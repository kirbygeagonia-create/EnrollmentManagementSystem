<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentrequirementsubmissions extends Model
{
    protected $table = 'studentrequirementsubmissions';
    public $timestamps = false;
    protected $fillable = ['admissionId', 'requirementId', 'submissionStatus', 'submittedDate', 'remarks'];

    protected function casts(): array
    {
        return [
            'submissionStatus' => \App\Enums\SubmissionStatus::class,
            'submittedDate' => 'date',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admissions::class, 'admissionId');
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Admissionrequirements::class, 'requirementId');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Documents::class, 'submissionId');
    }
}
