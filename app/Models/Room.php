<?php

namespace App\Models;

class Room extends StringKeyModel
{
    protected $primaryKey = 'room_id';
    protected $fillable = ['room_id', 'block_id', 'room_number', 'floor_number', 'capacity', 'room_type'];

    public function block()
    {
        return $this->belongsTo(Block::class, 'block_id', 'block_id');
    }
    public function beds()
    {
        return $this->hasMany(Bed::class, 'room_id', 'room_id');
    }

    public function scopeAvailable($query)
    {
        return $query->whereHas('beds', fn($q) => $q->where('status', 'Available'));
    }

    public function scopeFullyAllocated($query)
    {
        return $query->whereHas('beds', fn($q) => $q->where('status', 'Allocated'))
            ->whereDoesntHave('beds', fn($q) => $q->where('status', 'Available'));
    }
}
