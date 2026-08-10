<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedulemeetings extends Model
{
    protected $table = 'schedulemeetings';

    protected $primaryKey = 'meetingId';

    public $timestamps = false;

    protected $fillable = ['scheduleId', 'dayOfWeek', 'startTime', 'endTime'];

    // NOTE: startTime/endTime are intentionally NOT cast to datetime. Casting them
    // to Carbon breaks BlockingController::detectConflicts(), which uses query-builder
    // comparisons (where('startTime', '<', $meeting->endTime)) against the TIME column —
    // a Carbon binding serializes to a full datetime string and corrupts the comparison.
    // Blades parse on demand: \Illuminate\Support\Carbon::parse($meeting->startTime)->format('H:i').

    protected function casts(): array
    {
        return [
            'dayOfWeek' => DayOfWeek::class,
        ];
    }

    /**
     * @return BelongsTo<Schedules, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedules::class, 'scheduleId');
    }
}
