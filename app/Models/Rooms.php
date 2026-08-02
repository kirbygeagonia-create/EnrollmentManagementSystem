<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rooms extends Model
{
    protected $table = 'rooms';
    public $timestamps = false;
    protected $fillable = ['roomName', 'capacity', 'building'];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedules::class, 'roomId');
    }
}
