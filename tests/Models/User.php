<?php

declare(strict_types=1);

namespace RoundlyConsulting\Appointments\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Appointments\Concerns\HasAppointments;
use RoundlyConsulting\Approvals\Interfaces\GivesApprovalsInterface;
use RoundlyConsulting\Approvals\Traits\GivesApprovals;
use RoundlyConsulting\Contacts\Concerns\HasContacts;
use RoundlyConsulting\Reviews\Concerns\CanReview;

class User extends Model implements GivesApprovalsInterface
{
    use CanReview;
    use GivesApprovals;
    use HasAppointments;
    use HasContacts;

    protected $guarded = [];

    public $timestamps = false;
}
