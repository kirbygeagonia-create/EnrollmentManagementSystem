<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class Staffusers extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

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
            'role' => StaffRole::class,
            'status' => StaffStatus::class,
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

    /**
     * @return BelongsTo<Offices, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    /**
     * @return BelongsTo<Academicunits, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Academicunits::class, 'unitId');
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'evaluatedBy');
    }

    /**
     * @return HasMany<Auditlogs, $this>
     */
    public function auditlogs(): HasMany
    {
        return $this->hasMany(Auditlogs::class, 'userId');
    }

    /**
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'approvedBy');
    }

    /**
     * @return HasMany<Clinicrecords, $this>
     */
    public function clinicrecords(): HasMany
    {
        return $this->hasMany(Clinicrecords::class, 'clinicStaffId');
    }

    /**
     * @return HasMany<Documentprintlog, $this>
     */
    public function documentprintlog(): HasMany
    {
        return $this->hasMany(Documentprintlog::class, 'printedBy');
    }

    /**
     * @return HasMany<Documents, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Documents::class, 'verifiedBy');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function evaluatedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'evaluatedBy');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function processedEnrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'registrarProcessedBy');
    }

    /**
     * @return HasMany<Enrollmentstatushistory, $this>
     */
    public function enrollmentstatushistory(): HasMany
    {
        return $this->hasMany(Enrollmentstatushistory::class, 'changedBy');
    }

    /**
     * @return HasMany<Payments, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'processedBy');
    }

    /**
     * @return HasMany<Schedules, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'instructorId');
    }

    /**
     * @return HasMany<StaffRoles, $this>
     */
    public function staffRoles(): HasMany
    {
        return $this->hasMany(StaffRoles::class, 'userId');
    }

    /**
     * @return HasMany<Studentclearances, $this>
     */
    public function studentclearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'receivedBy');
    }

    /**
     * @return HasMany<Studentids, $this>
     */
    public function studentids(): HasMany
    {
        return $this->hasMany(Studentids::class, 'validatedBy');
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'approvedBy');
    }

    /**
     * @return HasMany<Workflowsteps, $this>
     */
    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'signedBy');
    }
}
