<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Curriculums extends Model
{
    protected $table = 'curriculums';

    protected $primaryKey = 'curriculumId';

    public $timestamps = false;

    protected $fillable = ['courseId', 'majorId', 'effectiveYear', 'curriculumName'];

    protected function casts(): array
    {
        return [
            'effectiveYear' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Majors, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    /**
     * @return HasMany<Curriculumsubjects, $this>
     */
    public function curriculumsubjects(): HasMany
    {
        return $this->hasMany(Curriculumsubjects::class, 'curriculumId');
    }

    /**
     * The curriculum version a new record of this program is priced against.
     *
     * Item 7 made the version a student was evaluated under part of the enrollment, but
     * nothing wrote it, so every lookup fell through to "the newest one" — and with one
     * effective year on the catalog that is a tie the database breaks arbitrarily. This is
     * the single definition of that fallback, shared by the desks that create an enrollment
     * and by the screen that reads a pinned one back.
     *
     * A major narrows the search when the record names one, and the program-only lookup is
     * the fallback rather than a failure: a program without a per-major catalog still has
     * exactly one version to be priced against.
     */
    public static function currentFor(int $courseId, ?int $majorId = null): ?Curriculums
    {
        $newest = fn (?int $major): ?Curriculums => static::query()
            ->where('courseId', $courseId)
            ->when($major, fn ($q) => $q->where('majorId', $major))
            ->orderByDesc('effectiveYear')
            ->orderByDesc('curriculumId')
            ->first();

        return $newest($majorId) ?? $newest(null);
    }
}
