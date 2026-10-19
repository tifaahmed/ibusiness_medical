<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One free trial per phone and per national ID, ever. See the migration for why this is its own table. */
class TrialClaim extends Model
{
    protected $fillable = ['national_id', 'phone', 'membership_id', 'user_id', 'ip_address', 'user_agent'];
}
