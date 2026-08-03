<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    protected $table = 'settings';

    protected $primaryKey = 'settingKey';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = ['settingValue', 'description'];
}
