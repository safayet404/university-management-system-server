<?php

namespace Tests\Feature;

use App\Models\ClassLevel;
use App\Models\DailyAttendance;
use App\Models\DailyAttendanceRecord;
use App\Models\ExamMark;
use App\Models\FacultyProfile;
use App\Models\FeeInvoice;
use App\Models\GroupMember;
use App\Models\GroupSubject;
use App\Models\StudentGroup;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TermExam;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Database\Seeders\SchoolStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SchoolPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $me;
    private StudentProfile $student;
    private StudentProfile $classmate;
    private StudentGroup $section;
    private array $subjects = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.institution_type' => 'school']);
        $this->seed(RolesPermissionsSeeder::class);
        $this->seed(SchoolStructureSeeder::class);

        $teacher = User::factory()->create(['name' => 'Rahima Khatun'])->assignRole('faculty');
        $faculty = FacultyProfile::create(['user_id' => $teacher->id, 'employee_id' => 'T-1', 'designation' => 'Teacher']);

        $level = ClassLevel::where('code', 'C6')->value('id');
        $this->section = StudentGroup::create([
            'kind' => 'section', 'name' => 'A', 'class_level_id' => $level, 'academic_year' => '2026',
            'class_teacher_id' => $faculty->id, 'monthly_fee' => 1200,
        ]);
        foreach (['Bangla' => false, 'Math' => false, 'Agri' => true] as $name => $optional) {
            $this->subjects[$name] = Subject::create(['name' => $name, 'code' => "T-{$name}", 'class_level_id' => $level, 'full_marks' => 100, 'pass_marks' => 33, 'is_optional' => $optional]);
            GroupSubject::create(['student_group_id' => $this->section->id, 'subject_id' => $this->subjects[$name]->id, 'faculty_profile_id' => $faculty->id]);
        }

        $this->me = User::factory()->create()->assignRole('student');
        $this->student   = StudentProfile::create(['user_id' => $this->me->id, 'student_id' => 'S-1']);
        $this->classmate = StudentProfile::create(['user_id' => User::factory()->create()->assignRole('student')->id, 'student_id' => 'S-2']);
        GroupMember::create(['student_group_id' => $this->section->id, 'student_profile_id' => $this->student->id, 'roll_number' => 7, 'status' => 'active',
            'optional_subject_id' => $this->subjects['Agri']->id, 'monthly_discount' => 200]);
        GroupMember::create(['student_group_id' => $this->section->id, 'student_profile_id' => $this->classmate->id, 'roll_number' => 8, 'status' => 'active']);

        Sanctum::actingAs($this->me);
    }

    private function exam(string $status, array $marks): TermExam
    {
        $exam = TermExam::create(['name' => "Exam {$status}", 'kind' => 'half_yearly', 'academic_year' => '2026', 'class_level_id' => $this->section->class_level_id,
            'status' => $status, 'published_at' => $status === 'published' ? now() : null]);
        foreach ($marks as $studentId => $bySubject) {
            foreach ($bySubject as $subject => $m) {
                ExamMark::create(['term_exam_id' => $exam->id, 'student_profile_id' => $studentId, 'subject_id' => $this->subjects[$subject]->id, 'marks' => $m]);
            }
        }

        return $exam;
    }

    private function attend(string $date, array $statuses): void
    {
        $day = DailyAttendance::create(['student_group_id' => $this->section->id, 'date' => $date, 'taken_by' => $this->me->id]);
        foreach ($statuses as $studentId => $status) {
            DailyAttendanceRecord::create(['daily_attendance_id' => $day->id, 'student_profile_id' => $studentId, 'status' => $status]);
        }
    }

    public function test_my_class_shows_section_roll_teacher_and_my_subjects(): void
    {
        $class = $this->getJson('/api/me/student/class')->assertOk()->json('data.0');

        $this->assertSame(['Class 6 – A', 7, 'Rahima Khatun', 'Agri'], [$class['display_name'], $class['roll_number'], $class['class_teacher'], $class['fourth_subject']]);
        $this->assertSame(['Bangla', 'Math', 'Agri'], array_column($class['subjects'], 'name'));
        $this->assertEquals(1000, $class['monthly_fee']); // after their discount

        // A classmate without the optional subject does not see it
        Sanctum::actingAs($this->classmate->user);
        $this->assertSame(['Bangla', 'Math'], array_column($this->getJson('/api/me/student/class')->json('data.0.subjects'), 'name'));
    }

    public function test_daily_attendance_lists_my_days_and_percentage(): void
    {
        $month = today()->format('Y-m');
        $this->attend("{$month}-01", [$this->student->id => 'present', $this->classmate->id => 'absent']);
        $this->attend("{$month}-02", [$this->student->id => 'late', $this->classmate->id => 'present']);
        $this->attend("{$month}-03", [$this->student->id => 'absent', $this->classmate->id => 'present']);
        $this->attend("{$month}-04", [$this->student->id => 'leave', $this->classmate->id => 'present']);

        $data = $this->getJson("/api/me/student/daily-attendance?month={$month}")->assertOk()->json('data');

        $this->assertSame(['present', 'late', 'absent', 'leave'], array_column($data['days'], 'status'));
        $this->assertSame(67, $data['summary']['percent']);   // 2 of 3 counted days; leave excused
    }

    public function test_only_published_results_are_shown_with_my_own_marks(): void
    {
        $this->exam('draft', [$this->student->id => ['Bangla' => 99, 'Math' => 99]]);
        $this->exam('published', [
            $this->student->id   => ['Bangla' => 85, 'Math' => 72, 'Agri' => 90],
            $this->classmate->id => ['Bangla' => 95, 'Math' => 95],
        ]);

        $results = $this->getJson('/api/me/student/exam-results')->assertOk()->json('data');

        $this->assertCount(1, $results);
        $this->assertSame('Exam published', $results[0]['exam']['name']);
        $mine = $results[0]['result'];
        $this->assertSame($this->student->id, $mine['student_profile_id']);
        $this->assertEquals([5.0, 'passed', 1, 247], [$mine['gpa'], $mine['status'], $mine['section_position'], $mine['total']]); // (5 + 4 + 3) / 2 → 5; the 4th subject's marks count in the total
    }

    public function test_report_card_downloads_only_for_my_published_exams(): void
    {
        $published = $this->exam('published', [$this->student->id => ['Bangla' => 85, 'Math' => 72, 'Agri' => 90]]);
        $draft     = $this->exam('draft', [$this->student->id => ['Bangla' => 85, 'Math' => 72]]);
        $otherClass = TermExam::create(['name' => 'C7', 'kind' => 'annual', 'academic_year' => '2026', 'class_level_id' => ClassLevel::where('code', 'C7')->value('id'), 'status' => 'published']);

        $pdf = $this->get("/api/me/student/exam-results/{$published->id}/report-card")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));

        $this->getJson("/api/me/student/exam-results/{$draft->id}/report-card")->assertNotFound();
        $this->getJson("/api/me/student/exam-results/{$otherClass->id}/report-card")->assertNotFound();
    }

    public function test_overview_brings_today_month_latest_result_and_fee_dues(): void
    {
        $this->attend(today()->toDateString(), [$this->student->id => 'late']);
        $this->exam('published', [$this->student->id => ['Bangla' => 85, 'Math' => 72, 'Agri' => 90]]);
        FeeInvoice::create(['invoice_number' => 'INV-1', 'student_profile_id' => $this->student->id, 'student_group_id' => $this->section->id, 'billing_month' => today()->format('Y-m'),
            'fee_type' => 'tuition', 'description' => 'Monthly fee', 'amount' => 1200, 'discount' => 200, 'semester' => 'x', 'academic_year' => '2026', 'due_date' => today()->addDays(5), 'status' => 'unpaid']);

        $data = $this->getJson('/api/me/student/overview-school')->assertOk()->json('data');

        $this->assertSame('late', $data['attendance']['today']);
        $this->assertSame(100, $data['attendance']['month']['percent']);
        $this->assertEquals(5.0, $data['latest_result']['result']['gpa']);
        $this->assertEquals(1000, $data['fees']['due']);
    }

    public function test_teachers_cannot_use_the_student_portal(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('faculty'));

        $this->getJson('/api/me/student/exam-results')->assertForbidden();
        $this->getJson('/api/me/student/daily-attendance')->assertForbidden();
    }
}
