<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedules extends Model
{
    protected $table = 'schedules';

    protected $primaryKey = 'scheduleId';

    public $timestamps = false;

    protected $fillable = ['blockId', 'subjectId', 'instructorId', 'roomId'];

    /**
     * @return BelongsTo<Blocks, $this>
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Blocks::class, 'blockId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'instructorId');
    }

    /**
     * @return BelongsTo<Rooms, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Rooms::class, 'roomId');
    }

    /**
     * @return BelongsTo<Subjects, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }

    /**
     * @return HasMany<Enrolledsubjects, $this>
     */
    public function enrolledSubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'scheduleId');
    }

    /**
     * @return HasMany<Schedulemeetings, $this>
     */
    public function schedulemeetings(): HasMany
    {
        return $this->hasMany(Schedulemeetings::class, 'scheduleId');
    }

    /**
     * Alias for schedulemeetings().
     *
     * @return HasMany<Schedulemeetings, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Schedulemeetings::class, 'scheduleId');
    }
}
