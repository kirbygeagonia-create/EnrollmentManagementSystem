<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Offices extends Model
{
    protected $table = 'offices';

    protected $primaryKey = 'officeId';

    public $timestamps = false;

    protected $fillable = ['officeId', 'officeName'];

    /**
     * @return HasMany<Clearancerequirements, $this>
     */
    public function clearancerequirements(): HasMany
    {
        return $this->hasMany(Clearancerequirements::class, 'officeId');
    }

    /**
     * @return HasMany<Staffusers, $this>
     */
    public function staffusers(): HasMany
    {
        return $this->hasMany(Staffusers::class, 'officeId');
    }

    /**
     * @return HasMany<Workflowsteps, $this>
     */
    public function workflowsteps(): HasMany
    {
        return $this->hasMany(Workflowsteps::class, 'officeId');
    }
}
