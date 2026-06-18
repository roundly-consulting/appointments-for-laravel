<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Concerns\HasAppointments;

class User extends Model
{
    use HasAppointments;

    protected $guarded = [];

    public $timestamps = false;
}
