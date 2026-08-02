<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedulemeetings extends Model
{
    protected $table = 'schedulemeetings';
    public $timestamps = false;
    protected $fillable = ['scheduleId', 'dayOfWeek', 'startTime', 'endTime'];

    protected function casts(): array
    {
        return [
            'dayOfWeek' => \App\Enums\DayOfWeek::class,
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedules::class, 'scheduleId');
    }
}
