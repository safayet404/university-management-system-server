<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeInvoice extends Model
{
    use SoftDeletes, LogsActivity;
    protected $fillable = ['invoice_number', 'student_profile_id', 'fee_structure_id', 'fee_type', 'description', 'amount', 'discount', 'fine', 'paid_amount', 'semester', 'academic_year', 'due_date', 'status', 'remarks'];
    protected $casts = ['amount' => 'decimal:2', 'discount' => 'decimal:2', 'fine' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_date' => 'date'];
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'paid_amount'])->logOnlyDirty();
    }
    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_profile_id');
    }
    public function feeStructure()
    {
        return $this->belongsTo(FeeStructure::class);
    }
    public function payments()
    {
        return $this->hasMany(FeePayment::class);
    }
    public function getNetAmountAttribute(): float
    {
        return (float)$this->amount - (float)$this->discount + (float)$this->fine;
    }
    public function getDueAmountAttribute(): float
    {
        return $this->net_amount - (float)$this->paid_amount;
    }
    public function updateStatus(): void
    {
        $net = $this->net_amount;
        $paid = (float)$this->paid_amount;
        if ($paid <= 0) {
            $this->status = now()->gt($this->due_date) ? 'overdue' : 'unpaid';
        } elseif ($paid >= $net) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }
        $this->save();
    }
}
