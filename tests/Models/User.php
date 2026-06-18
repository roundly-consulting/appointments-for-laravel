<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}
