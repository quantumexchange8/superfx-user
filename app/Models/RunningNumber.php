<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RunningNumber extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type',
        'prefix',
        'digits',
        'last_number',
    ];
}
