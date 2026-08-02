<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Religions extends Model
{
    protected $table = 'religions';
    public $timestamps = false;
    protected $fillable = ['religionName'];

    public function students(): HasMany
    {
        return $this->hasMany(Students::class, 'religionId');
    }
}
