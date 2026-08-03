<?php

namespace App\Models;

use App\Enums\GuardianRelationship;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guardians extends Model
{
    protected $table = 'guardians';

    protected $primaryKey = 'guardianId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'relationship', 'fullName', 'contactNumber', 'email', 'isEmergencyContact', 'isAuthorizedToActOnBehalf'];

    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'isEmergencyContact' => 'boolean',
            'isAuthorizedToActOnBehalf' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }
}
