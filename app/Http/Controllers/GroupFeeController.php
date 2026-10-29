<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\StudentGroup;
use App\Traits\LogsPageVisit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Monthly fees of school sections and coaching batches: every active student
 * gets one invoice per group and month (never twice), less their fixed
 * discount. Payments go through FeeController::collectPayment.
 */
class GroupFeeController extends Controller
{
    use LogsPageVisit;

    private const OPEN = ['unpaid', 'partial', 'overdue'];

    // GET /api/fees/monthly?month=YYYY-MM
    public function overview(Request $request)
    {
        $month = $this->month($request);

        $groups = StudentGroup::with('classLevel')->withCount('activeMembers')->where('is_active', true)->get()
            ->sortBy(fn ($g) => [$g->kind, $g->classLevel?->sort_order ?? 999, $g->name])->values();
        $invoices = FeeInvoice::whereIn('student_group_id', $groups->pluck('id'))->where('billing_month', $month)->get()->groupBy('student_group_id');

        $rows = $groups->map(function ($g) use ($invoices) {
            $billed = $invoices[$g->id] ?? collect();

            return [
                'id'             => $g->id,
                'kind'           => $g->kind,
                'display_name'   => $g->display_name,
                'monthly_fee'    => $g->monthly_fee !== null ? (float) $g->monthly_fee : null,
                'students_count' => $g->active_members_count,
                'invoiced'       => $billed->count(),
                'billed'         => round($billed->sum(fn ($i) => $i->net_amount), 2),
                'collected'      => round($billed->sum(fn ($i) => (float) $i->paid_amount), 2),
                'due'            => round($billed->whereIn('status', self::OPEN)->sum(fn ($i) => max(0, $i->due_amount)), 2),
            ];
        });

        return response()->json(['success' => true, 'data' => [
            'month'  => $month,
            'groups' => $rows,
            'totals' => [
                'billed'    => round($rows->sum('billed'), 2),
                'collected' => round($rows->sum('collected'), 2),
                'due'       => round($rows->sum('due'), 2),
            ],
        ]]);
    }

    // GET /api/student-groups/{id}/fees?month=YYYY-MM
    public function sheet(Request $request, $id)
    {
        $group = StudentGroup::with('classLevel')->findOrFail($id);
        $month = $this->month($request);

        $members  = $group->activeMembers()->with('student.user')->orderBy('roll_number')->get();
        $invoices = FeeInvoice::with(['payments' => fn ($q) => $q->latest('id')])
            ->where('student_group_id', $group->id)
            ->whereIn('student_profile_id', $members->pluck('student_profile_id'))
            ->where('billing_month', '<=', $month)
            ->get()
            ->groupBy('student_profile_id');

        $rows = $members->map(function ($m) use ($invoices, $month) {
            $mine    = $invoices[$m->student_profile_id] ?? collect();
            $current = $mine->firstWhere('billing_month', $month);
            $earlier = $mine->where('billing_month', '<', $month)->whereIn('status', self::OPEN);

            return [
                'member_id'          => $m->id,
                'student_profile_id' => $m->student_profile_id,
                'roll_number'        => $m->roll_number,
                'name'               => $m->student?->user?->name,
                'student_id'         => $m->student?->student_id,
                'monthly_discount'   => (float) $m->monthly_discount,
                'discount_note'      => $m->discount_note,
                'invoice'            => $current ? $this->invoice($current) : null,
                'earlier_due'        => round($earlier->sum(fn ($i) => max(0, $i->due_amount)), 2),
                'earlier_months'     => $earlier->pluck('billing_month')->sort()->values(),
            ];
        });

        $billed = $rows->pluck('invoice')->filter();

        return response()->json(['success' => true, 'data' => [
            'month'       => $month,
            'month_label' => $this->label($month),
            'monthly_fee' => $group->monthly_fee !== null ? (float) $group->monthly_fee : null,
            'students'    => $rows,
            'totals'      => [
                'students'  => $rows->count(),
                'invoiced'  => $billed->count(),
                'billed'    => round($billed->sum('net_amount'), 2),
                'collected' => round($billed->sum('paid_amount'), 2),
                'due'       => round($billed->sum('due_amount'), 2),
            ],
        ]]);
    }

    // POST /api/student-groups/{id}/fees/generate
    public function generate(Request $request, $id)
    {
        $group = StudentGroup::findOrFail($id);
        [$month, $due] = $this->billingInput($request);

        if (! (float) $group->monthly_fee) {
            throw ValidationException::withMessages(['month' => "Set a monthly fee for {$group->display_name} first."]);
        }
        $created = $this->bill($group, $month, $due);
        $label   = $this->label($month);

        self::logVisit('fees', 'monthly', 'created', "Generated {$created} {$label} invoices for {$group->display_name}");

        return response()->json([
            'success' => true,
            'message' => $created ? "{$created} " . Str::plural('invoice', $created) . " created for {$label}." : "Everyone is already billed for {$label}.",
            'created' => $created,
        ]);
    }

    // POST /api/fees/monthly/generate — every active section/batch with a monthly fee
    public function generateAll(Request $request)
    {
        [$month, $due] = $this->billingInput($request);

        $created = 0;
        $groups  = 0;
        foreach (StudentGroup::where('is_active', true)->where('monthly_fee', '>', 0)->get() as $group) {
            $n = $this->bill($group, $month, $due);
            $created += $n;
            $groups  += $n > 0 ? 1 : 0;
        }
        $label = $this->label($month);

        self::logVisit('fees', 'monthly', 'created', "Generated {$created} {$label} invoices in {$groups} groups");

        return response()->json([
            'success' => true,
            'message' => $created ? "{$created} " . Str::plural('invoice', $created) . " created for {$label}." : "Everyone is already billed for {$label}.",
            'created' => $created,
        ]);
    }

    // PATCH /api/student-groups/{id}/members/{memberId}/discount
    public function discount(Request $request, $id, $memberId)
    {
        $group  = StudentGroup::findOrFail($id);
        $member = $group->members()->findOrFail($memberId);

        $max  = $group->monthly_fee !== null ? (float) $group->monthly_fee : 1_000_000;
        $data = $request->validate([
            'monthly_discount' => "required|numeric|min:0|max:{$max}",
            'discount_note'    => 'nullable|string|max:255',
        ], ['monthly_discount.max' => 'The discount cannot be more than the monthly fee.']);

        $updated = DB::transaction(function () use ($member, $group, $data) {
            $member->update($data);

            // Also fix this month's and later invoices nobody has paid anything on yet
            $invoices = FeeInvoice::where('student_group_id', $group->id)
                ->where('student_profile_id', $member->student_profile_id)
                ->where('billing_month', '>=', today()->format('Y-m'))
                ->where('paid_amount', 0)
                ->whereIn('status', ['unpaid', 'overdue', 'waived'])
                ->lockForUpdate()
                ->get();
            foreach ($invoices as $invoice) {
                $discount = min((float) $invoice->amount, (float) $data['monthly_discount']);
                $invoice->update(['discount' => $discount, 'remarks' => $discount > 0 ? ($data['discount_note'] ?? null) : null]);
                $discount >= (float) $invoice->amount ? $invoice->update(['status' => 'waived']) : $invoice->updateStatus();
            }

            return $invoices->count();
        });

        return response()->json(['success' => true, 'message' => $updated
            ? "Discount saved and applied to {$updated} unpaid " . Str::plural('invoice', $updated) . '.'
            : 'Discount saved. It applies to the next invoices.']);
    }

    // GET /api/fees/payments/{id}/receipt
    public function receipt(Request $request, $id)
    {
        $payment = FeePayment::with(['invoice.group.classLevel', 'student.user', 'collectedBy'])->findOrFail($id);
        $user    = $request->user();
        abort_unless($user->can('fees.read') || $user->can('fees.collect') || (int) $user->studentProfile?->id === (int) $payment->student_profile_id, 403);

        $invoice = $payment->invoice;
        $pdf = Pdf::loadView('fees.receipt', [
            'institution' => config('app.name'),
            'payment'     => $payment,
            'invoice'     => $invoice,
            'student'     => $payment->student,
            'group'       => $invoice?->group,
            'roll'        => $invoice?->group?->members()->where('student_profile_id', $payment->student_profile_id)->value('roll_number'),
        ])->setPaper('a5', 'portrait');

        $name = "receipt-{$payment->transaction_id}.pdf";

        return $request->boolean('inline') ? $pdf->stream($name) : $pdf->download($name);
    }

    /** Creates the missing invoices of one group for a month; returns how many. */
    private function bill(StudentGroup $group, string $month, string $due): int
    {
        $start = Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        $end   = $start->copy()->endOfMonth();

        // A batch that has not started or has already ended is not billed
        if (($group->starts_on && $group->starts_on->gt($end)) || ($group->ends_on && $group->ends_on->lt($start))) {
            return 0;
        }

        return DB::transaction(function () use ($group, $month, $due, $end) {
            // One generator at a time per group, so a month is never billed twice
            $group  = StudentGroup::lockForUpdate()->find($group->id);
            $fee    = (float) $group->monthly_fee;
            $billed = FeeInvoice::withTrashed()->where('student_group_id', $group->id)->where('billing_month', $month)->pluck('student_profile_id')->flip();

            $created = 0;
            $members = $group->activeMembers()->where(fn ($q) => $q->whereNull('joined_on')->orWhere('joined_on', '<=', $end->toDateString()))->get();
            foreach ($members as $m) {
                if (isset($billed[$m->student_profile_id])) {
                    continue;
                }
                $discount = min($fee, (float) $m->monthly_discount);
                FeeInvoice::create([
                    'invoice_number'     => 'INV-' . strtoupper(Str::random(8)),
                    'student_profile_id' => $m->student_profile_id,
                    'student_group_id'   => $group->id,
                    'billing_month'      => $month,
                    'fee_type'           => 'tuition',
                    'description'        => "Monthly fee – {$this->label($month)}",
                    'amount'             => $fee,
                    'discount'           => $discount,
                    'semester'           => $this->label($month),
                    'academic_year'      => $group->academic_year ?? substr($month, 0, 4),
                    'due_date'           => $due,
                    'status'             => $discount >= $fee ? 'waived' : 'unpaid',
                    'remarks'            => $discount > 0 ? $m->discount_note : null,
                ]);
                $created++;
            }

            return $created;
        });
    }

    private function month(Request $request): string
    {
        return $request->validate(['month' => 'nullable|date_format:Y-m'])['month'] ?? today()->format('Y-m');
    }

    /** @return array{string, string} month and due date */
    private function billingInput(Request $request): array
    {
        $next = today()->addMonthNoOverflow()->format('Y-m');
        $data = $request->validate([
            'month'    => "required|date_format:Y-m|before_or_equal:{$next}",
            'due_date' => 'required|date',
        ], ['month.before_or_equal' => 'You can bill at most one month ahead.']);

        return [$data['month'], $data['due_date']];
    }

    private function label(string $month): string
    {
        return Carbon::createFromFormat('Y-m-d', "{$month}-01")->format('F Y');
    }

    private function invoice(FeeInvoice $i): array
    {
        $open = in_array($i->status, self::OPEN, true);

        return [
            'id'              => $i->id,
            'invoice_number'  => $i->invoice_number,
            'amount'          => (float) $i->amount,
            'discount'        => (float) $i->discount,
            'net_amount'      => round($i->net_amount, 2),
            'paid_amount'     => (float) $i->paid_amount,
            'due_amount'      => $open ? round(max(0, $i->due_amount), 2) : 0.0,
            'status'          => $i->status,
            'is_overdue'      => $open && $i->due_date?->lt(today()),
            'due_date'        => $i->due_date?->toDateString(),
            'last_payment_id' => $i->payments->first()?->id,
        ];
    }
}
