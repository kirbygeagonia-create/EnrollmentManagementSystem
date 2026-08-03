<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Majors extends Model
{
    protected $table = 'majors';

    protected $primaryKey = 'majorId';

    public $timestamps = false;

    protected $fillable = ['courseId', 'majorName'];

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return HasMany<Curriculums, $this>
     */
    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculums::class, 'majorId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'majorId');
    }
}
