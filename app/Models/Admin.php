<?php

namespace App\Models;

class Admin extends StringKeyModel
{
    protected $primaryKey = 'admin_id';
    protected $fillable = ['admin_id', 'user_id', 'first_name', 'last_name'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
