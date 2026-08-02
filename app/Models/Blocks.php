<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blocks extends Model
{
    protected $table = 'blocks';
    public $timestamps = false;
    protected $fillable = ['courseId', 'termId', 'yearLevel', 'blockName', 'maxStudents'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    public function enrolledsubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'blockId');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'blockId');
    }
}
