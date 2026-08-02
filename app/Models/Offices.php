<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offices extends Model
{
    protected $table = 'offices';
    public $timestamps = false;
    protected $fillable = ['officeName'];

    public function clearancerequirements(): HasMany
    {
        return $this->hasMany(Clearancerequirements::class, 'officeId');
    }

    public function staffusers(): HasMany
    {
        return $this->hasMany(Staffusers::class, 'officeId');
    }

    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'officeId');
    }
}
