<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookIssue extends Model
{
    protected $fillable = [
        'library_book_id', 'library_member_id', 'issued_by', 'returned_to',
        'issue_date', 'due_date', 'return_date',
        'fine_days', 'fine_amount', 'fine_paid', 'status', 'remarks',
    ];

    protected $casts = [
        'issue_date'  => 'date',
        'due_date'    => 'date',
        'return_date' => 'date',
        'fine_paid'   => 'boolean',
        'fine_amount' => 'decimal:2',
    ];

    public function book()       { return $this->belongsTo(LibraryBook::class, 'library_book_id'); }
    public function member()     { return $this->belongsTo(LibraryMember::class, 'library_member_id'); }
    public function issuedBy()   { return $this->belongsTo(User::class, 'issued_by'); }
    public function returnedTo() { return $this->belongsTo(User::class, 'returned_to'); }

    public function calculateFine(int $finePerDay = 5): array
    {
        $dueDate    = $this->due_date;
        $returnDate = $this->return_date ?? now()->toDateObject();
        $days       = max(0, $dueDate->diffInDays($returnDate, false) * -1);
        $overdue    = $returnDate > $dueDate ? (int)$dueDate->diffInDays($returnDate) : 0;
        return ['days' => $overdue, 'amount' => $overdue * $finePerDay];
    }
}
