<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use LogsPageVisit;

    public function index(Request $request)
    {
        self::logVisit('courses', 'list', 'visited', 'Visited courses list');

        $query = Course::with(['department', 'program', 'faculty.user'])
            ->withCount(['enrollments as enrolled_count' => fn($q) => $q->where('status', 'approved')])
            ->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('name', 'like', "%{$s}%")
                ->orWhere('code', 'like', "%{$s}%")
            );
        }
        if ($request->filled('department_id'))  $query->where('department_id', $request->department_id);
        if ($request->filled('program_id'))     $query->where('program_id', $request->program_id);
        if ($request->filled('course_type'))    $query->where('course_type', $request->course_type);
        if ($request->filled('status'))         $query->where('status', $request->status);
        if ($request->filled('semester_level')) $query->where('semester_level', $request->semester_level);

        if ($request->boolean('all')) {
            return response()->json(['success' => true, 'data' => $query->get()->map(fn($c) => $this->formatCourse($c))]);
        }

        $courses = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success'    => true,
            'data'       => $courses->map(fn($c) => $this->formatCourse($c)),
            'pagination' => [
                'total'        => $courses->total(),
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
            ],
        ]);
    }

    public function show($id)
    {
        $course = Course::with(['department', 'program', 'faculty.user',
            'enrollments' => fn($q) => $q->with('student.user')->where('status', 'approved'),
        ])->findOrFail($id);

        self::logVisit('courses', 'view', 'visited', "Viewed course: {$course->name}", [], [], Course::class, $course->id);

        return response()->json(['success' => true, 'data' => $this->formatCourse($course, true)]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'               => 'required|string|max:255',
            'code'               => 'required|string|max:20|unique:courses,code',
            'department_id'      => 'required|exists:departments,id',
            'program_id'         => 'nullable|exists:programs,id',
            'faculty_profile_id' => 'nullable|exists:faculty_profiles,id',
            'credit_hours'       => 'nullable|integer|min:1|max:6',
            'contact_hours'      => 'nullable|integer|min:1|max:8',
            'course_type'        => 'nullable|in:theory,lab,project,thesis',
            'semester_level'     => 'nullable|string|max:20',
            'description'        => 'nullable|string',
            'objectives'         => 'nullable|string',
            'max_students'       => 'nullable|integer|min:1',
            'status'             => 'nullable|in:active,inactive,archived',
        ]);

        $course = Course::create($validated);
        self::logVisit('courses', 'create', 'created', "Created course: {$course->name}", [], $validated, Course::class, $course->id);

        return response()->json(['success' => true, 'message' => 'Course created.', 'data' => $this->formatCourse($course->load(['department', 'faculty.user']))], 201);
    }

    public function update(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validated = $request->validate([
            'name'               => 'sometimes|string|max:255',
            'code'               => 'sometimes|string|max:20|unique:courses,code,' . $id,
            'department_id'      => 'sometimes|exists:departments,id',
            'program_id'         => 'nullable|exists:programs,id',
            'faculty_profile_id' => 'nullable|exists:faculty_profiles,id',
            'credit_hours'       => 'nullable|integer|min:1|max:6',
            'contact_hours'      => 'nullable|integer|min:1|max:8',
            'course_type'        => 'nullable|in:theory,lab,project,thesis',
            'semester_level'     => 'nullable|string|max:20',
            'description'        => 'nullable|string',
            'objectives'         => 'nullable|string',
            'max_students'       => 'nullable|integer|min:1',
            'status'             => 'nullable|in:active,inactive,archived',
        ]);

        $old = $course->only(array_keys($validated));
        $course->update($validated);
        self::logVisit('courses', 'edit', 'updated', "Updated course: {$course->name}", $old, $validated, Course::class, $course->id);

        return response()->json(['success' => true, 'message' => 'Course updated.', 'data' => $this->formatCourse($course->fresh()->load(['department', 'faculty.user']))]);
    }

    public function destroy($id)
    {
        $course = Course::findOrFail($id);

        if ($course->enrollments()->count() > 0) {
            return response()->json(['success' => false, 'message' => 'Cannot delete course with enrollments.'], 422);
        }

        self::logVisit('courses', 'delete', 'deleted', "Deleted course: {$course->name}", [], [], Course::class, $course->id);
        $course->delete();

        return response()->json(['success' => true, 'message' => 'Course deleted.']);
    }

    public function stats()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'total'    => Course::count(),
                'active'   => Course::where('status', 'active')->count(),
                'theory'   => Course::where('course_type', 'theory')->count(),
                'lab'      => Course::where('course_type', 'lab')->count(),
                'by_dept'  => Course::selectRaw('department_id, count(*) as count')
                    ->with('department:id,name,code')
                    ->groupBy('department_id')
                    ->get(),
            ],
        ]);
    }

    private function formatCourse(Course $c, bool $detailed = false): array
    {
        $data = [
            'id'                 => $c->id,
            'name'               => $c->name,
            'code'               => $c->code,
            'credit_hours'       => $c->credit_hours,
            'contact_hours'      => $c->contact_hours,
            'course_type'        => $c->course_type,
            'semester_level'     => $c->semester_level,
            'status'             => $c->status,
            'max_students'       => $c->max_students,
            'enrolled_count'     => $c->enrolled_count ?? 0,
            'department'         => $c->department ? ['id' => $c->department->id, 'name' => $c->department->name, 'code' => $c->department->code] : null,
            'program'            => $c->program    ? ['id' => $c->program->id,    'name' => $c->program->name,    'code' => $c->program->code]    : null,
            'faculty'            => $c->faculty?->user ? ['id' => $c->faculty->id, 'name' => $c->faculty->user->name, 'designation' => $c->faculty->designation] : null,
            'faculty_profile_id' => $c->faculty_profile_id,
        ];

        if ($detailed) {
            $data['description'] = $c->description;
            $data['objectives']  = $c->objectives;
            $data['syllabus']    = $c->syllabus;
            $data['enrollments'] = $c->enrollments?->map(fn($e) => [
                'id'         => $e->id,
                'student_id' => $e->student?->student_id,
                'name'       => $e->student?->user?->name,
                'section'    => $e->section,
                'grade'      => $e->grade,
            ]);
        }

        return $data;
    }
}
