<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardians extends Model
{
    protected $table = 'guardians';
    public $timestamps = false;
    protected $fillable = ['studentId', 'relationship', 'fullName', 'contactNumber', 'email', 'isEmergencyContact', 'isAuthorizedToActOnBehalf'];

    protected function casts(): array
    {
        return [
            'relationship' => \App\Enums\GuardianRelationship::class,
            'isEmergencyContact' => 'boolean',
            'isAuthorizedToActOnBehalf' => 'boolean',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }
}
