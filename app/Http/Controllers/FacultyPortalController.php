<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseGrade;
use App\Models\Enrollment;
use App\Models\FacultyProfile;
use App\Models\TimetableSlot;
use App\Support\AcademicTerm;
use Illuminate\Support\Collection;

/**
 * Self-service endpoints for the signed-in faculty member: the courses they
 * teach this term and their schedule. Taking attendance and grading reuse the
 * existing endpoints, which are guarded by the teach-course gate.
 */
class FacultyPortalController extends Controller
{
    private const DAYS = ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

    // GET /api/me/faculty/overview
    public function overview()
    {
        $faculty = $this->faculty()->load(['user', 'department']);
        $courses = $this->courseSummaries($faculty);
        $today   = now()->format('l');

        return response()->json(['success' => true, 'data' => [
            'profile' => [
                'name'        => $faculty->user?->name,
                'employee_id' => $faculty->employee_id,
                'designation' => $faculty->designation,
                'department'  => $faculty->department?->name,
            ],
            'term'               => AcademicTerm::current(),
            'courses_count'      => $courses->count(),
            'students_count'     => $courses->sum('students'),
            'sessions_held'      => $courses->sum('sessions_held'),
            'attendance_percent' => $this->average($courses->pluck('attendance_percent')),
            'grading_pending'    => $courses->where('grades_published', false)->count(),
            'today'              => $today,
            'today_classes'      => $this->slots($faculty)->where('day', $today)->values(),
            'courses'            => $courses,
        ]]);
    }

    // GET /api/me/faculty/courses
    public function courses()
    {
        return response()->json([
            'success' => true,
            'term'    => AcademicTerm::current(),
            'data'    => $this->courseSummaries($this->faculty()),
        ]);
    }

    // GET /api/me/faculty/timetable
    public function timetable()
    {
        return response()->json([
            'success' => true,
            'term'    => AcademicTerm::current(),
            'data'    => $this->slots($this->faculty()),
        ]);
    }

    private function faculty(): FacultyProfile
    {
        $faculty = auth()->user()->facultyProfile;
        abort_unless($faculty, 404, 'No faculty profile is linked to this account.');

        return $faculty;
    }

    private function courseSummaries(FacultyProfile $faculty): Collection
    {
        $term    = AcademicTerm::current();
        $courses = Course::where('faculty_profile_id', $faculty->id)->orderBy('code')->get();
        $ids     = $courses->pluck('id');

        $inTerm = fn ($q) => $q->whereIn('course_id', $ids)
            ->where('semester', $term['semester'])
            ->where('academic_year', $term['academic_year']);

        // Publishing grades moves enrollments to "completed"; they still belong to this term
        $students = Enrollment::whereIn('status', ['approved', 'completed'])->where($inTerm)->get()->groupBy('course_id');
        $sessions = AttendanceSession::where($inTerm)->get()->groupBy('course_id');
        $grades   = CourseGrade::where($inTerm)->get()->groupBy('course_id');
        $records  = AttendanceRecord::whereIn('attendance_session_id', $sessions->flatten()->pluck('id'))
            ->with('session:id,course_id')
            ->get()
            ->groupBy(fn ($r) => $r->session->course_id);

        return $courses->map(function ($course) use ($students, $sessions, $grades, $records) {
            $rows         = $records->get($course->id, collect());
            $courseGrades = $grades->get($course->id, collect());
            $enrolled     = $students->get($course->id, collect())->count();

            return [
                'id'                 => $course->id,
                'code'               => $course->code,
                'name'               => $course->name,
                'credits'            => (int) $course->credit_hours,
                'type'               => $course->course_type,
                'students'           => $enrolled,
                'sessions_held'      => $sessions->get($course->id, collect())->count(),
                'attendance_percent' => $rows->count()
                    ? (int) round($rows->whereIn('status', ['present', 'late'])->count() / $rows->count() * 100)
                    : null,
                'grades_entered'     => $courseGrades->whereNotNull('total_marks')->count(),
                'grades_published'   => $enrolled > 0 && $courseGrades->isNotEmpty() && $courseGrades->every(fn ($g) => $g->is_published),
            ];
        })->values();
    }

    private function slots(FacultyProfile $faculty): Collection
    {
        $term = AcademicTerm::current();

        return TimetableSlot::with('course')
            ->where('semester', $term['semester'])
            ->where('academic_year', $term['academic_year'])
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('faculty_profile_id', $faculty->id)
                ->orWhereHas('course', fn ($c) => $c->where('faculty_profile_id', $faculty->id)))
            ->get()
            ->sortBy(fn ($s) => [array_search($s->day_of_week, self::DAYS), $s->start_time])
            ->map(fn ($s) => [
                'day'        => $s->day_of_week,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time'   => substr($s->end_time, 0, 5),
                'room'       => $s->room,
                'building'   => $s->building,
                'type'       => $s->slot_type,
                'section'    => $s->section,
                'course'     => ['id' => $s->course?->id, 'code' => $s->course?->code, 'name' => $s->course?->name],
            ])
            ->values();
    }

    private function average(Collection $values): ?int
    {
        $values = $values->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : (int) round($values->avg());
    }
}
