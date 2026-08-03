<?php

namespace App\Models;

use App\Enums\ScholarshipStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Studentscholarships extends Model
{
    protected $table = 'studentscholarships';

    protected $primaryKey = 'studentScholarshipId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'scholarshipTypeId', 'termId', 'status', 'approvedBy', 'awardedBeforeEnrollment'];

    protected function casts(): array
    {
        return [
            'status' => ScholarshipStatus::class,
            'awardedBeforeEnrollment' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'approvedBy');
    }

    /**
     * @return BelongsTo<Scholarshiptypes, $this>
     */
    public function scholarshipType(): BelongsTo
    {
        return $this->belongsTo(Scholarshiptypes::class, 'scholarshipTypeId');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }
}
