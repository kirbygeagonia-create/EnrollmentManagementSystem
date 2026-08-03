<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Studentrequirementsubmissions extends Model
{
    protected $table = 'studentrequirementsubmissions';

    protected $primaryKey = 'submissionId';

    public $timestamps = false;

    protected $fillable = ['admissionId', 'requirementId', 'submissionStatus', 'submittedDate', 'remarks'];

    protected function casts(): array
    {
        return [
            'submissionStatus' => SubmissionStatus::class,
            'submittedDate' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Admissions, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admissions::class, 'admissionId');
    }

    /**
     * @return BelongsTo<Admissionrequirements, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Admissionrequirements::class, 'requirementId');
    }

    /**
     * @return HasMany<Documents, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Documents::class, 'submissionId');
    }
}
