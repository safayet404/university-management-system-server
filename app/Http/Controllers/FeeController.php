<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\StudentProfile;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeeController extends Controller
{
    use LogsPageVisit;

    // ── Fee Structures ────────────────────────────────────────

    public function structures(Request $request)
    {
        self::logVisit('fees', 'structures', 'visited', 'Visited fee structures');
        $query = FeeStructure::with(['program', 'department'])->latest();
        if ($request->filled('fee_type'))    $query->where('fee_type', $request->fee_type);
        if ($request->filled('program_id'))  $query->where('program_id', $request->program_id);
        if ($request->filled('status'))      $query->where('status', $request->status);
        if ($request->boolean('all'))        return response()->json(['success' => true, 'data' => $query->get()]);
        $items = $query->paginate($request->get('per_page', 15));
        return response()->json(['success' => true, 'data' => $items->items(), 'pagination' => ['total' => $items->total(), 'current_page' => $items->currentPage(), 'last_page' => $items->lastPage()]]);
    }

    public function storeStructure(Request $request)
    {
        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'fee_type'      => 'required|in:tuition,lab,library,exam,admission,transport,hostel,misc',
            'amount'        => 'required|numeric|min:0',
            'program_id'    => 'nullable|exists:programs,id',
            'department_id' => 'nullable|exists:departments,id',
            'semester'      => 'nullable|string',
            'academic_year' => 'nullable|string',
            'is_mandatory'  => 'nullable|boolean',
            'description'   => 'nullable|string',
        ]);
        $fs = FeeStructure::create($validated);
        self::logVisit('fees', 'structure-create', 'created', "Created fee structure: {$fs->name}", [], $validated, FeeStructure::class, $fs->id);
        return response()->json(['success' => true, 'message' => 'Fee structure created.', 'data' => $fs], 201);
    }

    public function updateStructure(Request $request, $id)
    {
        $fs = FeeStructure::findOrFail($id);
        $validated = $request->validate([
            'name'          => 'sometimes|string|max:255',
            'fee_type'      => 'sometimes|in:tuition,lab,library,exam,admission,transport,hostel,misc',
            'amount'        => 'sometimes|numeric|min:0',
            'is_mandatory'  => 'nullable|boolean',
            'description'   => 'nullable|string',
            'status'        => 'nullable|in:active,inactive',
        ]);
        $fs->update($validated);
        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $fs->fresh()]);
    }

    public function destroyStructure($id)
    {
        FeeStructure::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    // ── Invoices ──────────────────────────────────────────────

    public function invoices(Request $request)
    {
        self::logVisit('fees', 'invoices', 'visited', 'Visited fee invoices');
        $query = FeeInvoice::with(['student.user', 'student.department'])->latest();
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('invoice_number', 'like', "%{$s}%")
                ->orWhereHas('student', fn($sq) => $sq->where('student_id', 'like', "%{$s}%"))
                ->orWhereHas('student.user', fn($uq) => $uq->where('name', 'like', "%{$s}%"))
            );
        }
        if ($request->filled('status'))            $query->where('status', $request->status);
        if ($request->filled('fee_type'))          $query->where('fee_type', $request->fee_type);
        if ($request->filled('semester'))          $query->where('semester', $request->semester);
        if ($request->filled('academic_year'))     $query->where('academic_year', $request->academic_year);
        if ($request->filled('student_profile_id'))$query->where('student_profile_id', $request->student_profile_id);

        $invoices = $query->paginate($request->get('per_page', 15));
        return response()->json([
            'success'    => true,
            'data'       => $invoices->map(fn($i) => $this->formatInvoice($i)),
            'pagination' => ['total' => $invoices->total(), 'current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage()],
        ]);
    }

    public function storeInvoice(Request $request)
    {
        $request->validate([
            'student_profile_id' => 'required|exists:student_profiles,id',
            'fee_structure_id'   => 'nullable|exists:fee_structures,id',
            'fee_type'           => 'required|in:tuition,lab,library,exam,admission,transport,hostel,misc',
            'description'        => 'required|string|max:255',
            'amount'             => 'required|numeric|min:0',
            'discount'           => 'nullable|numeric|min:0',
            'semester'           => 'required|string',
            'academic_year'      => 'required|string',
            'due_date'           => 'required|date',
        ]);

        $invoice = FeeInvoice::create([
            ...$request->validated(),
            'invoice_number' => 'INV-' . strtoupper(Str::random(8)),
            'status'         => 'unpaid',
        ]);

        self::logVisit('fees', 'invoice-create', 'created', "Invoice created: {$invoice->invoice_number}", [], [], FeeInvoice::class, $invoice->id);

        return response()->json(['success' => true, 'message' => 'Invoice created.', 'data' => $this->formatInvoice($invoice->load('student.user'))], 201);
    }

    // Bulk generate invoices for all students
    public function bulkGenerate(Request $request)
    {
        $request->validate([
            'fee_structure_id' => 'required|exists:fee_structures,id',
            'semester'         => 'required|string',
            'academic_year'    => 'required|string',
            'due_date'         => 'required|date',
            'program_id'       => 'nullable|exists:programs,id',
            'department_id'    => 'nullable|exists:departments,id',
        ]);

        $fs = FeeStructure::findOrFail($request->fee_structure_id);

        $query = StudentProfile::where('academic_status', 'regular');
        if ($request->program_id)    $query->where('program_id', $request->program_id);
        if ($request->department_id) $query->where('department_id', $request->department_id);

        $students = $query->get();
        $created  = 0;

        foreach ($students as $student) {
            // Skip if invoice already exists
            if (FeeInvoice::where(['student_profile_id' => $student->id, 'fee_structure_id' => $fs->id, 'semester' => $request->semester, 'academic_year' => $request->academic_year])->exists()) continue;

            FeeInvoice::create([
                'invoice_number'     => 'INV-' . strtoupper(Str::random(8)),
                'student_profile_id' => $student->id,
                'fee_structure_id'   => $fs->id,
                'fee_type'           => $fs->fee_type,
                'description'        => $fs->name,
                'amount'             => $fs->amount,
                'semester'           => $request->semester,
                'academic_year'      => $request->academic_year,
                'due_date'           => $request->due_date,
                'status'             => 'unpaid',
            ]);
            $created++;
        }

        self::logVisit('fees', 'bulk-generate', 'created', "Bulk generated {$created} invoices for {$fs->name}");

        return response()->json(['success' => true, 'message' => "{$created} invoices generated."]);
    }

    // ── Payments ──────────────────────────────────────────────

    public function collectPayment(Request $request, $invoiceId)
    {
        $invoice = FeeInvoice::with('student')->findOrFail($invoiceId);
        $request->validate([
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,online,cheque,bkash,nagad',
            'payment_date'   => 'required|date',
            'remarks'        => 'nullable|string',
        ]);

        DB::transaction(function () use ($request, $invoice) {
            // Create payment record
            FeePayment::create([
                'fee_invoice_id'     => $invoice->id,
                'student_profile_id' => $invoice->student_profile_id,
                'collected_by'       => auth()->id(),
                'transaction_id'     => 'TXN-' . strtoupper(Str::random(10)),
                'amount'             => $request->amount,
                'payment_method'     => $request->payment_method,
                'payment_date'       => $request->payment_date,
                'remarks'            => $request->remarks,
            ]);

            // Update invoice paid amount
            $invoice->paid_amount = (float)$invoice->paid_amount + (float)$request->amount;
            $invoice->save();
            $invoice->updateStatus();
        });

        self::logVisit('fees', 'payment', 'created', "Payment of {$request->amount} collected for invoice {$invoice->invoice_number}", [], ['amount' => $request->amount, 'method' => $request->payment_method], FeeInvoice::class, $invoice->id);

        return response()->json(['success' => true, 'message' => 'Payment collected.', 'data' => $this->formatInvoice($invoice->fresh()->load('student.user'))]);
    }

    // ── Reports & Stats ───────────────────────────────────────

    public function stats()
    {
        $today = today();
        return response()->json(['success' => true, 'data' => [
            'total_invoiced'    => FeeInvoice::sum('amount'),
            'total_collected'   => FeeInvoice::sum('paid_amount'),
            'total_pending'     => FeeInvoice::whereIn('status', ['unpaid', 'partial', 'overdue'])->sum(DB::raw('amount - discount + fine - paid_amount')),
            'paid_count'        => FeeInvoice::where('status', 'paid')->count(),
            'unpaid_count'      => FeeInvoice::where('status', 'unpaid')->count(),
            'overdue_count'     => FeeInvoice::where('status', 'overdue')->count(),
            'partial_count'     => FeeInvoice::where('status', 'partial')->count(),
            'collected_today'   => FeePayment::whereDate('payment_date', $today)->sum('amount'),
            'by_type'           => FeeInvoice::selectRaw('fee_type, sum(amount) as total, sum(paid_amount) as collected, count(*) as count')
                ->groupBy('fee_type')->get(),
        ]]);
    }

    public function defaulters(Request $request)
    {
        self::logVisit('fees', 'defaulters', 'visited', 'Viewed defaulters list');
        $invoices = FeeInvoice::with(['student.user', 'student.department'])
            ->whereIn('status', ['unpaid', 'overdue', 'partial'])
            ->where('due_date', '<', today())
            ->latest('due_date')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $invoices->map(fn($i) => $this->formatInvoice($i)),
            'pagination' => ['total' => $invoices->total(), 'current_page' => $invoices->currentPage(), 'last_page' => $invoices->lastPage()],
        ]);
    }

    public function studentInvoices($studentId)
    {
        $invoices = FeeInvoice::with('payments')
            ->where('student_profile_id', $studentId)
            ->latest()->get();

        $total   = $invoices->sum('amount');
        $paid    = $invoices->sum('paid_amount');
        $pending = $total - $paid;

        return response()->json([
            'success' => true,
            'summary' => ['total' => $total, 'paid' => $paid, 'pending' => $pending],
            'data'    => $invoices->map(fn($i) => $this->formatInvoice($i)),
        ]);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatInvoice(FeeInvoice $i): array
    {
        return [
            'id'             => $i->id,
            'invoice_number' => $i->invoice_number,
            'fee_type'       => $i->fee_type,
            'description'    => $i->description,
            'amount'         => $i->amount,
            'discount'       => $i->discount,
            'fine'           => $i->fine,
            'net_amount'     => $i->net_amount,
            'paid_amount'    => $i->paid_amount,
            'due_amount'     => $i->due_amount,
            'semester'       => $i->semester,
            'academic_year'  => $i->academic_year,
            'due_date'       => $i->due_date?->format('Y-m-d'),
            'status'         => $i->status,
            'remarks'        => $i->remarks,
            'created_at'     => $i->created_at?->format('Y-m-d'),
            'student'        => $i->student ? [
                'id'         => $i->student->id,
                'student_id' => $i->student->student_id,
                'name'       => $i->student->user?->name,
                'avatar_url' => $i->student->user?->avatar_url,
                'department' => $i->student->department?->code,
                'program'    => $i->student->program?->code,
            ] : null,
        ];
    }
}
