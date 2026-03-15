<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class FeePayment extends Model {
    protected $fillable = ['fee_invoice_id','student_profile_id','collected_by','transaction_id','amount','payment_method','payment_date','remarks'];
    protected $casts = ['amount'=>'decimal:2','payment_date'=>'date'];
    public function invoice()     { return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id'); }
    public function student()     { return $this->belongsTo(StudentProfile::class, 'student_profile_id'); }
    public function collectedBy() { return $this->belongsTo(User::class, 'collected_by'); }
}
