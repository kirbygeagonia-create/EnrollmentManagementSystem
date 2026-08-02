<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Majors extends Model
{
    protected $table = 'majors';
    public $timestamps = false;
    protected $fillable = ['courseId', 'majorName'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    public function curriculums(): HasMany
    {
        return $this->hasMany(Curriculums::class, 'majorId');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'majorId');
    }
}
