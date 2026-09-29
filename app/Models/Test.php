<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    protected $table = 'tbltest';
    protected $primaryKey = 'TestId';

    protected $fillable = [
        'SessionId',
        'CreatedByUserId',
        'TestName',
        'DurationMinutes',
        'TotalMarks',
        'PassScore',
        'RandomizeQuestions',
        'ExamDay',
        'AcademicYear',
        'ScheduledAt',
        'FinishedAt',
        'Status',
    ];

    protected $casts = [
        'ScheduledAt' => 'datetime',
        'FinishedAt' => 'datetime',
        'RandomizeQuestions' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'SessionId', 'SessionId');
    }

    public function createdBy()
    {
        return $this->belongsTo(Admin::class, 'CreatedByUserId', 'AdminId');
    }

    public function questions()
    {
        return $this->hasMany(Question::class, 'TestId', 'TestId');
    }

    public function submissions()
    {
        return $this->hasMany(StudentSubmission::class, 'TestId', 'TestId');
    }
}
