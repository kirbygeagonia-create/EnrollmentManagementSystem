<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Students extends Model
{
    protected $table = 'students';
    public $timestamps = false;
    protected $fillable = ['schoolIdNumber', 'lastName', 'firstName', 'middleName', 'suffix', 'gender', 'birthdate', 'birthplace', 'citizenship', 'civilStatus', 'religionId', 'contactNumber', 'telephoneNumber', 'semestersCompleted', 'yearsInInstitution', 'email', 'username', 'passwordHash', 'status'];

    protected function casts(): array
    {
        return [
            'gender' => \App\Enums\Gender::class,
            'birthdate' => 'date',
            'civilStatus' => \App\Enums\CivilStatus::class,
            'status' => \App\Enums\StaffStatus::class,
        ];
    }

    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religions::class, 'religionId');
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Addresses::class, 'studentId');
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'studentId');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'studentId');
    }

    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'studentId');
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardians::class, 'studentId');
    }

    public function studentclearances(): HasMany
    {
        return $this->hasMany(Studentclearances::class, 'studentId');
    }

    public function studenteducationalbackgrounds(): HasMany
    {
        return $this->hasMany(Studenteducationalbackgrounds::class, 'studentId');
    }

    public function studentids(): HasMany
    {
        return $this->hasMany(Studentids::class, 'studentId');
    }

    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'studentId');
    }

    public function transferacademicrecords(): HasMany
    {
        return $this->hasMany(Transferacademicrecords::class, 'studentId');
    }
}
