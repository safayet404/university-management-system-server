<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamRemark extends Model
{
    protected $fillable = ['term_exam_id', 'student_profile_id', 'remarks', 'entered_by'];
}
