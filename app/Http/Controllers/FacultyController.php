<?php

namespace App\Http\Controllers;

use App\Models\FacultyProfile;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\FacultyExport;

class FacultyController extends Controller
{
    use LogsPageVisit;

    // ── GET /api/faculty ──────────────────────────────────────
    public function index(Request $request)
    {
        self::logVisit('faculty', 'list', 'visited', 'Visited faculty list');

        $query = FacultyProfile::with(['user', 'department'])->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('employee_id', 'like', "%{$s}%")
                    ->orWhere('designation', 'like', "%{$s}%")
                    ->orWhere('specialization', 'like', "%{$s}%")
                    ->orWhereHas(
                        'user',
                        fn($uq) => $uq
                            ->where('name', 'like', "%{$s}%")
                            ->orWhere('email', 'like', "%{$s}%")
                    );
            });
        }

        if ($request->filled('department_id'))    $query->where('department_id', $request->department_id);
        if ($request->filled('designation'))       $query->where('designation', $request->designation);
        if ($request->filled('employment_status')) $query->where('employment_status', $request->employment_status);
        if ($request->filled('employment_type'))   $query->where('employment_type', $request->employment_type);

        $faculty = $query->paginate($request->input('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $faculty->map(fn($f) => $this->formatFaculty($f)),
            'pagination' => [
                'total'        => $faculty->total(),
                'current_page' => $faculty->currentPage(),
                'last_page'    => $faculty->lastPage(),
            ],
        ]);
    }

    // ── GET /api/faculty/{id} ─────────────────────────────────
    public function show($id)
    {
        $faculty = FacultyProfile::with(['user', 'department'])->findOrFail($id);
        self::logVisit('faculty', 'view', 'visited', "Viewed faculty: {$faculty->user->name}", [], [], FacultyProfile::class, $faculty->id);
        return response()->json(['success' => true, 'data' => $this->formatFaculty($faculty, true)]);
    }

    // ── POST /api/faculty ─────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'phone'             => 'nullable|string|max:20',
            'gender'            => 'nullable|in:male,female,other',
            'password'          => 'nullable|string|min:8',
            'employee_id'       => 'required|string|unique:faculty_profiles,employee_id',
            'department_id'     => 'required|exists:departments,id',
            'designation'       => 'required|string|max:100',
            'employment_type'   => 'nullable|in:full-time,part-time,visiting,adjunct',
            'specialization'    => 'nullable|string|max:255',
            'joining_date'      => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, &$faculty) {
            $user = User::create([
                'name'        => $request->name,
                'email'       => $request->email,
                'phone'       => $request->phone,
                'gender'      => $request->gender,
                'password'    => Hash::make($request->password ?? 'password'),
                'employee_id' => $request->employee_id,
                'status'      => 'active',
            ]);
            $user->assignRole('faculty');

            $faculty = FacultyProfile::create([
                'user_id'          => $user->id,
                'employee_id'      => $request->employee_id,
                'department_id'    => $request->department_id,
                'designation'      => $request->designation,
                'employment_type'  => $request->employment_type ?? 'full-time',
                'specialization'   => $request->specialization,
                'joining_date'     => $request->joining_date,
                'employment_status' => 'active',
            ]);
        });

        self::logVisit('faculty', 'create', 'created', "Created faculty: {$request->name}", [], [], FacultyProfile::class, $faculty->id);

        return response()->json([
            'success' => true,
            'message' => 'Faculty created successfully.',
            'data'    => $this->formatFaculty($faculty->load(['user', 'department'])),
        ], 201);
    }

    // ── PUT /api/faculty/{id} ─────────────────────────────────
    public function update(Request $request, $id)
    {
        $faculty = FacultyProfile::with('user')->findOrFail($id);

        $request->validate([
            'name'               => 'sometimes|string|max:255',
            'email'              => 'sometimes|email|unique:users,email,' . $faculty->user_id,
            'phone'              => 'nullable|string|max:20',
            'gender'             => 'nullable|in:male,female,other',
            'department_id'      => 'sometimes|exists:departments,id',
            'designation'        => 'sometimes|string|max:100',
            'employment_type'    => 'nullable|in:full-time,part-time,visiting,adjunct',
            'employment_status'  => 'nullable|in:active,on-leave,resigned,retired',
            'specialization'     => 'nullable|string|max:255',
            'research_interests' => 'nullable|string',
            'highest_degree'     => 'nullable|string|max:50',
            'phd_institution'    => 'nullable|string|max:255',
            'phd_year'           => 'nullable|string|max:4',
            'publications_count' => 'nullable|integer|min:0',
            'citations_count'    => 'nullable|integer|min:0',
            'h_index'            => 'nullable|numeric|min:0',
            'office_room'        => 'nullable|string|max:50',
            'office_phone'       => 'nullable|string|max:20',
            'website'            => 'nullable|string|max:255',
            'linkedin'           => 'nullable|string|max:255',
            'google_scholar'     => 'nullable|string|max:255',
        ]);

        DB::transaction(function () use ($request, $faculty) {
            $faculty->user->update($request->only(['name', 'email', 'phone', 'gender', 'date_of_birth']));
            $faculty->update($request->except(['name', 'email', 'phone', 'gender', 'date_of_birth', 'password']));
        });

        self::logVisit('faculty', 'edit', 'updated', "Updated faculty: {$faculty->user->name}", [], [], FacultyProfile::class, $faculty->id);

        return response()->json([
            'success' => true,
            'message' => 'Faculty updated.',
            'data'    => $this->formatFaculty($faculty->fresh()->load(['user', 'department'])),
        ]);
    }

    // ── DELETE /api/faculty/{id} ──────────────────────────────
    public function destroy($id)
    {
        $faculty = FacultyProfile::with('user')->findOrFail($id);
        self::logVisit('faculty', 'delete', 'deleted', "Deleted faculty: {$faculty->user->name}", [], [], FacultyProfile::class, $faculty->id);
        $faculty->user->delete();
        $faculty->delete();
        return response()->json(['success' => true, 'message' => 'Faculty deleted.']);
    }

    // ── GET /api/faculty/stats ────────────────────────────────
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'       => FacultyProfile::count(),
                'active'      => FacultyProfile::where('employment_status', 'active')->count(),
                'on_leave'    => FacultyProfile::where('employment_status', 'on-leave')->count(),
                'full_time'   => FacultyProfile::where('employment_type', 'full-time')->count(),
                'part_time'   => FacultyProfile::where('employment_type', 'part-time')->count(),
                'by_designation' => FacultyProfile::selectRaw('designation, count(*) as count')
                    ->groupBy('designation')
                    ->orderByDesc('count')
                    ->get(),
                'by_dept'     => FacultyProfile::selectRaw('department_id, count(*) as count')
                    ->with('department:id,name,code')
                    ->groupBy('department_id')
                    ->get(),
            ],
        ]);
    }

    // ── GET /api/faculty/export ───────────────────────────────
    public function export(Request $request)
    {
        self::logVisit('faculty', 'export', 'exported', 'Exported faculty list');
        return Excel::download(new FacultyExport($request->all()), 'faculty-' . now()->format('Y-m-d') . '.xlsx');
    }

    // ── PATCH /api/faculty/{id}/status ────────────────────────
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['employment_status' => 'required|in:active,on-leave,resigned,retired']);
        $faculty = FacultyProfile::findOrFail($id);
        $old = $faculty->employment_status;
        $faculty->update(['employment_status' => $request->employment_status]);
        self::logVisit('faculty', 'status', 'status-changed', "Faculty status: {$old} → {$request->employment_status}", ['status' => $old], ['status' => $request->employment_status], FacultyProfile::class, $faculty->id);
        return response()->json(['success' => true, 'message' => 'Status updated.']);
    }

    // ── Helper ────────────────────────────────────────────────
    private function formatFaculty(FacultyProfile $f, bool $detailed = false): array
    {
        $data = [
            'id'                => $f->id,
            'user_id'           => $f->user_id,
            'employee_id'       => $f->employee_id,
            'name'              => $f->user?->name,
            'email'             => $f->user?->email,
            'phone'             => $f->user?->phone,
            'avatar_url'        => $f->user?->avatar_url,
            'gender'            => $f->user?->gender,
            'status'            => $f->user?->status,
            'department'        => $f->department ? ['id' => $f->department->id, 'name' => $f->department->name, 'code' => $f->department->code] : null,
            'designation'       => $f->designation,
            'employment_type'   => $f->employment_type,
            'employment_status' => $f->employment_status,
            'specialization'    => $f->specialization,
            'highest_degree'    => $f->highest_degree,
            'joining_date'      => $f->joining_date?->format('Y-m-d'),
            'publications_count' => $f->publications_count,
            'citations_count'   => $f->citations_count,
            'h_index'           => $f->h_index,
        ];

        if ($detailed) {
            $data = array_merge($data, [
                'research_interests'  => $f->research_interests,
                'phd_institution'     => $f->phd_institution,
                'phd_year'            => $f->phd_year,
                'masters_institution' => $f->masters_institution,
                'masters_year'        => $f->masters_year,
                'bachelors_institution' => $f->bachelors_institution,
                'bachelors_year'      => $f->bachelors_year,
                'office_room'         => $f->office_room,
                'office_phone'        => $f->office_phone,
                'personal_email'      => $f->personal_email,
                'website'             => $f->website,
                'linkedin'            => $f->linkedin,
                'google_scholar'      => $f->google_scholar,
                'orcid'               => $f->orcid,
                'blood_group'         => $f->blood_group,
                'nationality'         => $f->nationality,
                'present_address'     => $f->present_address,
            ]);
        }

        return $data;
    }
}
