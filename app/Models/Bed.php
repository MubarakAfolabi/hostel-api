<?php

namespace App\Models;

class Bed extends StringKeyModel
{
    protected $primaryKey = 'bed_id';
    protected $fillable = ['bed_id', 'room_id', 'bed_label', 'status', 'student_id'];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'student_id');
    }
}
