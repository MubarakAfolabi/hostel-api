<?php

namespace App\Models;

class Bed extends StringKeyModel
{
    protected $primaryKey = 'bed_id';
    protected $fillable = ['bed_id', 'room_id', 'bed_label', 'status'];

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_id', 'room_id');
    }
}
