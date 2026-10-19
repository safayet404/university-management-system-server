<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseGrade;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\FacultyProfile;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\Program;
use App\Models\StudentProfile;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\AcademicTerm;
use App\Support\GradeReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Gives the demo student and faculty accounts a complete, realistic record so
 * the read-only demo shows full portals. Safe to run repeatedly:
 *   php artisan db:seed --class=DemoAccountsSeeder --force
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $studentUser = User::where('email', 'student@unicore.edu')->first();
        $facultyUser = User::where('email', 'faculty@unicore.edu')->first();
        $department  = Department::where('code', 'CSE')->first() ?? Department::first();

        if (! $studentUser || ! $facultyUser || ! $department) {
            $this->command?->warn('DemoAccountsSeeder skipped: run the base seeders first.');
            return;
        }

        $term    = AcademicTerm::current();
        $program = Program::where('department_id', $department->id)->first();
        $courses = Course::where('status', 'active')
            ->orderByRaw('CASE WHEN department_id = ? THEN 0 ELSE 1 END', [$department->id])
            ->orderBy('id')
            ->take(12)
            ->get();

        if ($courses->count() < 4) {
            $this->command?->warn('DemoAccountsSeeder skipped: at least 4 active courses are needed.');
            return;
        }

        $faculty = FacultyProfile::updateOrCreate(['user_id' => $facultyUser->id], [
            'department_id'   => $department->id,
            'employee_id'     => 'FAC-DEMO-01',
            'designation'     => 'Assistant Professor',
            'employment_type' => 'full_time',
            'specialization'  => 'Distributed Systems',
            'joining_date'    => '2019-01-15',
        ]);

        $student = StudentProfile::updateOrCreate(['user_id' => $studentUser->id], [
            'department_id'          => $department->id,
            'program_id'             => $program?->id,
            'student_id'             => '221-0001-001',
            'batch'                  => '2022',
            'semester'               => '6th',
            'section'                => 'A',
            'shift'                  => 'morning',
            'admission_type'         => 'regular',
            'admission_date'         => '2022-01-10',
            'academic_status'        => 'regular',
            'total_credits_required' => 144,
        ]);

        // Two past terms with published results, then the current term
        $current = $courses->take(4)->values();
        $terms = [
            ['semester' => 'Spring 2024', 'academic_year' => '2023-2024', 'courses' => $courses->slice(8, 4)->values(), 'marks' => [78, 84, 71, 66]],
            ['semester' => 'Fall 2024', 'academic_year' => '2024-2025', 'courses' => $courses->slice(4, 4)->values(), 'marks' => [82, 69, 88, 74]],
        ];

        foreach ($terms as $past) {
            foreach ($past['courses'] as $i => $course) {
                $enrollment = $this->enroll($student, $course, $past['semester'], $past['academic_year'], 'completed');
                $grade      = ExamResult::calculateGrade($past['marks'][$i], 100);

                CourseGrade::updateOrCreate(
                    ['enrollment_id' => $enrollment->id, 'student_profile_id' => $student->id],
                    [
                        'course_id'     => $course->id,
                        'semester'      => $past['semester'],
                        'academic_year' => $past['academic_year'],
                        'total_marks'   => $past['marks'][$i],
                        'grade_point'   => $grade['point'],
                        'grade_letter'  => $grade['letter'],
                        'is_published'  => true,
                        'published_at'  => now()->subMonths(3),
                    ]
                );
            }
        }

        // Current term: the demo faculty teaches two of the four courses
        foreach ($current as $i => $course) {
            if ($i < 2) {
                $course->update(['faculty_profile_id' => $faculty->id]);
            }
            $this->enroll($student, $course, $term['semester'], $term['academic_year'], 'approved');
        }

        $this->seedAttendance($student, $facultyUser, $current, $term);
        $this->seedClassmates($student, $current->take(2), $department, $term);
        $this->seedTimetable($current, $faculty, $department, $term);
        $this->seedFees($student, $term);

        $student->update(['cgpa' => GradeReport::for($student)['cgpa'] ?? 0]);

        $this->command?->info('✅ Demo student and faculty accounts populated');
    }

    private function enroll(StudentProfile $student, Course $course, string $semester, string $year, string $status): Enrollment
    {
        return Enrollment::withTrashed()->updateOrCreate(
            ['student_profile_id' => $student->id, 'course_id' => $course->id, 'semester' => $semester, 'academic_year' => $year],
            ['status' => $status, 'section' => 'A', 'deleted_at' => null, 'approved_at' => now()->subMonths(2)]
        );
    }

    private function seedAttendance(StudentProfile $student, User $taker, $courses, array $term): void
    {
        // Eight recent class days per course, from a perfect record down to one
        // course below the usual 75% requirement so the portal's warning shows
        $patterns = [
            ['present', 'present', 'late', 'present', 'present', 'present', 'present', 'present'],
            ['present', 'absent', 'present', 'present', 'late', 'present', 'present', 'present'],
            ['present', 'present', 'absent', 'present', 'present', 'excused', 'present', 'absent'],
            ['absent', 'present', 'late', 'absent', 'present', 'absent', 'present', 'present'],
        ];

        foreach ($courses as $c => $course) {
            for ($i = 0; $i < 8; $i++) {
                $date = Carbon::today()->subWeekdays(($i * 3) + $c + 1)->toDateString();

                $session = AttendanceSession::firstOrCreate(
                    ['course_id' => $course->id, 'date' => $date, 'section' => 'A', 'semester' => $term['semester'], 'academic_year' => $term['academic_year']],
                    ['faculty_profile_id' => $course->faculty_profile_id, 'taken_by' => $taker->id, 'topic' => "Lecture " . (8 - $i), 'is_finalized' => true]
                );

                AttendanceRecord::updateOrCreate(
                    ['attendance_session_id' => $session->id, 'student_profile_id' => $student->id],
                    ['status' => $patterns[$c % count($patterns)][$i]]
                );
            }
        }
    }

    /**
     * Fill the demo faculty's two classes with classmates, their attendance and,
     * for the first class, unpublished marks — so the faculty portal shows a
     * real roster and a course that is ready to publish.
     */
    private function seedClassmates(StudentProfile $demo, $courses, Department $department, array $term): void
    {
        // Same-department students first, then others taking it as an elective
        $roster = StudentProfile::where('id', '!=', $demo->id)
            ->orderByRaw('CASE WHEN department_id = ? THEN 0 ELSE 1 END', [$department->id])
            ->orderBy('id')
            ->take(12)
            ->get()
            ->prepend($demo);

        $statuses = ['present', 'present', 'present', 'late', 'present', 'absent', 'present', 'present', 'excused', 'present'];

        foreach ($courses as $c => $course) {
            $sessions = AttendanceSession::where('course_id', $course->id)
                ->where('semester', $term['semester'])
                ->where('academic_year', $term['academic_year'])
                ->orderBy('date')
                ->get();

            foreach ($roster as $n => $classmate) {
                $enrollment = $classmate->is($demo)
                    ? Enrollment::where(['student_profile_id' => $demo->id, 'course_id' => $course->id, 'semester' => $term['semester'], 'academic_year' => $term['academic_year']])->first()
                    : $this->enroll($classmate, $course, $term['semester'], $term['academic_year'], 'approved');

                if (! $classmate->is($demo)) {
                    foreach ($sessions as $i => $session) {
                        AttendanceRecord::updateOrCreate(
                            ['attendance_session_id' => $session->id, 'student_profile_id' => $classmate->id],
                            ['status' => $statuses[($n * 3 + $i + $c) % count($statuses)]]
                        );
                    }
                }

                if ($c === 0 && $enrollment) {
                    $marks = 52 + (($n * 17 + 11) % 44);
                    $grade = ExamResult::calculateGrade($marks, 100);
                    CourseGrade::updateOrCreate(
                        ['enrollment_id' => $enrollment->id, 'student_profile_id' => $classmate->id],
                        ['course_id' => $course->id, 'semester' => $term['semester'], 'academic_year' => $term['academic_year'],
                         'total_marks' => $marks, 'grade_point' => $grade['point'], 'grade_letter' => $grade['letter'], 'is_published' => false]
                    );
                }
            }
        }
    }

    private function seedTimetable($courses, FacultyProfile $faculty, Department $department, array $term): void
    {
        $slots = [
            ['Sunday', '09:00', '10:30'], ['Tuesday', '09:00', '10:30'],
            ['Sunday', '11:00', '12:30'], ['Tuesday', '11:00', '12:30'],
            ['Monday', '10:00', '11:30'], ['Wednesday', '10:00', '11:30'],
            ['Monday', '14:00', '15:30'], ['Wednesday', '14:00', '15:30'],
        ];

        foreach ($courses as $c => $course) {
            foreach ([0, 1] as $k) {
                [$day, $start, $end] = $slots[$c * 2 + $k];

                TimetableSlot::updateOrCreate(
                    ['course_id' => $course->id, 'day_of_week' => $day, 'semester' => $term['semester'], 'academic_year' => $term['academic_year'], 'section' => 'A'],
                    [
                        'faculty_profile_id' => $course->faculty_profile_id,
                        'department_id'      => $course->department_id ?? $department->id,
                        'start_time'         => $start,
                        'end_time'           => $end,
                        'room'               => 'DEMO-' . (301 + $c),
                        'building'           => 'Academic Building 1',
                        'slot_type'          => $course->course_type === 'lab' ? 'lab' : 'lecture',
                        'is_active'          => true,
                    ]
                );
            }
        }
    }

    private function seedFees(StudentProfile $student, array $term): void
    {
        $invoices = [
            ['INV-DEMO-0001', 'tuition', 'Tuition Fee — Fall 2024', 45000, 'Fall 2024', '2024-2025', now()->subMonths(4), 45000],
            ['INV-DEMO-0002', 'tuition', "Tuition Fee — {$term['semester']}", 45000, $term['semester'], $term['academic_year'], now()->addDays(20), 25000],
            ['INV-DEMO-0003', 'lab', "Lab Fee — {$term['semester']}", 6000, $term['semester'], $term['academic_year'], now()->addDays(10), 0],
        ];

        foreach ($invoices as [$number, $type, $description, $amount, $semester, $year, $due, $paid]) {
            $invoice = FeeInvoice::updateOrCreate(['invoice_number' => $number], [
                'student_profile_id' => $student->id,
                'fee_type'           => $type,
                'description'        => $description,
                'amount'             => $amount,
                'paid_amount'        => $paid,
                'semester'           => $semester,
                'academic_year'      => $year,
                'due_date'           => $due->toDateString(),
            ]);
            $invoice->updateStatus();

            if ($paid > 0 && ! $invoice->payments()->exists()) {
                FeePayment::create([
                    'fee_invoice_id'     => $invoice->id,
                    'student_profile_id' => $student->id,
                    'collected_by'       => User::role('accountant')->value('id') ?? User::role('admin')->value('id'),
                    'transaction_id'     => 'TXN-' . $number,
                    'amount'             => $paid,
                    'payment_method'     => 'bkash',
                    // Two weeks before the due date, but never in the future
                    'payment_date'       => $due->copy()->subDays(15)->min(now())->toDateString(),
                ]);
            }
        }
    }
}
