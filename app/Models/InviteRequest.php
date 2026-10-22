<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InviteRequest extends Model
{
    protected $fillable = [
        'email',
        'status',
        'ip_address',
        'user_agent',
        'locale',
        'notes',
    ];
}
