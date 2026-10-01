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

    /**
     * Highest grade that still counts as a pass.
     *
     * The Philippine scale this institution uses is inverted — lower is better —
     * so anything ABOVE this number is a failed subject. Falls back to 3.00 when
     * no scale is configured, because 3.00 is the lowest passing grade in the
     * 1.00–5.00 scale; a configured scale always wins over the fallback.
     */
    public static function passingCeiling(): float
    {
        $ceiling = static::query()->where('isPassing', true)->max('maxGrade');

        return $ceiling !== null ? (float) $ceiling : 3.0;
    }

    /**
     * Whether an official grade scale has been configured at all. Lets the
     * standing desk say "derived against the school's scale" or "derived
     * against the assumed 3.00 default" instead of pretending they are the same.
     */
    public static function isConfigured(): bool
    {
        return static::query()->where('isPassing', true)->exists();
    }
}
