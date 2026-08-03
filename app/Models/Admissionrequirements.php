<?php

namespace App\Models;

use App\Enums\AppliesTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admissionrequirements extends Model
{
    protected $table = 'admissionrequirements';

    protected $primaryKey = 'requirementId';

    public $timestamps = false;

    protected $fillable = ['requirementName', 'appliesTo', 'isRequired'];

    protected function casts(): array
    {
        return [
            'appliesTo' => AppliesTo::class,
            'isRequired' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Studentrequirementsubmissions, $this>
     */
    public function studentrequirementsubmissions(): HasMany
    {
        return $this->hasMany(Studentrequirementsubmissions::class, 'requirementId');
    }
}
