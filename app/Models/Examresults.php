<?php

namespace App\Models;

use App\Enums\ExamResult;
use App\Enums\ExamStage;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Examresults extends Model
{
    protected $table = 'examresults';

    protected $primaryKey = 'examId';

    public $timestamps = false;

    protected $fillable = ['studentId', 'courseId', 'termId', 'examStage', 'examType', 'examResult', 'examDate', 'resultStatus', 'verifiedBy', 'verifiedDate'];

    protected function casts(): array
    {
        return [
            'examStage' => ExamStage::class,
            'examType' => ExamType::class,
            'examResult' => ExamResult::class,
            'resultStatus' => ExamStatus::class,
            'examDate' => 'date',
            'verifiedDate' => 'date',
        ];
    }

    /**
     * Whether the school stands behind this mark. §7.2, ruled 2026-10-26: a desk's own entry
     * decides nothing until the Registrar confirms it, and four readers ask that question —
     * the two admission blockers, the Stage 2 prerequisite, and the retention gate that stops
     * a form being forwarded. They ask it here rather than each spelling the comparison, so
     * one definition answers "does this result count" and a later ruling changes one line.
     *
     * There is deliberately no query scope for it: every reader must look at the row before it
     * knows whether to say "no result on record" or "not yet confirmed", and a filter that
     * answered the second with the first would send the desk to the wrong office.
     */
    public function isOfficial(): bool
    {
        return $this->resultStatus === ExamStatus::Official;
    }

    /**
     * The account that confirmed the result. Null on a row the migration backfilled, so a
     * confirmation the school never signed stays distinguishable from one it did.
     *
     * @return BelongsTo<Staffusers, $this>
     */
    public function verifiedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'verifiedBy', 'userId');
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Students, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Students::class, 'studentId');
    }

    /**
     * @return BelongsTo<Academicterms, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Academicterms::class, 'termId');
    }
}
