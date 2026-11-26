<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/** A notification to be sent later: once on a date, every week on a day, or every month on a day. */
class PushSchedule extends Model
{
    use SoftDeletes;

    public const ONCE = 'once';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';

    protected $fillable = [
        'title', 'body', 'url', 'audience', 'audience_phone', 'audience_filters', 'frequency', 'run_date', 'weekday', 'month_day', 'send_time',
        'is_active', 'next_run_at', 'last_run_at', 'runs_count', 'last_error', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'audience_filters' => 'array',
            'run_date' => 'date:Y-m-d',
            'weekday' => 'integer',
            'month_day' => 'integer',
            'is_active' => 'boolean',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * The first send time strictly after `$after`, or null when there is none (a one-off whose time has passed).
     * A monthly day the month does not have (31 in April, 30 in February) falls on that month's LAST day.
     */
    public function nextRunAfter(CarbonInterface $after): ?Carbon
    {
        $after = Carbon::instance($after)->setTimezone(config('app.timezone'));
        [$hour, $minute] = array_map('intval', explode(':', $this->send_time));
        $at = fn (Carbon $day): Carbon => $day->copy()->setTime($hour, $minute);

        if ($this->frequency === self::ONCE) {
            $run = $at(Carbon::parse($this->run_date->format('Y-m-d'), config('app.timezone')));

            return $run->gt($after) ? $run : null;
        }

        if ($this->frequency === self::WEEKLY) {
            $day = $after->copy()->startOfDay();
            while ($day->dayOfWeek !== $this->weekday || ! $at($day)->gt($after)) {
                $day->addDay();
            }

            return $at($day);
        }

        $month = $after->copy()->startOfMonth();
        $inMonth = fn (Carbon $m): Carbon => $at($m->copy()->day(min($this->month_day, $m->daysInMonth)));
        $run = $inMonth($month);

        return $run->gt($after) ? $run : $inMonth($month->addMonthNoOverflow());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
