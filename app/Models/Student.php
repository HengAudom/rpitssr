<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Student extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $table = 'tblstudent';
    protected $primaryKey = 'StudentId';

    protected $fillable = [
        'StudentCode',
        'SessionId',
        'ExamDay',
        'AcademicYear',
        'FirstName',
        'LastName',
        'Gender',
        'Phone',
        'ProfileImage',
    ];

    public function getAuthIdentifier()
    {
        return 'student:' . $this->StudentId;
    }

    public function getAuthIdentifierName()
    {
        return 'StudentId';
    }

    public function getIdAttribute()
    {
        return $this->StudentId;
    }

    public function getNameAttribute()
    {
        $full = trim(($this->FirstName ?? '') . ' ' . ($this->LastName ?? ''));
        return $full ?: ($this->StudentCode ?? ('Candidate #' . $this->StudentId));
    }

    public function getRoleAttribute()
    {
        return 'Student';
    }

    public function getStatusAttribute()
    {
        return 'Active';
    }

    public function getProfileImageAttribute()
    {
        return $this->attributes['ProfileImage'] ?? null;
    }

    public function setProfileImageAttribute($value)
    {
        $this->attributes['ProfileImage'] = $value;
    }

    public function session()
    {
        return $this->belongsTo(ExamSession::class, 'SessionId', 'SessionId');
    }

    public function submissions()
    {
        return $this->hasMany(StudentSubmission::class, 'StudentId', 'StudentId');
    }
}
