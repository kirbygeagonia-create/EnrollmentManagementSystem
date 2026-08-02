<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Studentscholarships extends Model
{
    protected $table = 'studentscholarships';
    public $timestamps = false;
    protected $fillable = ['studentId', 'scholarshipTypeId', 'termId', 'status', 'approvedBy', 'awardedBeforeEnrollment'];

    protected function casts(): array
    {
        return [
            'status' => \App\Enums\ScholarshipStatus::class,
            'awardedBeforeEnrollment' => 'boolean',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'approvedBy');
    }

    public function scholarshipType(): BelongsTo
    {
        return $this->belongsTo(Scholarshiptypes::class, 'scholarshipTypeId');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }
}
