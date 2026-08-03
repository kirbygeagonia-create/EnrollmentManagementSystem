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
