<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Educationalinstitutions extends Model
{
    protected $table = 'educationalinstitutions';
    public $timestamps = false;
    protected $fillable = ['institutionName', 'institutionType', 'cityMunicipality', 'province'];

    protected function casts(): array
    {
        return [
            'institutionType' => \App\Enums\InstitutionType::class,
        ];
    }

    public function studenteducationalbackgrounds(): HasMany
    {
        return $this->hasMany(Studenteducationalbackgrounds::class, 'institutionId');
    }

    public function transferacademicrecords(): HasMany
    {
        return $this->hasMany(Transferacademicrecords::class, 'institutionId');
    }
}
