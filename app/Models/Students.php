<?php

namespace App\Models;

use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Students extends Model
{
    protected $table = 'students';

    protected $primaryKey = 'studentId';

    public $timestamps = true;

    protected $fillable = ['schoolIdNumber', 'lastName', 'firstName', 'middleName', 'suffix', 'gender', 'birthdate', 'birthplace', 'citizenship', 'civilStatus', 'religionId', 'contactNumber', 'telephoneNumber', 'semestersCompleted', 'yearsInInstitution', 'email', 'username', 'passwordHash', 'status'];

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birthdate' => 'date',
            'civilStatus' => CivilStatus::class,
            'status' => StaffStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Religions, $this>
     */
    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religions::class, 'religionId');
    }

    /**
     * @return HasMany<Addresses, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Addresses::class, 'studentId');
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'studentId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'studentId');
    }

    /**
     * @return HasMany<Examresults, $this>
     */
    public function examResults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'studentId');
    }

    /**
     * @return HasMany<Guardians, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardians::class, 'studentId');
    }

    /**
     * @return HasMany<Studentclearances, $this>
     */
    public function clearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'studentId');
    }

    /**
     * @return HasMany<Studentclearances, $this>
     */
    public function studentclearances(): HasMany
    {
        return $this->clearances();
    }

    /**
     * @return HasMany<Studenteducationalbackgrounds, $this>
     */
    public function educationalBackgrounds(): HasMany
    {
        return $this->hasMany(Studenteducationalbackgrounds::class, 'studentId');
    }

    /**
     * @return HasMany<Studenteducationalbackgrounds, $this>
     */
    public function studenteducationalbackgrounds(): HasMany
    {
        return $this->educationalBackgrounds();
    }

    /**
     * @return HasMany<Studentids, $this>
     */
    public function studentids(): HasMany
    {
        return $this->hasMany(Studentids::class, 'studentId');
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function scholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'studentId');
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function studentscholarships(): HasMany
    {
        return $this->scholarships();
    }

    /**
     * @return HasMany<Transferacademicrecords, $this>
     */
    public function transferRecords(): HasMany
    {
        return $this->hasMany(Transferacademicrecords::class, 'studentId');
    }

    /**
     * @return HasMany<Transferacademicrecords, $this>
     */
    public function transferacademicrecords(): HasMany
    {
        return $this->transferRecords();
    }
}
