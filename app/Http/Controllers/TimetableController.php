<?php

namespace App\Http\Controllers;

use App\Models\TimetableSlot;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;

class TimetableController extends Controller
{
    use LogsPageVisit;

    // GET /api/timetable — weekly grid data
    public function index(Request $request)
    {
        self::logVisit('timetable', 'list', 'visited', 'Visited timetable');

        $query = TimetableSlot::with(['course', 'faculty.user', 'department'])
            ->where('is_active', true);

        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('semester'))      $query->where('semester', $request->semester);
        if ($request->filled('academic_year')) $query->where('academic_year', $request->academic_year);
        if ($request->filled('section'))       $query->where('section', $request->section);
        if ($request->filled('faculty_id'))    $query->where('faculty_profile_id', $request->faculty_id);
        if ($request->filled('room'))          $query->where('room', $request->room);

        $slots = $query->orderBy('start_time')->get();

        // Group by day
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $grouped = [];
        foreach ($days as $day) {
            $grouped[$day] = $slots->where('day_of_week', $day)->map(fn($s) => $this->formatSlot($s))->values();
        }

        return response()->json(['success' => true, 'data' => $grouped, 'total' => $slots->count()]);
    }

    // GET /api/timetable/list — flat list with pagination
    public function list(Request $request)
    {
        $query = TimetableSlot::with(['course', 'faculty.user', 'department'])->latest();

        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('semester'))      $query->where('semester', $request->semester);
        if ($request->filled('course_id'))     $query->where('course_id', $request->course_id);

        $slots = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success'    => true,
            'data'       => $slots->map(fn($s) => $this->formatSlot($s)),
            'pagination' => ['total' => $slots->total(), 'current_page' => $slots->currentPage(), 'last_page' => $slots->lastPage()],
        ]);
    }

    // POST /api/timetable
    public function store(Request $request)
    {
        $request->validate([
            'course_id'          => 'required|exists:courses,id',
            'department_id'      => 'required|exists:departments,id',
            'day_of_week'        => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'         => 'required|date_format:H:i',
            'end_time'           => 'required|date_format:H:i|after:start_time',
            'semester'           => 'required|string',
            'academic_year'      => 'required|string',
            'room'               => 'nullable|string|max:50',
            'faculty_profile_id' => 'nullable|exists:faculty_profiles,id',
            'section'            => 'nullable|string|max:10',
            'slot_type'          => 'nullable|in:lecture,lab,tutorial',
        ]);

        // Conflict check — same room, day, overlapping time
        if ($request->room) {
            $conflict = TimetableSlot::where('day_of_week', $request->day_of_week)
                ->where('room', $request->room)
                ->where('semester', $request->semester)
                ->where('academic_year', $request->academic_year)
                ->where('is_active', true)
                ->where(fn($q) => $q
                    ->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time',   [$request->start_time, $request->end_time])
                    ->orWhere(fn($q2) => $q2->where('start_time', '<=', $request->start_time)->where('end_time', '>=', $request->end_time))
                )->first();

            if ($conflict) {
                return response()->json(['success' => false, 'message' => "Room {$request->room} is already booked on {$request->day_of_week} during this time ({$conflict->course->code})."], 422);
            }
        }

        // Faculty conflict check
        if ($request->faculty_profile_id) {
            $facultyConflict = TimetableSlot::where('day_of_week', $request->day_of_week)
                ->where('faculty_profile_id', $request->faculty_profile_id)
                ->where('semester', $request->semester)
                ->where('academic_year', $request->academic_year)
                ->where('is_active', true)
                ->where(fn($q) => $q
                    ->whereBetween('start_time', [$request->start_time, $request->end_time])
                    ->orWhereBetween('end_time',   [$request->start_time, $request->end_time])
                )->first();

            if ($facultyConflict) {
                return response()->json(['success' => false, 'message' => "Faculty already has a class on {$request->day_of_week} at this time ({$facultyConflict->course->code})."], 422);
            }
        }

        $colors = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#14b8a6'];
        $slot   = TimetableSlot::create(array_merge($request->all(), [
            'color' => $request->color ?? $colors[array_rand($colors)],
        ]));

        self::logVisit('timetable', 'create', 'created', "Created slot: {$slot->course->code} on {$slot->day_of_week}", [], [], TimetableSlot::class, $slot->id);

        return response()->json(['success' => true, 'message' => 'Slot created.', 'data' => $this->formatSlot($slot->load(['course', 'faculty.user', 'department']))], 201);
    }

    // PUT /api/timetable/{id}
    public function update(Request $request, $id)
    {
        $slot = TimetableSlot::findOrFail($id);
        $request->validate([
            'day_of_week'  => 'sometimes|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday',
            'start_time'   => 'sometimes|date_format:H:i',
            'end_time'     => 'sometimes|date_format:H:i',
            'room'         => 'nullable|string|max:50',
            'section'      => 'nullable|string|max:10',
            'slot_type'    => 'nullable|in:lecture,lab,tutorial',
        ]);
        $slot->update($request->all());
        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $this->formatSlot($slot->fresh()->load(['course', 'faculty.user', 'department']))]);
    }

    // DELETE /api/timetable/{id}
    public function destroy($id)
    {
        TimetableSlot::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Slot deleted.']);
    }

    // GET /api/timetable/rooms
    public function rooms(Request $request)
    {
        $rooms = TimetableSlot::whereNotNull('room')
            ->where('semester', $request->semester)
            ->where('academic_year', $request->academic_year)
            ->distinct()->pluck('room')->sort()->values();
        return response()->json(['success' => true, 'data' => $rooms]);
    }

    // GET /api/timetable/stats
    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total_slots'   => TimetableSlot::where('is_active', true)->count(),
            'rooms_used'    => TimetableSlot::where('is_active', true)->whereNotNull('room')->distinct('room')->count('room'),
            'courses_scheduled' => TimetableSlot::where('is_active', true)->distinct('course_id')->count('course_id'),
            'by_day'        => TimetableSlot::where('is_active', true)
                ->selectRaw('day_of_week, count(*) as count')
                ->groupBy('day_of_week')->get(),
        ]]);
    }

    private function formatSlot(TimetableSlot $s): array
    {
        return [
            'id'           => $s->id,
            'day_of_week'  => $s->day_of_week,
            'start_time'   => $s->start_time,
            'end_time'     => $s->end_time,
            'duration'     => $s->duration,
            'room'         => $s->room,
            'building'     => $s->building,
            'slot_type'    => $s->slot_type,
            'section'      => $s->section,
            'color'        => $s->color,
            'semester'     => $s->semester,
            'academic_year'=> $s->academic_year,
            'is_active'    => $s->is_active,
            'course'       => $s->course ? ['id' => $s->course->id, 'name' => $s->course->name, 'code' => $s->course->code, 'credit_hours' => $s->course->credit_hours] : null,
            'faculty'      => $s->faculty?->user ? ['id' => $s->faculty->id, 'name' => $s->faculty->user->name, 'designation' => $s->faculty->designation] : null,
            'department'   => $s->department ? ['id' => $s->department->id, 'name' => $s->department->name, 'code' => $s->department->code] : null,
        ];
    }
}
