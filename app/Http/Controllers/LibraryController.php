<?php

namespace App\Http\Controllers;

use App\Models\BookIssue;
use App\Models\LibraryBook;
use App\Models\LibraryMember;
use App\Models\User;
use App\Traits\LogsPageVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LibraryController extends Controller
{
    use LogsPageVisit;

    const FINE_PER_DAY    = 5;
    const LOAN_DAYS       = 14;
    const FINE_PER_DAY_BDT = 5;

    // ── BOOKS ─────────────────────────────────────────────────

    public function books(Request $request)
    {
        self::logVisit('library', 'books', 'visited', 'Visited library catalog');
        $query = LibraryBook::with('department')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('title', 'like', "%{$s}%")
                ->orWhere('author', 'like', "%{$s}%")
                ->orWhere('isbn', 'like', "%{$s}%")
                ->orWhere('publisher', 'like', "%{$s}%")
            );
        }
        if ($request->filled('category'))      $query->where('category', $request->category);
        if ($request->filled('department_id')) $query->where('department_id', $request->department_id);
        if ($request->filled('status'))        $query->where('status', $request->status);
        if ($request->filled('available'))     $query->where('available_copies', '>', 0);

        if ($request->boolean('all')) return response()->json(['success' => true, 'data' => $query->get()->map(fn($b) => $this->formatBook($b))]);

        $books = $query->paginate($request->get('per_page', 15));
        return response()->json([
            'success'    => true,
            'data'       => $books->map(fn($b) => $this->formatBook($b)),
            'pagination' => ['total' => $books->total(), 'current_page' => $books->currentPage(), 'last_page' => $books->lastPage()],
        ]);
    }

    public function storeBook(Request $request)
    {
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'author'          => 'required|string|max:255',
            'isbn'            => 'nullable|string|unique:library_books,isbn',
            'publisher'       => 'nullable|string|max:255',
            'edition'         => 'nullable|string|max:50',
            'publish_year'    => 'nullable|string|max:4',
            'category'        => 'required|in:textbook,reference,fiction,journal,thesis,magazine,other',
            'language'        => 'nullable|string',
            'department_id'   => 'nullable|exists:departments,id',
            'total_copies'    => 'nullable|integer|min:1',
            'price'           => 'nullable|numeric|min:0',
            'shelf_location'  => 'nullable|string|max:50',
            'description'     => 'nullable|string',
        ]);
        $validated['available_copies'] = $validated['total_copies'] ?? 1;
        $book = LibraryBook::create($validated);
        self::logVisit('library', 'book-add', 'created', "Added book: {$book->title}", [], $validated, LibraryBook::class, $book->id);
        return response()->json(['success' => true, 'message' => 'Book added.', 'data' => $this->formatBook($book)], 201);
    }

    public function updateBook(Request $request, $id)
    {
        $book = LibraryBook::findOrFail($id);
        $validated = $request->validate([
            'title'          => 'sometimes|string|max:255',
            'author'         => 'sometimes|string|max:255',
            'isbn'           => 'nullable|string|unique:library_books,isbn,' . $id,
            'publisher'      => 'nullable|string',
            'edition'        => 'nullable|string',
            'publish_year'   => 'nullable|string',
            'category'       => 'sometimes|in:textbook,reference,fiction,journal,thesis,magazine,other',
            'total_copies'   => 'nullable|integer|min:1',
            'price'          => 'nullable|numeric',
            'shelf_location' => 'nullable|string',
            'status'         => 'nullable|in:available,unavailable,lost,damaged',
            'description'    => 'nullable|string',
        ]);

        // Adjust available copies if total changes
        if (isset($validated['total_copies'])) {
            $diff = $validated['total_copies'] - $book->total_copies;
            $validated['available_copies'] = max(0, $book->available_copies + $diff);
        }

        $book->update($validated);
        return response()->json(['success' => true, 'message' => 'Updated.', 'data' => $this->formatBook($book->fresh())]);
    }

    public function destroyBook($id)
    {
        $book = LibraryBook::findOrFail($id);
        if ($book->issues()->where('status', 'issued')->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete book with active issues.'], 422);
        }
        $book->delete();
        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    // ── MEMBERS ───────────────────────────────────────────────

    public function members(Request $request)
    {
        self::logVisit('library', 'members', 'visited', 'Visited library members');
        $query = LibraryMember::with('user')->latest();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q
                ->where('member_id', 'like', "%{$s}%")
                ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            );
        }
        if ($request->filled('member_type')) $query->where('member_type', $request->member_type);
        if ($request->filled('status'))      $query->where('status', $request->status);

        $members = $query->paginate($request->get('per_page', 15));
        return response()->json([
            'success'    => true,
            'data'       => $members->map(fn($m) => $this->formatMember($m)),
            'pagination' => ['total' => $members->total(), 'current_page' => $members->currentPage(), 'last_page' => $members->lastPage()],
        ]);
    }

    public function storeMember(Request $request)
    {
        $request->validate([
            'user_id'     => 'required|exists:users,id|unique:library_members,user_id',
            'member_type' => 'required|in:student,faculty,staff',
            'max_books'   => 'nullable|integer|min:1|max:10',
        ]);

        $member = LibraryMember::create([
            'user_id'          => $request->user_id,
            'member_id'        => 'LIB-' . strtoupper(Str::random(6)),
            'member_type'      => $request->member_type,
            'max_books'        => $request->max_books ?? ($request->member_type === 'faculty' ? 5 : 3),
            'membership_start' => now()->format('Y-m-d'),
            'membership_end'   => now()->addYear()->format('Y-m-d'),
            'status'           => 'active',
        ]);

        self::logVisit('library', 'member-add', 'created', "Added library member: {$member->member_id}", [], [], LibraryMember::class, $member->id);

        return response()->json(['success' => true, 'message' => 'Member added.', 'data' => $this->formatMember($member->load('user'))], 201);
    }

    public function searchMembers(Request $request)
    {
        $s = $request->get('q', '');
        $members = LibraryMember::with('user')
            ->where('status', 'active')
            ->where(fn($q) => $q
                ->where('member_id', 'like', "%{$s}%")
                ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%"))
            )
            ->limit(8)->get();

        return response()->json(['success' => true, 'data' => $members->map(fn($m) => $this->formatMember($m))]);
    }

    // ── ISSUES ────────────────────────────────────────────────

    public function issues(Request $request)
    {
        self::logVisit('library', 'issues', 'visited', 'Visited book issues');
        $query = BookIssue::with(['book', 'member.user', 'issuedBy'])->latest();

        if ($request->filled('status'))            $query->where('status', $request->status);
        if ($request->filled('library_member_id')) $query->where('library_member_id', $request->library_member_id);
        if ($request->filled('overdue'))           $query->where('due_date', '<', today())->where('status', 'issued');

        // Auto-mark overdue
        BookIssue::where('status', 'issued')->where('due_date', '<', today())->update(['status' => 'overdue']);

        $issues = $query->paginate($request->get('per_page', 15));
        return response()->json([
            'success'    => true,
            'data'       => $issues->map(fn($i) => $this->formatIssue($i)),
            'pagination' => ['total' => $issues->total(), 'current_page' => $issues->currentPage(), 'last_page' => $issues->lastPage()],
        ]);
    }

    public function issueBook(Request $request)
    {
        $request->validate([
            'library_book_id'   => 'required|exists:library_books,id',
            'library_member_id' => 'required|exists:library_members,id',
            'due_date'          => 'nullable|date|after:today',
        ]);

        $book   = LibraryBook::findOrFail($request->library_book_id);
        $member = LibraryMember::findOrFail($request->library_member_id);

        if ($book->available_copies < 1) return response()->json(['success' => false, 'message' => 'No copies available.'], 422);
        if ($member->status !== 'active') return response()->json(['success' => false, 'message' => 'Member account is not active.'], 422);

        $currentlyIssued = BookIssue::where('library_member_id', $member->id)->where('status', 'issued')->count();
        if ($currentlyIssued >= $member->max_books) return response()->json(['success' => false, 'message' => "Member has reached maximum book limit ({$member->max_books})."], 422);

        DB::transaction(function () use ($request, $book, $member, &$issue) {
            $issue = BookIssue::create([
                'library_book_id'   => $book->id,
                'library_member_id' => $member->id,
                'issued_by'         => auth()->id(),
                'issue_date'        => today()->format('Y-m-d'),
                'due_date'          => $request->due_date ?? today()->addDays(self::LOAN_DAYS)->format('Y-m-d'),
                'status'            => 'issued',
            ]);
            $book->decrement('available_copies');
        });

        self::logVisit('library', 'issue', 'created', "Issued: {$book->title} to {$member->member_id}", [], [], BookIssue::class, $issue->id);

        return response()->json(['success' => true, 'message' => "Book issued successfully. Due: {$issue->due_date->format('M d, Y')}", 'data' => $this->formatIssue($issue->load(['book', 'member.user']))], 201);
    }

    public function returnBook(Request $request, $issueId)
    {
        $issue = BookIssue::with(['book', 'member'])->findOrFail($issueId);

        if ($issue->status === 'returned') return response()->json(['success' => false, 'message' => 'Already returned.'], 422);

        $returnDate = $request->return_date ?? today()->format('Y-m-d');
        $fine       = $issue->calculateFine(self::FINE_PER_DAY_BDT);

        DB::transaction(function () use ($issue, $returnDate, $fine, $request) {
            $issue->update([
                'return_date' => $returnDate,
                'returned_to' => auth()->id(),
                'fine_days'   => $fine['days'],
                'fine_amount' => $fine['amount'],
                'fine_paid'   => $fine['amount'] == 0 || $request->boolean('fine_paid'),
                'status'      => 'returned',
                'remarks'     => $request->remarks,
            ]);
            $issue->book->increment('available_copies');
        });

        self::logVisit('library', 'return', 'updated', "Returned: {$issue->book->title}", [], [], BookIssue::class, $issue->id);

        return response()->json([
            'success' => true,
            'message' => $fine['days'] > 0 ? "Book returned with fine: ৳{$fine['amount']} ({$fine['days']} days overdue)" : 'Book returned successfully.',
            'fine'    => $fine,
            'data'    => $this->formatIssue($issue->fresh()->load(['book', 'member.user'])),
        ]);
    }

    public function stats()
    {
        return response()->json(['success' => true, 'data' => [
            'total_books'      => LibraryBook::sum('total_copies'),
            'available_books'  => LibraryBook::sum('available_copies'),
            'total_titles'     => LibraryBook::count(),
            'total_members'    => LibraryMember::where('status', 'active')->count(),
            'issued_today'     => BookIssue::whereDate('issue_date', today())->count(),
            'currently_issued' => BookIssue::whereIn('status', ['issued', 'overdue'])->count(),
            'overdue'          => BookIssue::where('status', 'overdue')->count(),
            'total_fines'      => BookIssue::sum('fine_amount'),
            'by_category'      => LibraryBook::selectRaw('category, count(*) as count, sum(total_copies) as copies')
                ->groupBy('category')->orderByDesc('count')->get(),
        ]]);
    }

    // ── Helpers ───────────────────────────────────────────────

    private function formatBook(LibraryBook $b): array
    {
        return [
            'id'               => $b->id,
            'isbn'             => $b->isbn,
            'title'            => $b->title,
            'author'           => $b->author,
            'publisher'        => $b->publisher,
            'edition'          => $b->edition,
            'publish_year'     => $b->publish_year,
            'category'         => $b->category,
            'language'         => $b->language,
            'total_copies'     => $b->total_copies,
            'available_copies' => $b->available_copies,
            'price'            => $b->price,
            'shelf_location'   => $b->shelf_location,
            'status'           => $b->status,
            'description'      => $b->description,
            'department'       => $b->department ? ['id' => $b->department->id, 'name' => $b->department->name, 'code' => $b->department->code] : null,
        ];
    }

    private function formatMember(LibraryMember $m): array
    {
        return [
            'id'               => $m->id,
            'member_id'        => $m->member_id,
            'member_type'      => $m->member_type,
            'max_books'        => $m->max_books,
            'membership_start' => $m->membership_start?->format('Y-m-d'),
            'membership_end'   => $m->membership_end?->format('Y-m-d'),
            'status'           => $m->status,
            'total_fines'      => $m->total_fines,
            'user'             => $m->user ? ['id' => $m->user->id, 'name' => $m->user->name, 'email' => $m->user->email, 'avatar_url' => $m->user->avatar_url] : null,
        ];
    }

    private function formatIssue(BookIssue $i): array
    {
        $daysOverdue = $i->status !== 'returned' && $i->due_date < today()
            ? today()->diffInDays($i->due_date)
            : 0;
        return [
            'id'          => $i->id,
            'issue_date'  => $i->issue_date?->format('Y-m-d'),
            'due_date'    => $i->due_date?->format('Y-m-d'),
            'return_date' => $i->return_date?->format('Y-m-d'),
            'fine_days'   => $i->fine_days,
            'fine_amount' => $i->fine_amount,
            'fine_paid'   => $i->fine_paid,
            'status'      => $i->status,
            'remarks'     => $i->remarks,
            'days_overdue'=> $daysOverdue,
            'estimated_fine' => $daysOverdue * self::FINE_PER_DAY_BDT,
            'issued_by'   => $i->issuedBy?->name,
            'book'        => $i->book  ? ['id' => $i->book->id,  'title' => $i->book->title,  'author' => $i->book->author,  'isbn' => $i->book->isbn, 'shelf_location' => $i->book->shelf_location] : null,
            'member'      => $i->member? ['id' => $i->member->id,'member_id' => $i->member->member_id,'member_type' => $i->member->member_type,'name' => $i->member->user?->name,'avatar_url' => $i->member->user?->avatar_url] : null,
        ];
    }
}
