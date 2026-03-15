<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\StudentsExport;

class StudentController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/students ─────────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('students', 'list', 'visited', 'Visited students list');

        $query = StudentProfile::with(['user', 'department', 'program'])
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('student_id', 'like', "%{$s}%")
                  ->orWhereHas('user', fn($uq) => $uq
                      ->where('name', 'like', "%{$s}%")
                      ->orWhere('email', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%")
                  );
            });
        }

        if ($request->filled('department_id'))   $query->where('department_id', $request->department_id);
        if ($request->filled('program_id'))      $query->where('program_id', $request->program_id);
        if ($request->filled('academic_status')) $query->where('academic_status', $request->academic_status);
        if ($request->filled('batch'))           $query->where('batch', $request->batch);
        if ($request->filled('semester'))        $query->where('semester', $request->semester);

        $students = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $students->map(fn($s) => $this->formatStudent($s)),
            'pagination' => [
                'total'        => $students->total(),
                'current_page' => $students->currentPage(),
                'last_page'    => $students->lastPage(),
            ],
        ]);
    }

    // ── GET /api/students/{id} ────────────────────────────────
    public function show($id)
    {
        $student = StudentProfile::with(['user', 'department', 'program'])->findOrFail($id);
        self::logVisit('students', 'view', 'visited', "Viewed student: {$student->user->name}", [], [], StudentProfile::class, $student->id);
        return response()->json(['success' => true, 'data' => $this->formatStudent($student, true)]);
    }

    // ── POST /api/students ────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            // User fields
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email',
            'phone'         => 'nullable|string|max:20',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'password'      => 'nullable|string|min:8',
            // Profile fields
            'student_id'    => 'required|string|unique:student_profiles,student_id',
            'department_id' => 'required|exists:departments,id',
            'program_id'    => 'required|exists:programs,id',
            'batch'         => 'nullable|string|max:10',
            'semester'      => 'nullable|string|max:20',
            'section'       => 'nullable|string|max:5',
            'admission_date'=> 'nullable|date',
        ]);

        DB::transaction(function () use ($request, &$student) {
            // Create user account
            $user = User::create([
                'name'          => $request->name,
                'email'         => $request->email,
                'phone'         => $request->phone,
                'gender'        => $request->gender,
                'date_of_birth' => $request->date_of_birth,
                'password'      => Hash::make($request->password ?? 'password'),
                'student_id'    => $request->student_id,
                'status'        => 'active',
            ]);
            $user->assignRole('student');

            // Create student profile
            $student = StudentProfile::create([
                'user_id'        => $user->id,
                'student_id'     => $request->student_id,
                'department_id'  => $request->department_id,
                'program_id'     => $request->program_id,
                'batch'          => $request->batch,
                'semester'       => $request->semester,
                'section'        => $request->section,
                'admission_date' => $request->admission_date,
                'academic_status'=> 'regular',
            ]);
        });

        self::logVisit('students', 'create', 'created', "Created student: {$request->name}", [], [], StudentProfile::class, $student->id);

        return response()->json([
            'success' => true,
            'message' => 'Student created successfully.',
            'data'    => $this->formatStudent($student->load(['user', 'department', 'program'])),
        ], 201);
    }

    // ── PUT /api/students/{id} ────────────────────────────────
    public function update(Request $request, $id)
    {
        $student = StudentProfile::with('user')->findOrFail($id);

        $request->validate([
            'name'           => 'sometimes|string|max:255',
            'email'          => 'sometimes|email|unique:users,email,' . $student->user_id,
            'phone'          => 'nullable|string|max:20',
            'gender'         => 'nullable|in:male,female,other',
            'date_of_birth'  => 'nullable|date',
            'department_id'  => 'sometimes|exists:departments,id',
            'program_id'     => 'sometimes|exists:programs,id',
            'semester'       => 'nullable|string|max:20',
            'section'        => 'nullable|string|max:5',
            'academic_status'=> 'nullable|in:regular,on-leave,suspended,graduated,dropped',
            'cgpa'           => 'nullable|numeric|min:0|max:4',
        ]);

        DB::transaction(function () use ($request, $student) {
            // Update user
            $student->user->update($request->only(['name', 'email', 'phone', 'gender', 'date_of_birth']));

            // Update profile
            $student->update($request->except(['name', 'email', 'phone', 'gender', 'date_of_birth', 'password']));
        });

        self::logVisit('students', 'edit', 'updated', "Updated student: {$student->user->name}", [], [], StudentProfile::class, $student->id);

        return response()->json([
            'success' => true,
            'message' => 'Student updated.',
            'data'    => $this->formatStudent($student->fresh()->load(['user', 'department', 'program'])),
        ]);
    }

    // ── DELETE /api/students/{id} ─────────────────────────────
    public function destroy($id)
    {
        $student = StudentProfile::with('user')->findOrFail($id);
        self::logVisit('students', 'delete', 'deleted', "Deleted student: {$student->user->name}", [], [], StudentProfile::class, $student->id);
        $student->user->delete();
        $student->delete();
        return response()->json(['success' => true, 'message' => 'Student deleted.']);
    }

    // ── GET /api/students/stats ───────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'       => StudentProfile::count(),
                'regular'     => StudentProfile::where('academic_status', 'regular')->count(),
                'on_leave'    => StudentProfile::where('academic_status', 'on-leave')->count(),
                'graduated'   => StudentProfile::where('academic_status', 'graduated')->count(),
                'suspended'   => StudentProfile::where('academic_status', 'suspended')->count(),
                'by_dept'     => StudentProfile::selectRaw('department_id, count(*) as count')
                    ->with('department:id,name,code')
                    ->groupBy('department_id')
                    ->get(),
            ],
        ]);
    }

    // ── GET /api/students/export ──────────────────────────────
    public function export(Request $request)
    {
        self::logVisit('students', 'export', 'exported', 'Exported students list');
        return Excel::download(new StudentsExport($request->all()), 'students-' . now()->format('Y-m-d') . '.xlsx');
    }

    // ── PATCH /api/students/{id}/status ───────────────────────
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['academic_status' => 'required|in:regular,on-leave,suspended,graduated,dropped']);
        $student = StudentProfile::findOrFail($id);
        $old = $student->academic_status;
        $student->update(['academic_status' => $request->academic_status]);
        self::logVisit('students', 'status', 'status-changed', "Student status changed from {$old} to {$request->academic_status}", ['status' => $old], ['status' => $request->academic_status], StudentProfile::class, $student->id);
        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatStudent(StudentProfile $s, bool $detailed = false): array
    {
        $data = [
            'id'              => $s->id,
            'student_id'      => $s->student_id,
            'user_id'         => $s->user_id,
            'name'            => $s->user?->name,
            'email'           => $s->user?->email,
            'phone'           => $s->user?->phone,
            'avatar_url'      => $s->user?->avatar_url,
            'gender'          => $s->user?->gender,
            'date_of_birth'   => $s->user?->date_of_birth?->format('Y-m-d'),
            'status'          => $s->user?->status,
            'department'      => $s->department ? ['id' => $s->department->id, 'name' => $s->department->name, 'code' => $s->department->code] : null,
            'program'         => $s->program ? ['id' => $s->program->id, 'name' => $s->program->name, 'code' => $s->program->code] : null,
            'batch'           => $s->batch,
            'semester'        => $s->semester,
            'section'         => $s->section,
            'academic_status' => $s->academic_status,
            'cgpa'            => $s->cgpa,
            'admission_date'  => $s->admission_date?->format('Y-m-d'),
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'shift'                => $s->shift,
                'admission_type'       => $s->admission_type,
                'expected_graduation'  => $s->expected_graduation?->format('Y-m-d'),
                'actual_graduation'    => $s->actual_graduation?->format('Y-m-d'),
                'completed_credits'    => $s->completed_credits,
                'total_credits_required' => $s->total_credits_required,
                'blood_group'          => $s->blood_group,
                'nationality'          => $s->nationality,
                'religion'             => $s->religion,
                'nid_number'           => $s->nid_number,
                'father_name'          => $s->father_name,
                'father_phone'         => $s->father_phone,
                'mother_name'          => $s->mother_name,
                'mother_phone'         => $s->mother_phone,
                'guardian_name'        => $s->guardian_name,
                'guardian_phone'       => $s->guardian_phone,
                'present_address'      => $s->present_address,
                'permanent_address'    => $s->permanent_address,
                'ssc_school'           => $s->ssc_school,
                'ssc_gpa'              => $s->ssc_gpa,
                'hsc_college'          => $s->hsc_college,
                'hsc_gpa'              => $s->hsc_gpa,
                'scholarship_type'     => $s->scholarship_type,
                'fee_waiver'           => $s->fee_waiver,
                'parsed_id'            => $s->parsed_student_id,
            ]);
        }

        return $data;
    }
}
