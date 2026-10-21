<?php

namespace App\Models;

class HostelDetail extends StringKeyModel
{
    protected $table = 'hostel_details';
    protected $primaryKey = 'hostel_id';
    protected $fillable = ['hostel_id', 'hostel_name', 'warden_name'];

    public function blocks()
    {
        return $this->hasMany(Block::class, 'hostel_id', 'hostel_id');
    }
}
