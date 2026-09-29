<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSession extends Model
{
    use HasFactory;

    protected $table = 'tblexamsession';
    protected $primaryKey = 'SessionId';

    protected $fillable = [
        'SessionName',
        'ExamDate',
        'Days',
        'Years',
        'StartTime',
        'EndTime',
        'Description',
        'Status',
    ];

    public function students()
    {
        return $this->hasMany(Student::class, 'SessionId', 'SessionId');
    }

    public function tests()
    {
        return $this->hasMany(Test::class, 'SessionId', 'SessionId');
    }
}
