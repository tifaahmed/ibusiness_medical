<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientErrorLog extends Model
{
    protected $fillable = [
        'platform',
        'app_version',
        'route',
        'fatal',
        'message',
        'stack',
        'extra',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'fatal' => 'boolean',
        'extra' => 'array',
    ];
}
