<?php

namespace App\Models;

class Block extends StringKeyModel
{
    protected $primaryKey = 'block_id';
    protected $fillable = ['block_id', 'hostel_id', 'block_number', 'total_floors'];

    public function hostel()
    {
        return $this->belongsTo(HostelDetail::class, 'hostel_id', 'hostel_id');
    }
    public function rooms()
    {
        return $this->hasMany(Room::class, 'block_id', 'block_id');
    }
}
