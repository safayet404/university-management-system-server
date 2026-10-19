<?php

namespace Tests\Feature;

use App\Mail\DirectMessage;
use App\Models\Department;
use App\Models\Program;
use App\Models\SentEmail;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Mail::fake();
        $this->admin = User::factory()->create(['name' => 'Office Admin', 'email' => 'office@school.edu'])->assignRole('admin');
        Sanctum::actingAs($this->admin);
    }

    private function email(array $extra = [])
    {
        return $this->postJson('/api/communications/email', array_merge([
            'to' => 'guest.speaker@example.com', 'recipient_name' => 'Dr. Guest',
            'subject' => 'Invitation to speak', 'message' => "Hello,\nWould you join our seminar?",
        ], $extra));
    }

    public function test_sends_to_any_address_with_replies_going_to_the_sender(): void
    {
        $this->email()->assertCreated()->assertJsonPath('remaining', 19);

        Mail::assertSent(DirectMessage::class, fn ($mail) => $mail->hasTo('guest.speaker@example.com')
            && $mail->hasReplyTo('office@school.edu')
            && $mail->subjectLine === 'Invitation to speak');

        $this->assertDatabaseHas('sent_emails', ['sender_id' => $this->admin->id, 'recipient_email' => 'guest.speaker@example.com', 'status' => 'sent']);
    }

    public function test_message_text_is_escaped_in_the_email(): void
    {
        $mail = new DirectMessage($this->admin, 'Parent', 'Hi', '<script>alert(1)</script> line one');
        $html = $mail->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Office Admin', $html);
    }

    public function test_guardians_can_be_found_by_the_students_name(): void
    {
        $student = User::factory()->create(['name' => 'Rafi Hasan']);
        StudentProfile::create(['user_id' => $student->id, 'student_id' => 'S-1', 'guardian_name' => 'Karim Hasan', 'guardian_email' => 'karim@example.com']);

        $this->getJson('/api/communications/recipients?q=Rafi')
            ->assertOk()
            ->assertJsonFragment(['type' => 'guardian', 'name' => 'Karim Hasan', 'email' => 'karim@example.com']);
    }

    public function test_students_cannot_send_emails(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('student'));

        $this->email()->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_each_sender_has_a_daily_cap(): void
    {
        config(['app.mail_daily_limit_per_user' => 2]);

        $this->email()->assertCreated();
        $this->email()->assertCreated();
        $this->email()->assertStatus(429);

        Mail::assertSentCount(2);
    }

    public function test_institution_can_restrict_recipient_domains(): void
    {
        config(['app.mail_allowed_domains' => ['school.edu']]);

        $this->email(['to' => 'parent@gmail.com'])->assertUnprocessable();
        $this->email(['to' => 'teacher@school.edu'])->assertCreated();
    }

    public function test_a_delivery_failure_is_reported_and_logged(): void
    {
        Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));

        $this->email()->assertStatus(502)->assertJson(['message' => 'The email could not be sent. Please try again later.']);
        $this->assertDatabaseHas('sent_emails', ['status' => 'failed']);
    }

    public function test_senders_see_their_own_log_and_admins_see_everyone(): void
    {
        $this->email();
        $officer = User::factory()->create()->assignRole('admission-officer');
        Sanctum::actingAs($officer);
        $this->email(['to' => 'parent@example.com']);

        $this->getJson('/api/communications/email')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.to', 'parent@example.com');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/communications/email')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_new_students_and_faculty_get_personal_temporary_passwords(): void
    {
        $dept    = Department::create(['name' => 'Computer Science', 'code' => 'CSE']);
        $program = Program::create(['department_id' => $dept->id, 'name' => 'BSc CSE', 'code' => 'BSCSE', 'degree_type' => 'bachelor']);

        $student = $this->postJson('/api/students', [
            'name' => 'New Student', 'email' => 'new.student@example.com', 'student_id' => '261-0099-CSE',
            'department_id' => $dept->id, 'program_id' => $program->id, 'guardian_email' => 'guardian@example.com',
        ])->assertCreated();
        $faculty = $this->postJson('/api/faculty', [
            'name' => 'New Teacher', 'email' => 'new.teacher@example.com', 'employee_id' => 'F-99',
            'department_id' => $dept->id, 'designation' => 'Lecturer',
        ])->assertCreated();

        foreach (['new.student@example.com' => $student, 'new.teacher@example.com' => $faculty] as $email => $response) {
            $user = User::where('email', $email)->first();
            $this->assertTrue($user->must_change_password);
            $this->assertNotEmpty($response->json('credentials.temporary_password'));
            $this->assertTrue(\Illuminate\Support\Facades\Hash::check($response->json('credentials.temporary_password'), $user->password));
        }
        $this->assertSame('guardian@example.com', StudentProfile::where('student_id', '261-0099-CSE')->value('guardian_email'));
    }

    public function test_profile_updates_only_change_validated_fields(): void
    {
        $owner    = User::factory()->create();
        $intruder = User::factory()->create();
        $profile  = StudentProfile::create(['user_id' => $owner->id, 'student_id' => 'S-OWN']);

        $this->putJson("/api/students/{$profile->id}", ['user_id' => $intruder->id, 'student_id' => 'HACKED', 'guardian_email' => 'g@example.com'])
            ->assertOk();

        $profile->refresh();
        $this->assertSame($owner->id, $profile->user_id);
        $this->assertSame('S-OWN', $profile->student_id);
        $this->assertSame('g@example.com', $profile->guardian_email);

        $teacher = \App\Models\FacultyProfile::create(['user_id' => $owner->id, 'employee_id' => 'F-OWN', 'designation' => 'Lecturer']);
        $this->putJson("/api/faculty/{$teacher->id}", ['user_id' => $intruder->id, 'employee_id' => 'HACKED', 'designation' => 'Professor'])
            ->assertOk();

        $teacher->refresh();
        $this->assertSame($owner->id, $teacher->user_id);
        $this->assertSame('F-OWN', $teacher->employee_id);
        $this->assertSame('Professor', $teacher->designation);
    }
}
