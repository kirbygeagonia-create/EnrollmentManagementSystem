<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Staffusers extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'staffusers';
    protected $primaryKey = 'userId';
    public $timestamps = false;

    protected $fillable = [
        'officeId', 'unitId', 'employeeNo', 'firstName', 'middleName',
        'lastName', 'username', 'passwordHash', 'role', 'email',
        'contactNo', 'status',
    ];

    protected $hidden = [
        'passwordHash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'role' => \App\Enums\StaffRole::class,
            'status' => \App\Enums\StaffStatus::class,
        ];
    }

    /**
     * Get the password for the authentication guard.
     */
    public function getAuthPassword(): string
    {
        return $this->passwordHash;
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'username';
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'unitId');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'evaluatedBy');
    }

    public function auditlogs(): HasMany
    {
        return $this->hasMany(Auditlogs::class, 'userId');
    }

    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'approvedBy');
    }

    public function clinicrecords(): HasMany
    {
        return $this->hasMany(Clinicrecords::class, 'clinicStaffId');
    }

    public function documentprintlog(): HasMany
    {
        return $this->hasMany(Documentprintlog::class, 'printedBy');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Documents::class, 'verifiedBy');
    }

    public function evaluatedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'evaluatedBy');
    }

    public function processedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'registrarProcessedBy');
    }

    public function enrollmentstatushistory(): HasMany
    {
        return $this->hasMany(Enrollmentstatushistory::class, 'changedBy');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'processedBy');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'instructorId');
    }

    public function staffRoles(): HasMany
    {
        return $this->hasMany(StaffRoles::class, 'userId');
    }

    public function studentclearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'receivedBy');
    }

    public function studentids(): HasMany
    {
        return $this->hasMany(Studentids::class, 'validatedBy');
    }

    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'approvedBy');
    }

    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'signedBy');
    }
}