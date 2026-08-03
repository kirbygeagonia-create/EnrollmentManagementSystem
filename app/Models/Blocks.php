<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blocks extends Model
{
    protected $table = 'blocks';

    protected $primaryKey = 'blockId';

    public $timestamps = false;

    protected $fillable = ['courseId', 'termId', 'yearLevel', 'blockName', 'maxStudents'];

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }

    /**
     * @return HasMany<Enrolledsubjects, $this>
     */
    public function enrolledsubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'blockId');
    }

    /**
     * @return HasMany<Schedules, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'blockId');
    }
}
