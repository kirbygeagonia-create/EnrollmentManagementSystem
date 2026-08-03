<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gradescale extends Model
{
    protected $table = 'gradescale';

    protected $primaryKey = 'gradeScaleId';

    public $timestamps = false;

    protected $fillable = ['minGrade', 'maxGrade', 'isPassing', 'description'];

    protected function casts(): array
    {
        return [
            'minGrade' => 'decimal:2',
            'maxGrade' => 'decimal:2',
            'isPassing' => 'boolean',
        ];
    }
}
