<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedules extends Model
{
    protected $table = 'schedules';
    public $timestamps = false;
    protected $fillable = ['blockId', 'subjectId', 'instructorId', 'roomId'];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Blocks::class, 'blockId');
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'instructorId');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Rooms::class, 'roomId');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subjects::class, 'subjectId');
    }

    public function enrolledsubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'scheduleId');
    }

    public function schedulemeetings(): HasMany
    {
        return $this->hasMany(Schedulemeetings::class, 'scheduleId');
    }
}
