<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transferacademicrecords extends Model
{
    protected $table = 'transferacademicrecords';
    public $timestamps = false;
    protected $fillable = ['studentId', 'institutionId', 'subjectNameAtOldSchool', 'unitsAtOldSchool', 'gradeAtOldSchool', 'passResult'];

    protected function casts(): array
    {
        return [
            'unitsAtOldSchool' => 'decimal:2',
            'gradeAtOldSchool' => 'decimal:2',
            'passResult' => \App\Enums\PassResult::class,
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Educationalinstitutions::class, 'institutionId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'transferRecordId');
    }
}
