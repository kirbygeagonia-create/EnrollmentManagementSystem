<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clearancerequirements extends Model
{
    protected $table = 'clearancerequirements';
    public $timestamps = false;
    protected $fillable = ['officeId'];

    public function office(): BelongsTo
    {
        return $this->belongsTo(Offices::class, 'officeId');
    }

    public function clearanceapprovals(): HasMany
    {
        return $this->hasMany(Clearanceapprovals::class, 'clearanceRequirementId');
    }
}
