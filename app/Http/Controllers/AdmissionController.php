<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Department;
use App\Models\StudentProfile;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    use LogsPageVisit;

    /** Set only by the workflow below, never from request input. */
    private const WORKFLOW_FIELDS = [
        'application_number', 'status', 'merit_score', 'student_profile_id',
        'reviewed_by', 'reviewed_at', 'decided_by', 'decided_at',
    ];

    /** Which statuses each action may move an application from. */
    private const ALLOWED_FROM = [
        'under_review' => ['applied'],
        'shortlisted'  => ['applied', 'under_review'],
        'accepted'     => ['applied', 'under_review', 'shortlisted'],
        'rejected'     => ['applied', 'under_review', 'shortlisted', 'accepted'],
    ];

    // ── GET /api/admissions ───────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('admissions', 'list', 'visited', 'Visited admissions list');

        $query = Admission::with(['program', 'department', 'reviewedBy', 'decidedBy'])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('application_number', 'like', "%{$s}%")
                ->orWhere('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%")
            );
        }

        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('program_id'))    $query->where('program_id', $request->program_id);
        if ($request->filled('semester'))      $query->where('semester', $request->semester);
        if ($request->filled('hsc_group'))     $query->where('hsc_group', $request->hsc_group);

        $admissions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $admissions->map(fn($a) => $this->formatAdmission($a)),
            'pagination' => ['total' => $admissions->total(), 'current_page' => $admissions->currentPage(), 'last_page' => $admissions->lastPage()],
        ]);
    }

    // ── GET /api/admissions/{id} ──────────────────────────────
    public function show($id)
    {
        $admission = Admission::with(['program', 'department', 'reviewedBy', 'decidedBy', 'studentProfile.user'])->findOrFail($id);
        self::logVisit('admissions', 'view', 'visited', "Viewed application: {$admission->application_number}", [], [], Admission::class, $admission->id);
        return response()->json(['success' => true, 'data' => $this->formatAdmission($admission, true)]);
    }

    // ── POST /api/admissions ──────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'first_name'    => 'required|string|max:100',
            'last_name'     => 'required|string|max:100',
            'email'         => 'required|email|unique:admissions,email',
            'phone'         => 'nullable|string|max:20',
            'gender'        => 'nullable|in:male,female,other',
            'date_of_birth' => 'nullable|date',
            'program_id'    => 'nullable|exists:programs,id',
            'department_id' => [Rule::requiredIf(Institution::isUniversity()), 'nullable', 'exists:departments,id'],
            'semester'      => 'required|string',
            'academic_year' => 'required|string',
            'ssc_gpa'       => 'nullable|numeric|min:0|max:5',
            'hsc_gpa'       => 'nullable|numeric|min:0|max:5',
            'hsc_group'     => 'nullable|in:Science,Arts,Commerce,Vocational',
            'guardian_email'=> 'nullable|email|max:255',
        ]);

        $admission = Admission::create([
            ...$request->except(self::WORKFLOW_FIELDS),
            'application_number' => 'APP-' . date('Y') . '-' . strtoupper(Str::random(6)),
            'status'             => 'applied',
            'merit_score'        => $this->calculateMeritScore($request->ssc_gpa, $request->hsc_gpa),
        ]);

        self::logVisit('admissions', 'create', 'created', "Application created: {$admission->application_number}", [], [], Admission::class, $admission->id);

        return response()->json(['success' => true, 'message' => 'Application submitted.', 'data' => $this->formatAdmission($admission->load(['program', 'department']))], 201);
    }

    // ── PUT /api/admissions/{id} ──────────────────────────────
    public function update(Request $request, $id)
    {
        $admission = Admission::findOrFail($id);
        $admission->update($request->except(self::WORKFLOW_FIELDS));

        if ($request->filled('ssc_gpa') || $request->filled('hsc_gpa')) {
            $admission->merit_score = $this->calculateMeritScore($admission->ssc_gpa, $admission->hsc_gpa);
            $admission->save();
        }

        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $this->formatAdmission($admission->fresh()->load(['program', 'department']))]);
    }

    // ── PATCH /api/admissions/{id}/review ─────────────────────
    public function review($id)
    {
        $admission = Admission::findOrFail($id);
        if ($error = $this->cannotMove($admission, 'under_review')) return $error;
        $admission->update(['status' => 'under_review', 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        self::logVisit('admissions', 'review', 'updated', "Marked under review: {$admission->application_number}", [], [], Admission::class, $admission->id);
        return response()->json(['success' => true, 'message' => 'Marked as under review.']);
    }

    // ── PATCH /api/admissions/{id}/shortlist ──────────────────
    public function shortlist($id)
    {
        $admission = Admission::findOrFail($id);
        if ($error = $this->cannotMove($admission, 'shortlisted')) return $error;
        $admission->update(['status' => 'shortlisted']);
        self::logVisit('admissions', 'shortlist', 'updated', "Shortlisted: {$admission->application_number}", [], [], Admission::class, $admission->id);
        return response()->json(['success' => true, 'message' => 'Applicant shortlisted.']);
    }

    // ── PATCH /api/admissions/{id}/accept ─────────────────────
    public function accept(Request $request, $id)
    {
        $admission = Admission::findOrFail($id);
        if ($error = $this->cannotMove($admission, 'accepted')) return $error;
        $admission->update([
            'status'     => 'accepted',
            'decided_by' => auth()->id(),
            'decided_at' => now(),
            'remarks'    => $request->remarks,
        ]);
        self::logVisit('admissions', 'accept', 'updated', "Accepted: {$admission->application_number}", [], [], Admission::class, $admission->id);
        return response()->json(['success' => true, 'message' => 'Application accepted.']);
    }

    // ── PATCH /api/admissions/{id}/reject ─────────────────────
    public function reject(Request $request, $id)
    {
        $admission = Admission::findOrFail($id);
        if ($error = $this->cannotMove($admission, 'rejected')) return $error;
        $admission->update([
            'status'           => 'rejected',
            'decided_by'       => auth()->id(),
            'decided_at'       => now(),
            'rejection_reason' => $request->reason,
        ]);
        self::logVisit('admissions', 'reject', 'updated', "Rejected: {$admission->application_number}", [], [], Admission::class, $admission->id);
        return response()->json(['success' => true, 'message' => 'Application rejected.']);
    }

    // ── POST /api/admissions/{id}/enroll ──────────────────────
    // Convert accepted applicant to student
    public function enroll($id)
    {
        $result = DB::transaction(function () use ($id) {
            // Lock the application so a double submit cannot create two students
            $admission = Admission::with(['program', 'department'])->lockForUpdate()->findOrFail($id);

            if ($admission->status !== 'accepted') {
                return 'Only accepted applications can be enrolled.';
            }
            if ($admission->student_profile_id) {
                return 'Already enrolled as student.';
            }
            if (User::withTrashed()->where('email', $admission->email)->exists()) {
                return "An account already exists for {$admission->email}.";
            }

            $studentId = $this->nextStudentId($admission);
            // Each student gets their own temporary password and must replace it on first login
            $temporaryPassword = Str::password(10, symbols: false);

            $user = User::create([
                'name'                 => $admission->full_name,
                'email'                => $admission->email,
                'phone'                => $admission->phone,
                'gender'               => $admission->gender,
                'date_of_birth'        => $admission->date_of_birth,
                'password'             => $temporaryPassword,
                'must_change_password' => true,
                'status'               => 'active',
                'student_id'           => $studentId,
            ]);
            $user->assignRole('student');

            $profile = StudentProfile::create([
                'user_id'         => $user->id,
                'student_id'      => $studentId,
                'department_id'   => $admission->department_id,
                'program_id'      => $admission->program_id,
                'semester'        => $admission->semester,
                'academic_year'   => $admission->academic_year,
                'blood_group'     => $admission->blood_group,
                'present_address' => $admission->present_address,
                'father_name'     => $admission->father_name,
                'mother_name'     => $admission->mother_name,
                'guardian_phone'  => $admission->guardian_phone,
                'guardian_email'  => $admission->guardian_email,
                'academic_status' => 'regular',
                'admission_date'  => now()->format('Y-m-d'),
            ]);

            $admission->update(['status' => 'enrolled', 'student_profile_id' => $profile->id]);

            return [$admission, $studentId, $temporaryPassword];
        });

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 422);
        }
        [$admission, $studentId, $temporaryPassword] = $result;

        self::logVisit('admissions', 'enroll', 'created', "Enrolled applicant: {$admission->application_number}", [], [], Admission::class, $admission->id);

        return response()->json([
            'success' => true,
            'message' => 'Applicant enrolled as student successfully.',
            // Shown once so the officer can hand it to the student; it is not stored in plain text
            'credentials' => ['login' => $studentId, 'email' => $admission->email, 'temporary_password' => $temporaryPassword],
            'data'    => $this->formatAdmission($admission->fresh()),
        ]);
    }

    /**
     * Student IDs look like "251-0007-CSE": year, term (1 spring / 2 other),
     * serial within the department, department code. The department row is
     * locked so concurrent enrollments get consecutive serials, and the serial
     * continues from the highest existing one so deleted students never cause
     * a duplicate.
     */
    private function nextStudentId(Admission $admission): string
    {
        $dept   = Department::lockForUpdate()->find($admission->department_id);
        $prefix = date('y') . (str_contains(strtolower($admission->semester), 'spring') ? 1 : 2);
        $code   = $dept?->code ?? 'GEN';

        // Student IDs live on both the profile and the user account (both unique),
        // and either may outlive the other, so look at both
        $pattern = "{$prefix}-%-{$code}";
        $highest = StudentProfile::withTrashed()->where('student_id', 'like', $pattern)->pluck('student_id')
            ->merge(User::withTrashed()->where('student_id', 'like', $pattern)->pluck('student_id'))
            ->map(fn ($id) => (int) explode('-', $id)[1])
            ->max() ?? 0;

        return "{$prefix}-" . str_pad($highest + 1, 4, '0', STR_PAD_LEFT) . "-{$code}";
    }

    private function cannotMove(Admission $admission, string $to)
    {
        if (in_array($admission->status, self::ALLOWED_FROM[$to], true)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => "An application that is {$admission->status} cannot be moved to " . str_replace('_', ' ', $to) . '.',
        ], 422);
    }

    // ── DELETE /api/admissions/{id} ───────────────────────────
    public function destroy($id)
    {
        $admission = Admission::findOrFail($id);
        if ($admission->status === 'enrolled') {
            return response()->json(['success' => false, 'message' => 'An enrolled application cannot be deleted; it is linked to a student record.'], 422);
        }
        $admission->delete();
        return response()->json(['success' => true, 'message' => 'Application deleted.']);
    }

    // ── GET /api/admissions/stats ─────────────────────────────
    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total'        => Admission::count(),
            'applied'      => Admission::where('status', 'applied')->count(),
            'under_review' => Admission::where('status', 'under_review')->count(),
            'shortlisted'  => Admission::where('status', 'shortlisted')->count(),
            'accepted'     => Admission::where('status', 'accepted')->count(),
            'rejected'     => Admission::where('status', 'rejected')->count(),
            'enrolled'     => Admission::where('status', 'enrolled')->count(),
            'by_dept'      => Admission::selectRaw('department_id, count(*) as count')
                ->with('department:id,name,code')
                ->groupBy('department_id')->orderByDesc('count')->get(),
        ]]);
    }

    // ── Helper ────────────────────────────────────────────────
    private function calculateMeritScore(?float $sscGpa, ?float $hscGpa): float
    {
        return round((($sscGpa ?? 0) * 40) + (($hscGpa ?? 0) * 60), 2);
    }

    private function formatAdmission(Admission $a, bool $detailed = false): array
    {
        $data = [
            'id'                 => $a->id,
            'application_number' => $a->application_number,
            'full_name'          => $a->full_name,
            'first_name'         => $a->first_name,
            'last_name'          => $a->last_name,
            'email'              => $a->email,
            'phone'              => $a->phone,
            'gender'             => $a->gender,
            'date_of_birth'      => $a->date_of_birth?->format('Y-m-d'),
            'hsc_group'          => $a->hsc_group,
            'hsc_gpa'            => $a->hsc_gpa,
            'ssc_gpa'            => $a->ssc_gpa,
            'merit_score'        => $a->merit_score,
            'semester'           => $a->semester,
            'academic_year'      => $a->academic_year,
            'status'             => $a->status,
            'remarks'            => $a->remarks,
            'rejection_reason'   => $a->rejection_reason,
            'created_at'         => $a->created_at?->format('Y-m-d'),
            'decided_at'         => $a->decided_at?->format('Y-m-d'),
            'reviewed_by'        => $a->reviewedBy?->name,
            'decided_by'         => $a->decidedBy?->name,
            'student_profile_id' => $a->student_profile_id,
            'department'         => $a->department ? ['id' => $a->department->id, 'name' => $a->department->name, 'code' => $a->department->code] : null,
            'program'            => $a->program    ? ['id' => $a->program->id,    'name' => $a->program->name,    'code' => $a->program->code]    : null,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'nationality'       => $a->nationality,
                'religion'          => $a->religion,
                'blood_group'       => $a->blood_group,
                'present_address'   => $a->present_address,
                'permanent_address' => $a->permanent_address,
                'ssc_board'         => $a->ssc_board,
                'ssc_year'          => $a->ssc_year,
                'hsc_board'         => $a->hsc_board,
                'hsc_year'          => $a->hsc_year,
                'father_name'       => $a->father_name,
                'father_occupation' => $a->father_occupation,
                'father_phone'      => $a->father_phone,
                'mother_name'       => $a->mother_name,
                'guardian_phone'    => $a->guardian_phone,
                'guardian_email'    => $a->guardian_email,
                'family_income'     => $a->family_income,
                'quota'             => $a->quota,
            ]);
        }

        return $data;
    }
}
