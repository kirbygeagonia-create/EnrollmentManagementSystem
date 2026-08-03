<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clearancerequirements extends Model
{
    protected $table = 'clearancerequirements';

    protected $primaryKey = 'clearanceRequirementId';

    public $timestamps = false;

    protected $fillable = ['officeId'];

    /**
     * @return BelongsTo<Offices, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    /**
     * @return HasMany<Clearanceapprovals, $this>
     */
    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'clearanceRequirementId');
    }
}
