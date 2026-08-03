<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculums extends Model
{
    protected $table = 'curriculums';

    protected $primaryKey = 'curriculumId';

    public $timestamps = false;

    protected $fillable = ['courseId', 'majorId', 'effectiveYear', 'curriculumName'];

    protected function casts(): array
    {
        return [
            'effectiveYear' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Majors, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    /**
     * @return HasMany<Curriculumsubjects, $this>
     */
    public function curriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'curriculumId');
    }
}
