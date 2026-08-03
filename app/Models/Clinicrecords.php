<?php

namespace App\Models;

use App\Enums\ClinicRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clinicrecords extends Model
{
    protected $table = 'clinicrecords';

    protected $primaryKey = 'clinicRecordId';

    public $timestamps = false;

    protected $fillable = ['enrollmentId', 'heightCm', 'weightKg', 'bloodPressure', 'philhealthNumber', 'philhealthRegistered', 'assessmentNotes', 'findings', 'clinicStaffId', 'assessmentDate', 'status'];

    protected function casts(): array
    {
        return [
            'heightCm' => 'decimal:2',
            'weightKg' => 'decimal:2',
            'philhealthRegistered' => 'boolean',
            'assessmentDate' => 'date',
            'status' => ClinicRecordStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function clinicStaff(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'clinicStaffId');
    }

    /**
     * @return BelongsTo<Enrollments, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }
}
