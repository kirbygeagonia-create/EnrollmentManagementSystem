<?php

namespace App\Models;

use App\Enums\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Academicterms extends Model
{
    protected $table = 'academicterms';

    protected $primaryKey = 'termId';

    public $timestamps = false;

    protected $fillable = ['termId', 'academicYearId', 'semester', 'startDate', 'endDate'];

    protected function casts(): array
    {
        return [
            'semester' => Semester::class,
            'startDate' => 'date',
            'endDate' => 'date',
        ];
    }

    /**
     * The term that covers a date — how the install answers "which term is it now" without
     * a hard-coded term id. Null when the calendar does not reach that date, which is the
     * honest answer during the gap between seeded years.
     */
    public static function covering(?Carbon $on = null): ?self
    {
        $on ??= Carbon::now();

        return static::query()
            ->whereDate('startDate', '<=', $on)
            ->whereDate('endDate', '>=', $on)
            ->first();
    }

    /**
     * @return HasMany<Admissions, $this>
     */
    public function admissions(): HasMany
    {
        return $this->hasMany(Admissions::class, 'termId');
    }

    /**
     * @return BelongsTo<Academicyears, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(Academicyears::class, 'academicYearId');
    }

    /**
     * @return HasMany<Blocks, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Blocks::class, 'termId');
    }

    /**
     * @return HasMany<Clearanceperiods, $this>
     */
    public function clearanceperiods(): HasMany
    {
        return $this->hasMany(Clearanceperiods::class, 'termId');
    }

    /**
     * @return HasMany<Enrollments, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollments::class, 'termId');
    }

    /**
     * @return HasMany<Examresults, $this>
     */
    public function examresults(): HasMany
    {
        return $this->hasMany(Examresults::class, 'termId');
    }

    /**
     * @return HasMany<Studentscholarships, $this>
     */
    public function studentscholarships(): HasMany
    {
        return $this->hasMany(Studentscholarships::class, 'termId');
    }
}
