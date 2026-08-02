<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculums extends Model
{
    protected $table = 'curriculums';
    public $timestamps = false;
    protected $fillable = ['courseId', 'majorId', 'effectiveYear', 'curriculumName'];

    protected function casts(): array
    {
        return [
            'effectiveYear' => 'date',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    public function curriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'curriculumId');
    }
}
