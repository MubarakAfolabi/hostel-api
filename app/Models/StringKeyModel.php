<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class StringKeyModel extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
}
