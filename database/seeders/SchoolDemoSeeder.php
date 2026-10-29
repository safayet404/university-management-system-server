<?php

namespace Database\Seeders;

use App\Models\ClassLevel;
use App\Models\DailyAttendance;
use App\Models\DailyAttendanceRecord;
use App\Models\ExamMark;
use App\Models\ExamRemark;
use App\Models\FacultyProfile;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\GroupMember;
use App\Models\GroupSubject;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TermExam;
use App\Models\User;
use App\Support\Institution;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Demo data for school and coaching deployments: sections or batches with
 * teachers and students, two months of daily attendance, a published exam
 * plus one still being marked, and monthly fees with payments. The demo
 * student and teacher accounts are placed in it. Runs once (skips when
 * sections or batches already exist):
 *   php artisan db:seed --class=SchoolDemoSeeder --force
 */
class SchoolDemoSeeder extends Seeder
{
    private const REMARKS = [
        'Excellent work. Keep it up!', 'Very good. Can do even better in Mathematics.', 'Regular and attentive in class.',
        'Needs to practise writing more.', 'Good progress this term.', 'Should be more attentive in class.',
    ];

    private Collection $teachers;
    private User $admin;

    public function run(): void
    {
        if (Institution::isUniversity()) {
            return;
        }
        if (StudentGroup::withTrashed()->exists()) {
            $this->command?->warn('SchoolDemoSeeder skipped: sections or batches already exist.');
            return;
        }

        $demoTeacher = User::where('email', 'faculty@unicore.edu')->first()?->facultyProfile;
        $demoStudent = User::where('email', 'student@unicore.edu')->first()?->studentProfile;
        $this->admin = User::role('admin')->first() ?? User::first();
        $this->teachers = FacultyProfile::where('id', '!=', $demoTeacher?->id)->orderBy('id')->get();
        $students = StudentProfile::where('id', '!=', $demoStudent?->id)->orderBy('id')->get();

        if (! $demoTeacher || ! $demoStudent || $this->teachers->count() < 5 || $students->count() < 20) {
            $this->command?->warn('SchoolDemoSeeder skipped: run the base seeders first.');
            return;
        }

        mt_srand(2026);
        $groups = Institution::isSchool()
            ? $this->schoolGroups($demoTeacher, $demoStudent, $students)
            : $this->coachingBatches($demoTeacher, $demoStudent, $students);

        foreach ($groups as $group) {
            $this->attendance($group);
            $this->fees($group);
        }
        $this->exams($groups);

        $this->command?->info('✅ SchoolDemoSeeder: ' . count($groups) . ' ' . (Institution::isSchool() ? 'sections' : 'batches') . ' with attendance, exams and fees');
    }

    /** @return StudentGroup[] */
    private function schoolGroups(FacultyProfile $demoTeacher, StudentProfile $demoStudent, Collection $students): array
    {
        $year  = (string) now()->year;
        $plan  = [
            ['C6', 'A', null, $demoTeacher->id, 1200, 10],
            ['C6', 'B', null, $this->teachers[0]->id, 1200, 8],
            ['C9', 'A', 'science', $this->teachers[1]->id, 1500, 9],
        ];
        $pool   = $students->values();
        $groups = [];

        foreach ($plan as $i => [$code, $name, $stream, $classTeacher, $fee, $size]) {
            $level = ClassLevel::where('code', $code)->first();
            $group = StudentGroup::create([
                'kind' => 'section', 'name' => $name, 'class_level_id' => $level->id, 'stream' => $stream, 'academic_year' => $year,
                'shift' => 'morning', 'class_teacher_id' => $classTeacher, 'capacity' => 40, 'monthly_fee' => $fee,
            ]);

            $subjects = Subject::where('class_level_id', $level->id)
                ->where(fn ($q) => $q->whereNull('stream')->orWhere('stream', $stream))
                ->orderBy('sort_order')->get();
            foreach ($subjects->values() as $j => $subject) {
                $teacher = Str::contains($subject->name, 'Mathematics') && ! Str::contains($subject->name, 'Higher')
                    ? $demoTeacher->id
                    : $this->teachers[($i * 3 + $j) % $this->teachers->count()]->id;
                GroupSubject::create(['student_group_id' => $group->id, 'subject_id' => $subject->id, 'faculty_profile_id' => $teacher]);
            }

            $members = $pool->splice(0, $size);
            if ($i === 0) {
                $members->prepend($demoStudent);
            }
            $optional = $subjects->where('is_optional', true)->values();
            foreach ($members->values() as $k => $student) {
                GroupMember::create([
                    'student_group_id' => $group->id, 'student_profile_id' => $student->id, 'roll_number' => $k + 1, 'status' => 'active',
                    'joined_on' => now()->startOfYear()->toDateString(),
                    'optional_subject_id' => $optional->isNotEmpty() ? $optional[$k % $optional->count()]->id : null,
                    'monthly_discount' => $k === 3 ? 300 : 0, 'discount_note' => $k === 3 ? 'Sibling discount' : null,
                ]);
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /** @return StudentGroup[] */
    private function coachingBatches(FacultyProfile $demoTeacher, StudentProfile $demoStudent, Collection $students): array
    {
        $year = (string) now()->year;
        $subject = fn (string $name, string $code) => Subject::firstOrCreate(['code' => $code], ['name' => $name, 'full_marks' => 100, 'pass_marks' => 40]);
        $plan = [
            ["HSC {$year} Physics – Evening", [$subject('Physics', 'COA-PHY')], $demoTeacher->id, 1800, 'Sat, Mon, Wed · 5–7 pm', 12],
            ["SSC {$year} Math & Science – Morning", [$subject('General Mathematics', 'COA-MAT'), $subject('General Science', 'COA-SCI')], $this->teachers[0]->id, 1500, 'Sun, Tue, Thu · 7–9 am', 10],
            ['Engineering Admission Prep', [$subject('Physics', 'COA-PHY'), $subject('Chemistry', 'COA-CHE'), $subject('Higher Mathematics', 'COA-HMA')], $this->teachers[1]->id, 3000, 'Every day · 3–6 pm', 10],
        ];

        $pool   = $students->values();
        $groups = [];
        foreach ($plan as $i => [$name, $subjects, $lead, $fee, $schedule, $size]) {
            $group = StudentGroup::create([
                'kind' => 'batch', 'name' => $name, 'academic_year' => $year, 'class_teacher_id' => $lead, 'capacity' => 30,
                'monthly_fee' => $fee, 'schedule_note' => $schedule, 'starts_on' => now()->startOfYear()->toDateString(),
                'ends_on' => now()->endOfYear()->toDateString(),
            ]);
            foreach ($subjects as $j => $s) {
                GroupSubject::create(['student_group_id' => $group->id, 'subject_id' => $s->id,
                    'faculty_profile_id' => $j === 0 ? $lead : $this->teachers[($i + $j + 2) % $this->teachers->count()]->id]);
            }

            // Students may join several batches; the demo student joins two
            $members = $pool->splice(0, $size);
            if ($i < 2) {
                $members->prepend($demoStudent);
            }
            foreach ($members->values() as $k => $student) {
                GroupMember::create([
                    'student_group_id' => $group->id, 'student_profile_id' => $student->id, 'roll_number' => $k + 1, 'status' => 'active',
                    'joined_on' => now()->startOfYear()->toDateString(),
                    'monthly_discount' => $k === 2 ? 500 : 0, 'discount_note' => $k === 2 ? 'Scholarship' : null,
                ]);
            }
            $groups[] = $group;
        }

        return $groups;
    }

    /** Daily attendance from the start of last month to today (no Fridays), most students present. */
    private function attendance(StudentGroup $group): void
    {
        $members = $group->activeMembers()->pluck('student_profile_id');
        $takenBy = $group->classTeacher?->user_id ?? $this->admin->id;

        for ($day = now()->subMonthNoOverflow()->startOfMonth(); $day->lte(today()); $day->addDay()) {
            if ($day->isFriday()) {
                continue;
            }
            $attendance = DailyAttendance::create(['student_group_id' => $group->id, 'date' => $day->toDateString(), 'taken_by' => $takenBy]);
            foreach ($members as $id) {
                $roll = mt_rand(1, 100);
                $status = $roll <= 86 ? 'present' : ($roll <= 92 ? 'late' : ($roll <= 98 ? 'absent' : 'leave'));
                DailyAttendanceRecord::create(['daily_attendance_id' => $attendance->id, 'student_profile_id' => $id, 'status' => $status,
                    'remarks' => $status === 'leave' ? 'Sick' : null]);
            }
        }
    }

    /** Last month (mostly paid) and this month (some paid). */
    private function fees(StudentGroup $group): void
    {
        foreach ([now()->subMonthNoOverflow(), now()] as $n => $month) {
            $key = $month->format('Y-m');
            foreach ($group->activeMembers()->get() as $k => $m) {
                $discount = min((float) $group->monthly_fee, (float) $m->monthly_discount);
                $invoice = FeeInvoice::create([
                    'invoice_number' => 'INV-' . strtoupper(Str::random(8)), 'student_profile_id' => $m->student_profile_id,
                    'student_group_id' => $group->id, 'billing_month' => $key, 'fee_type' => 'tuition',
                    'description' => 'Monthly fee – ' . $month->format('F Y'), 'amount' => $group->monthly_fee, 'discount' => $discount,
                    'semester' => $month->format('F Y'), 'academic_year' => $group->academic_year,
                    'due_date' => $month->copy()->startOfMonth()->addDays(9)->toDateString(), 'status' => 'unpaid',
                    'remarks' => $discount > 0 ? $m->discount_note : null,
                ]);

                $pays = $n === 0 ? $k % 6 !== 5 : $k % 2 === 0;   // last month ~85% paid, this month half
                if ($pays) {
                    FeePayment::create([
                        'fee_invoice_id' => $invoice->id, 'student_profile_id' => $m->student_profile_id, 'collected_by' => $this->admin->id,
                        'transaction_id' => 'TXN-' . strtoupper(Str::random(10)), 'amount' => $invoice->net_amount,
                        'payment_method' => ['cash', 'bkash', 'nagad'][$k % 3],
                        'payment_date' => $month->copy()->startOfMonth()->addDays(2 + $k % 6)->toDateString(),
                    ]);
                    $invoice->paid_amount = $invoice->net_amount;
                }
                $invoice->updateStatus();
            }
        }
    }

    /** A published exam with full marks and remarks, and one still being marked. */
    private function exams(array $groups): void
    {
        $year = (string) now()->year;
        $exams = [];
        if (Institution::isSchool()) {
            foreach (collect($groups)->groupBy('class_level_id') as $levelId => $sections) {
                $exams[] = [TermExam::create(['name' => 'Half Yearly Exam', 'kind' => 'half_yearly', 'academic_year' => $year, 'class_level_id' => $levelId,
                    'starts_on' => now()->subMonths(4)->startOfMonth()->toDateString(), 'ends_on' => now()->subMonths(4)->startOfMonth()->addDays(10)->toDateString(),
                    'status' => 'published', 'published_at' => now()->subMonths(3), 'created_by' => $this->admin->id]), $sections, true];
                $exams[] = [TermExam::create(['name' => 'Annual Exam', 'kind' => 'annual', 'academic_year' => $year, 'class_level_id' => $levelId,
                    'starts_on' => now()->subDays(10)->toDateString(), 'ends_on' => now()->subDays(1)->toDateString(),
                    'status' => 'draft', 'created_by' => $this->admin->id]), $sections, false];
            }
        } else {
            foreach ($groups as $batch) {
                foreach ([1, 2] as $n) {
                    $exams[] = [TermExam::create(['name' => "Model Test {$n}", 'kind' => 'model_test', 'academic_year' => $year, 'student_group_id' => $batch->id,
                        'full_marks' => 50, 'starts_on' => now()->subWeeks(6 - 3 * $n)->toDateString(),
                        'status' => $n === 1 ? 'published' : 'draft', 'published_at' => $n === 1 ? now()->subWeeks(2) : null,
                        'created_by' => $this->admin->id]), collect([$batch]), $n === 1];
                }
            }
        }

        foreach ($exams as [$exam, $sections, $complete]) {
            foreach ($sections as $group) {
                $group->load('groupSubjects.subject');
                foreach ($group->activeMembers()->get() as $k => $m) {
                    $ability = mt_rand(45, 95);   // each student is consistently strong or weak
                    foreach ($group->groupSubjects as $j => $gs) {
                        $subject = $gs->subject;
                        if ($subject->is_optional && (int) $m->optional_subject_id !== (int) $subject->id) {
                            continue;
                        }
                        // The exam still being marked: only the first subjects are in
                        if (! $complete && $j > 2) {
                            continue;
                        }
                        [$full] = $exam->marksFor($subject);
                        $absent = mt_rand(1, 100) === 1;
                        $marks = min($full, max(0, round(($ability + mt_rand(-15, 12)) / 100 * $full)));
                        ExamMark::create(['term_exam_id' => $exam->id, 'student_profile_id' => $m->student_profile_id, 'subject_id' => $subject->id,
                            'marks' => $absent ? null : $marks, 'is_absent' => $absent, 'entered_by' => $gs->teacher?->user_id ?? $this->admin->id]);
                    }
                    if ($complete) {
                        ExamRemark::create(['term_exam_id' => $exam->id, 'student_profile_id' => $m->student_profile_id,
                            'remarks' => self::REMARKS[$k % count(self::REMARKS)], 'entered_by' => $group->classTeacher?->user_id ?? $this->admin->id]);
                    }
                }
            }
        }
    }
}
