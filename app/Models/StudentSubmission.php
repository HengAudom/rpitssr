<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudentSubmission extends Model
{
    protected $table = 'tblstudentsubmission';
    protected $primaryKey = 'SubmissionId';

    protected $fillable = [
        'StudentId',
        'TestId',
        'StartedAt',
        'CompletedAt',
        'TotalCorrect',
        'Score',
        'TotalMarks',
        'TotalQuestions',
        'QuestionLimit',
        'PassScore',
        'Interruptions',
        'AssignedQuestionIds',
    ];

    protected $casts = [
        'StartedAt'           => 'datetime',
        'CompletedAt'         => 'datetime',
        'Score'               => 'decimal:2',
        'TotalMarks'          => 'decimal:2',
        'TotalQuestions'      => 'integer',
        'QuestionLimit'       => 'integer',
        'PassScore'           => 'integer',
        'Interruptions'       => 'integer',
        'AssignedQuestionIds' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'StudentId', 'StudentId');
    }

    public function test()
    {
        return $this->belongsTo(Test::class, 'TestId', 'TestId');
    }

    public function details()
    {
        return $this->hasMany(SubmissionDetail::class, 'SubmissionId', 'SubmissionId');
    }
}
