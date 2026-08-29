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

    protected $appends = [
        'name',
        'positionTitle',
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
     * Full display name (first + middle initial + last).
     */
    public function getNameAttribute(): string
    {
        $middle = $this->middleName ? ' '.mb_substr($this->middleName, 0, 1).'.' : '';

        return trim($this->firstName.$middle.' '.$this->lastName);
    }

    /**
     * Authentic institutional position title based on role, office, and academic unit.
     */
    public function getPositionTitleAttribute(): string
    {
        $officeName = $this->office ? $this->office->officeName : '';
        $unitName = $this->unit ? $this->unit->unitName : '';
        $roleValue = $this->role?->value ?? 'staff';

        if ($roleValue === 'admin') {
            return 'Lead System Administrator';
        }

        if ($roleValue === 'dean') {
            return $unitName ? "Dean, {$unitName}" : 'College Dean';
        }

        if ($roleValue === 'programHead') {
            return $unitName ? "Program Head, {$unitName}" : 'Academic Program Head';
        }

        if ($roleValue === 'instructor') {
            return $unitName ? "Instructor, {$unitName}" : 'College Faculty / Instructor';
        }

        return match ($this->officeId) {
            1 => $roleValue === 'officeHead' ? 'University Registrar' : 'Registrar Records Officer',
            2 => $roleValue === 'officeHead' ? 'Chief Cashier & Accounting Head' : 'Cashier / Accounting Officer',
            3 => $roleValue === 'officeHead' ? 'Scholarship & Financial Aid Director' : 'Scholarship Coordinator',
            4 => $roleValue === 'officeHead' ? 'Head Guidance Counselor' : 'Guidance Testing Officer',
            5 => $roleValue === 'officeHead' ? 'Head, Human Resources' : 'HR & Scheduling Officer',
            6 => $roleValue === 'officeHead' ? 'Chief of Safety & Security' : 'Security & Clearance Officer',
            7 => $roleValue === 'officeHead' ? 'Chief Librarian & LRC Head' : 'Librarian / Learning Resource Staff',
            8 => $roleValue === 'officeHead' ? 'Head, Property Management' : 'Property & Supply Officer',
            9 => $roleValue === 'officeHead' ? 'Director, Research & Extension' : 'Research Coordinator',
            10 => $roleValue === 'officeHead' ? 'NSTP Director' : 'NSTP Coordinator',
            11 => $roleValue === 'officeHead' ? 'Chief Medical Officer' : 'College Nurse / Health Officer',
            12 => $roleValue === 'officeHead' ? 'Science Laboratory Head' : 'Laboratory Technician / Custodian',
            13 => $roleValue === 'officeHead' ? 'Sports & Athletic Director' : 'Sports Development Officer',
            14 => $roleValue === 'officeHead' ? 'SIPP Internship Director' : 'Internship Coordinator',
            15 => $roleValue === 'officeHead' ? 'RCYC Adviser' : 'Red Cross Youth Officer',
            16 => $roleValue === 'officeHead' ? 'Quality Assurance & Accreditation Head' : 'Accreditation Officer',
            22 => $roleValue === 'officeHead' ? 'ID Office Supervisor' : 'ID Processing & Validation Officer',
            default => $roleValue === 'officeHead' ? ($officeName ? "Head, {$officeName}" : 'Office Head') : ($officeName ? "Staff, {$officeName}" : ($unitName ? "Staff, {$unitName}" : 'Staff Officer')),
        };
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
