<?php

namespace App\Models;

class Student extends StringKeyModel
{
    protected $primaryKey = 'student_id';
    protected $fillable = ['student_id', 'user_id', 'first_name', 'last_name', 'gender', 'level', 'department', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
