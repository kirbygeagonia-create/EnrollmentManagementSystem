<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clinicrecords extends Model
{
    protected $table = 'clinicrecords';
    public $timestamps = false;
    protected $fillable = ['enrollmentId', 'heightCm', 'weightKg', 'bloodPressure', 'philhealthNumber', 'philhealthRegistered', 'assessmentNotes', 'findings', 'clinicStaffId', 'assessmentDate', 'status'];

    protected function casts(): array
    {
        return [
            'heightCm' => 'decimal:2',
            'weightKg' => 'decimal:2',
            'philhealthRegistered' => 'boolean',
            'assessmentDate' => 'date',
            'status' => \App\Enums\ClinicRecordStatus::class,
        ];
    }

    public function clinicStaff(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'clinicStaffId');
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollments::class, 'enrollmentId');
    }
}
