<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class FeeStructure extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = [
        'program_id', 'department_id', 'name', 'fee_type',
        'amount', 'semester', 'academic_year',
        'is_mandatory', 'description', 'status',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'is_mandatory' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'amount', 'status'])->logOnlyDirty();
    }

    public function program()    { return $this->belongsTo(Program::class); }
    public function department() { return $this->belongsTo(Department::class); }
    public function invoices()   { return $this->hasMany(FeeInvoice::class); }
}
