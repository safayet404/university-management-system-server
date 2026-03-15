<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use App\Models\FacultyProfile;
use App\Models\TimetableSlot;
use Illuminate\Database\Seeder;

class TimetableSeeder extends Seeder
{
    public function run(): void
    {
        $semester  = 'Spring 2025';
        $acadYear  = '2024-2025';
        $days      = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $colors    = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#14b8a6'];

        $timeSlots = [
            ['08:00', '09:30'],
            ['09:30', '11:00'],
            ['11:00', '12:30'],
            ['13:30', '15:00'],
            ['15:00', '16:30'],
            ['16:30', '18:00'],
        ];

        $departments = Department::all();
        $count = 0;
        $roomCounter = 100;

        foreach ($departments as $dept) {
            $courses = Course::where('department_id', $dept->id)->where('status', 'active')->take(4)->get();
            $faculty = FacultyProfile::where('department_id', $dept->id)->get();

            if ($courses->isEmpty()) continue;

            $usedSlots = [];

            foreach ($courses as $idx => $course) {
                $assignedFaculty = $faculty->get($idx % max(1, $faculty->count()));
                $color = $colors[$idx % count($colors)];
                $room  = $dept->code . '-' . ($roomCounter + $idx);

                $slotCount  = $course->credit_hours >= 3 ? 2 : 1;
                $dayIndices = array_slice(range(0, count($days) - 1), $idx % count($days), $slotCount);

                foreach ($dayIndices as $di) {
                    $day      = $days[$di % count($days)];
                    $timeSlot = $timeSlots[($idx + $di) % count($timeSlots)];

                    $key = $day . '_' . $timeSlot[0];
                    if (in_array($key, $usedSlots)) continue;

                    if (TimetableSlot::where([
                        'course_id'     => $course->id,
                        'day_of_week'   => $day,
                        'semester'      => $semester,
                        'academic_year' => $acadYear,
                    ])->exists()) continue;

                    TimetableSlot::create([
                        'course_id'          => $course->id,
                        'faculty_profile_id' => $assignedFaculty?->id,
                        'department_id'      => $dept->id,
                        'semester'           => $semester,
                        'academic_year'      => $acadYear,
                        'section'            => 'A',
                        'day_of_week'        => $day,
                        'start_time'         => $timeSlot[0] . ':00',
                        'end_time'           => $timeSlot[1] . ':00',
                        'room'               => $room,
                        'building'           => 'Main Building',
                        'slot_type'          => $course->course_type === 'lab' ? 'lab' : 'lecture',
                        'color'              => $color,
                        'is_active'          => true,
                    ]);

                    $usedSlots[] = $key;
                    $count++;
                }
            }
            $roomCounter += 10;
        }

        $this->command->info("✅ TimetableSeeder: {$count} slots created");
    }
}
