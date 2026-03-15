<?php

namespace Database\Seeders;

use App\Models\BookIssue;
use App\Models\Department;
use App\Models\LibraryBook;
use App\Models\LibraryMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LibrarySeeder extends Seeder
{
    public function run(): void
    {
        $faker       = \Faker\Factory::create();
        $departments = Department::all();
        $admin       = User::role('super-admin')->first();

        // ── Books ────────────────────────────────────────────
        $bookData = [
            // CSE
            ['title' => 'Introduction to Algorithms',         'author' => 'Cormen et al.',       'category' => 'textbook',  'dept' => 'CSE', 'isbn' => '978-0262033848'],
            ['title' => 'Clean Code',                         'author' => 'Robert C. Martin',    'category' => 'reference', 'dept' => 'CSE', 'isbn' => '978-0132350884'],
            ['title' => 'The Pragmatic Programmer',           'author' => 'Hunt & Thomas',       'category' => 'reference', 'dept' => 'CSE', 'isbn' => '978-0135957059'],
            ['title' => 'Computer Networks',                  'author' => 'Tanenbaum',           'category' => 'textbook',  'dept' => 'CSE', 'isbn' => '978-0132126953'],
            ['title' => 'Operating System Concepts',          'author' => 'Silberschatz',        'category' => 'textbook',  'dept' => 'CSE', 'isbn' => '978-1119800361'],
            ['title' => 'Database System Concepts',           'author' => 'Silberschatz',        'category' => 'textbook',  'dept' => 'CSE', 'isbn' => '978-0078022159'],
            ['title' => 'Artificial Intelligence',            'author' => 'Russell & Norvig',    'category' => 'textbook',  'dept' => 'CSE', 'isbn' => '978-0134610993'],
            // EEE
            ['title' => 'Electric Circuits',                  'author' => 'Nilsson & Riedel',    'category' => 'textbook',  'dept' => 'EEE', 'isbn' => '978-0134746968'],
            ['title' => 'Microelectronic Circuits',           'author' => 'Sedra & Smith',       'category' => 'textbook',  'dept' => 'EEE', 'isbn' => '978-0190853464'],
            ['title' => 'Power Electronics',                  'author' => 'Mohan et al.',        'category' => 'textbook',  'dept' => 'EEE', 'isbn' => '978-0471226932'],
            // BBA
            ['title' => 'Principles of Management',           'author' => 'Robbins & Coulter',   'category' => 'textbook',  'dept' => 'BBA', 'isbn' => '978-0134527604'],
            ['title' => 'Financial Management',               'author' => 'Brigham & Ehrhardt',  'category' => 'textbook',  'dept' => 'BBA', 'isbn' => '978-0357129456'],
            ['title' => 'Marketing Management',               'author' => 'Philip Kotler',       'category' => 'textbook',  'dept' => 'BBA', 'isbn' => '978-0133856460'],
            // General
            ['title' => 'The Great Gatsby',                   'author' => 'F. Scott Fitzgerald', 'category' => 'fiction',   'dept' => null,  'isbn' => '978-0743273565'],
            ['title' => 'To Kill a Mockingbird',              'author' => 'Harper Lee',          'category' => 'fiction',   'dept' => null,  'isbn' => '978-0061935466'],
            ['title' => 'A Brief History of Time',            'author' => 'Stephen Hawking',     'category' => 'reference', 'dept' => null,  'isbn' => '978-0553380163'],
        ];

        $bookCount = 0;
        foreach ($bookData as $b) {
            $dept = $b['dept'] ? $departments->where('code', $b['dept'])->first() : null;
            if (LibraryBook::where('isbn', $b['isbn'])->exists()) continue;
            $copies = $faker->numberBetween(2, 5);
            LibraryBook::create([
                'title'            => $b['title'],
                'author'           => $b['author'],
                'isbn'             => $b['isbn'],
                'publisher'        => $faker->company(),
                'edition'          => $faker->randomElement(['1st', '2nd', '3rd', '4th', '5th']),
                'publish_year'     => (string)$faker->numberBetween(2015, 2023),
                'category'         => $b['category'],
                'department_id'    => $dept?->id,
                'total_copies'     => $copies,
                'available_copies' => $copies,
                'price'            => $faker->randomElement([500, 800, 1200, 1500, 2000]),
                'shelf_location'   => strtoupper($faker->randomLetter()) . '-' . $faker->numberBetween(1, 50),
                'status'           => 'available',
            ]);
            $bookCount++;
        }

        // ── Members ──────────────────────────────────────────
        $users   = User::take(15)->get();
        $memberCount = 0;
        foreach ($users as $user) {
            if (LibraryMember::where('user_id', $user->id)->exists()) continue;
            $type = $user->hasRole('faculty') ? 'faculty' : ($user->hasRole('student') ? 'student' : 'staff');
            LibraryMember::create([
                'user_id'          => $user->id,
                'member_id'        => 'LIB-' . strtoupper(Str::random(6)),
                'member_type'      => $type,
                'max_books'        => $type === 'faculty' ? 5 : 3,
                'membership_start' => now()->subMonths(6)->format('Y-m-d'),
                'membership_end'   => now()->addMonths(6)->format('Y-m-d'),
                'status'           => 'active',
            ]);
            $memberCount++;
        }

        // ── Issues ───────────────────────────────────────────
        $books   = LibraryBook::all();
        $members = LibraryMember::with('user')->take(8)->get();
        $issueCount = 0;

        foreach ($members->take(5) as $member) {
            $numBooks = rand(1, 2);
            $issuedBooks = $books->random(min($numBooks, $books->count()));

            foreach ($issuedBooks as $book) {
                if ($book->available_copies < 1) continue;

                $issueDate = now()->subDays(rand(5, 25));
                $dueDate   = $issueDate->copy()->addDays(14);
                $isReturned = rand(0, 1);

                $status = 'issued';
                $returnDate = null;
                $fineDays = 0;
                $fineAmount = 0;

                if ($isReturned) {
                    $returnDate = $dueDate->copy()->addDays(rand(-3, 5))->format('Y-m-d');
                    $overdue    = max(0, $dueDate->diffInDays($returnDate, false) * -1);
                    $fineDays   = $overdue > 0 ? (int)$dueDate->diffInDays($returnDate) : 0;
                    $fineAmount = $fineDays * 5;
                    $status     = 'returned';
                } elseif ($dueDate->lt(now())) {
                    $status = 'overdue';
                }

                BookIssue::create([
                    'library_book_id'   => $book->id,
                    'library_member_id' => $member->id,
                    'issued_by'         => $admin->id,
                    'issue_date'        => $issueDate->format('Y-m-d'),
                    'due_date'          => $dueDate->format('Y-m-d'),
                    'return_date'       => $returnDate,
                    'fine_days'         => $fineDays,
                    'fine_amount'       => $fineAmount,
                    'fine_paid'         => $fineAmount > 0 ? rand(0, 1) : false,
                    'status'            => $status,
                    'returned_to'       => $isReturned ? $admin->id : null,
                ]);

                if (!$isReturned) $book->decrement('available_copies');
                $issueCount++;
            }
        }

        $this->command->info("✅ LibrarySeeder: {$bookCount} books, {$memberCount} members, {$issueCount} issues");
    }
}
