<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['user_id', 'email', 'password_hash', 'role'];
    protected $hidden = ['password_hash'];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id', 'user_id');
    }
    public function admin()
    {
        return $this->hasOne(Admin::class, 'user_id', 'user_id');
    }
}
