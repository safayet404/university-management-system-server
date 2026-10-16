<?php

namespace Tests\Feature;

use App\Models\BookIssue;
use App\Models\LibraryBook;
use App\Models\LibraryMember;
use App\Models\User;
use Database\Seeders\RolesPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesPermissionsSeeder::class);
        Sanctum::actingAs(User::factory()->create()->assignRole('librarian'));
    }

    private function book(int $copies = 1): LibraryBook
    {
        return LibraryBook::create([
            'title' => 'Clean Code', 'author' => 'Robert Martin', 'category' => 'Software',
            'total_copies' => $copies, 'available_copies' => $copies,
        ]);
    }

    private function member(int $maxBooks = 3): LibraryMember
    {
        return LibraryMember::create([
            'user_id' => User::factory()->create()->id, 'member_id' => 'LIB-' . uniqid(),
            'max_books' => $maxBooks, 'membership_start' => now()->toDateString(),
        ]);
    }

    private function issue(LibraryBook $book, LibraryMember $member)
    {
        return $this->postJson('/api/library/issues', ['library_book_id' => $book->id, 'library_member_id' => $member->id]);
    }

    public function test_library_routes_are_registered(): void
    {
        $this->getJson('/api/library/stats')->assertOk();
        $this->getJson('/api/library/books')->assertOk();
        $this->getJson('/api/library/members')->assertOk();
        $this->getJson('/api/library/issues')->assertOk();
    }

    public function test_students_can_browse_books_but_not_manage_loans(): void
    {
        Sanctum::actingAs(User::factory()->create()->assignRole('student'));

        $this->getJson('/api/library/books')->assertOk();
        $this->getJson('/api/library/members')->assertForbidden();
        $this->postJson('/api/library/issues')->assertForbidden();
        $this->postJson('/api/library/books')->assertForbidden();
    }

    public function test_issuing_the_last_copy_blocks_further_issues(): void
    {
        $book = $this->book(1);

        $this->issue($book, $this->member())->assertCreated();
        $this->issue($book, $this->member())->assertUnprocessable()->assertJson(['message' => 'No copies available.']);

        $this->assertSame(0, $book->fresh()->available_copies);
    }

    public function test_overdue_books_count_towards_the_member_limit(): void
    {
        $member = $this->member(1);
        $this->issue($this->book(), $member)->assertCreated();
        BookIssue::query()->update(['status' => 'overdue', 'due_date' => now()->subDay()]);

        $this->issue($this->book(), $member)->assertUnprocessable();
    }

    public function test_a_book_cannot_be_returned_twice(): void
    {
        $book = $this->book(2);
        $this->issue($book, $this->member())->assertCreated();
        $issueId = BookIssue::first()->id;

        $this->patchJson("/api/library/issues/{$issueId}/return")->assertOk();
        $this->patchJson("/api/library/issues/{$issueId}/return")->assertUnprocessable();

        $this->assertSame(2, $book->fresh()->available_copies);
    }

    public function test_user_search_for_new_members_hides_existing_members_and_private_fields(): void
    {
        $existing = $this->member();
        $candidate = User::factory()->create(['name' => 'Zara Candidate']);
        $existing->user->update(['name' => 'Zara Member']);

        $response = $this->getJson('/api/library/users/search?q=Zara')->assertOk();

        $this->assertSame([$candidate->id], array_column($response->json('data'), 'id'));
        $this->assertSame(['id', 'name', 'email'], array_keys($response->json('data.0')));
    }

    public function test_late_return_charges_a_fine_per_overdue_day(): void
    {
        $this->issue($this->book(), $this->member())->assertCreated();
        $issue = BookIssue::first();
        $issue->update(['due_date' => today()->subDays(3)]);

        $this->patchJson("/api/library/issues/{$issue->id}/return")
            ->assertOk()
            ->assertJson(['fine' => ['days' => 3, 'amount' => 15]]);
    }
}
