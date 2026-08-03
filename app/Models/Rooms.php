<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rooms extends Model
{
    protected $table = 'rooms';

    protected $primaryKey = 'roomId';

    public $timestamps = false;

    protected $fillable = ['roomName', 'capacity', 'building'];

    /**
     * @return HasMany<Schedules, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'roomId');
    }
}
