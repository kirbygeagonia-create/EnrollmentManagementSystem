<?php

namespace App\Models;

use App\Enums\InstitutionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Educationalinstitutions extends Model
{
    protected $table = 'educationalinstitutions';

    protected $primaryKey = 'institutionId';

    public $timestamps = false;

    protected $fillable = ['institutionName', 'institutionType', 'cityMunicipality', 'province'];

    protected function casts(): array
    {
        return [
            'institutionType' => InstitutionType::class,
        ];
    }

    /**
     * @return HasMany<Studenteducationalbackgrounds, $this>
     */
    public function studenteducationalbackgrounds(): HasMany
    {
        return $this->hasMany(Studenteducationalbackgrounds::class, 'institutionId');
    }

    /**
     * @return HasMany<Transferacademicrecords, $this>
     */
    public function transferacademicrecords(): HasMany
    {
        return $this->hasMany(Transferacademicrecords::class, 'institutionId');
    }
}
