<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admissionrequirements extends Model
{
    protected $table = 'admissionrequirements';
    public $timestamps = false;
    protected $fillable = ['requirementName', 'appliesTo', 'isRequired'];

    protected function casts(): array
    {
        return [
            'appliesTo' => \App\Enums\AppliesTo::class,
            'isRequired' => 'boolean',
        ];
    }

    public function studentrequirementsubmissions(): HasMany
    {
        return $this->hasMany(Studentrequirementsubmissions::class, 'requirementId');
    }
}
