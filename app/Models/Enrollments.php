<?php

namespace App\Models;

use App\Enums\AcademicStanding;
use App\Enums\EnrollmentStatus;
use App\Enums\EnrollmentType;
use App\Enums\StudentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Enrollments extends Model
{
    protected $table = 'enrollments';

    protected $primaryKey = 'enrollmentId';

    public $timestamps = true;

    protected $fillable = ['studentId', 'courseId', 'majorId', 'termId', 'yearLevel', 'admissionId', 'studentType', 'enrollmentType', 'academicStanding', 'enrollmentStatus', 'evaluatedBy', 'registrarProcessedBy', 'returnReason', 'dropReason', 'clearanceConfirmedBy', 'clearanceConfirmedAt', 'curriculumId', 'enrolledDate', 'formIssuedDate', 'formSignedDate'];

    protected function casts(): array
    {
        return [
            'studentType' => StudentType::class,
            'enrollmentType' => EnrollmentType::class,
            'academicStanding' => AcademicStanding::class,
            'enrollmentStatus' => EnrollmentStatus::class,
            'enrolledDate' => 'date',
            'clearanceConfirmedAt' => 'datetime',
            'formIssuedDate' => 'date',
            'formSignedDate' => 'date',
        ];
    }

    /**
     * Standing and student type live on the enrollment, but the Exam and
     * Clearance queues are keyed by (student, term) and carry no enrollment
     * foreign key. This reads the whole page in one query instead of one per row.
     *
     * @param  iterable<int, array{0: int, 1: int|null}>  $pairs
     * @return array<string, self> keyed "{studentId}-{termId}"
     */
    public static function standingMapFor(iterable $pairs): array
    {
        $pairs = collect($pairs)->filter(fn (array $p) => $p[0] && $p[1]);

        if ($pairs->isEmpty()) {
            return [];
        }

        // Oldest first so the later write wins and a pair keeps its newest enrollment.
        return static::query()
            ->whereIn('studentId', $pairs->pluck(0)->unique())
            ->whereIn('termId', $pairs->pluck(1)->unique())
            ->orderBy('enrollmentId')
            ->get(['enrollmentId', 'studentId', 'termId', 'studentType', 'academicStanding'])
            ->keyBy(fn (self $e) => $e->studentId.'-'.$e->termId)
            ->all();
    }

    /**
     * The year level the student's own record says they are in.
     *
     * G-2. Admission used to assert year level 1 for every enrollment it created, which
     * was right only for a first-year and was never revisited — so a returning student
     * arrived at the department one year short and the level the curriculum priced their
     * load against was wrong from the first moment.
     *
     * It counts completed YEARS, not records: a normal year is two semesters, so two
     * finished terms are one year finished. A term still short of `enrolled` is not
     * completed — the student is still in it — and a dropped one never happened, which is
     * the same reading the seat guard uses.
     *
     * When the term being entered is named, a year counts only if it CLOSED before that
     * term's own year opened. Without this, a student who finished the 1st semester would
     * read as year 2 while sitting the 2nd semester of the same year — measured against the
     * live demo dataset, where exactly that happened to two records.
     *
     * This is the automatic answer, not a locked one: the department may place a student
     * elsewhere with `decideStanding`, and the issue form opens on this number so a person
     * confirms or corrects it rather than discovering it later.
     */
    public static function derivedYearLevel(int $studentId, ?int $targetTermId = null): int
    {
        return static::derivedYearLevels([$studentId], $targetTermId)[$studentId] ?? 1;
    }

    /**
     * `derivedYearLevel()` for a page of students at once.
     *
     * The Evaluation issue form lists every returning student, and one query per row is
     * the pattern that made the Blocking roster take 343ms. The grouping is the same
     * read, just spread over the whole id list.
     *
     * @param  int[]  $studentIds
     * @return array<int, int> studentId => year level
     */
    public static function derivedYearLevels(array $studentIds, ?int $targetTermId = null): array
    {
        $ids = array_values(array_unique(array_map(fn ($id) => (int) $id, $studentIds)));

        if ($ids === []) {
            return [];
        }

        // The year the student is entering, when the caller knows it. Its opening date is
        // the line a year has to fall behind before it counts as finished.
        $enteredYearOpens = $targetTermId === null
            ? null
            : Academicterms::find($targetTermId)?->academicYear?->startDate?->toDateString();

        $completedYears = static::query()
            ->join('academicterms', 'academicterms.termId', '=', 'enrollments.termId')
            ->join('academicyears', 'academicyears.academicYearId', '=', 'academicterms.academicYearId')
            ->whereIn('enrollments.studentId', $ids)
            ->where('enrollments.enrollmentStatus', EnrollmentStatus::Enrolled->value)
            ->when($enteredYearOpens, fn ($q) => $q->whereDate('academicyears.startDate', '<', $enteredYearOpens))
            ->groupBy('enrollments.studentId')
            ->select('enrollments.studentId')
            ->selectRaw('COUNT(DISTINCT academicyears.academicYearId) as years_completed')
            ->pluck('years_completed', 'studentId')
            ->all();

        $levels = [];
        foreach ($ids as $id) {
            // Five is the ceiling the screens offer and the highest year level the records
            // carry; a longer history cannot place a student above the top year.
            $levels[$id] = min(1 + (int) ($completedYears[$id] ?? 0), 5);
        }

        return $levels;
    }

    /**
     * @return BelongsTo<Admissions, $this>
     */
    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admissions::class, 'admissionId');
    }

    /**
     * @return BelongsTo<Courses, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Courses::class, 'courseId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function evaluatedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'evaluatedBy');
    }

    /**
     * The Evaluation desk staff member who confirmed the student's clearance pass
     * slip (ruling 5). Null means nobody at the department ever confirmed it, which is
     * not the same as it being confirmed and is what stops the Registrar's approval.
     *
     * @return BelongsTo<Staffusers, $this>
     */
    public function clearanceConfirmedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'clearanceConfirmedBy');
    }

    /**
     * @return BelongsTo<Majors, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Majors::class, 'majorId');
    }

    /**
     * Curriculum version this enrollment is pinned to (item 7).
     *
     * @return BelongsTo<Curriculums, $this>
     */
    public function curriculum(): BelongsTo
    {
        return $this->belongsTo(Curriculums::class, 'curriculumId');
    }

    /**
     * @return BelongsTo<Staffusers, $this>
     */
    public function registrarProcessedByUser(): BelongsTo
    {
        return $this->belongsTo(Staffusers::class, 'registrarProcessedBy');
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

    /**
     * @return HasMany<Clinicrecords, $this>
     */
    public function clinicrecords(): HasMany
    {
        return $this->hasMany(Clinicrecords::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Creditedsubjects, $this>
     */
    public function creditedsubjects(): HasMany
    {
        return $this->hasMany(Creditedsubjects::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Documentprintlog, $this>
     */
    public function documentprintlog(): HasMany
    {
        return $this->hasMany(Documentprintlog::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Enrolledsubjects, $this>
     */
    public function enrolledSubjects(): HasMany
    {
        return $this->hasMany(Enrolledsubjects::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Enrollmentstatushistory, $this>
     */
    public function enrollmentstatushistory(): HasMany
    {
        return $this->hasMany(Enrollmentstatushistory::class, 'enrollmentId');
    }

    /**
     * @return HasOne<Enrollmentworkflow, $this>
     */
    public function enrollmentworkflow(): HasOne
    {
        return $this->hasOne(Enrollmentworkflow::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Idrequests, $this>
     */
    public function idrequests(): HasMany
    {
        return $this->hasMany(Idrequests::class, 'enrollmentId');
    }

    /**
     * @return HasMany<Payments, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payments::class, 'enrollmentId');
    }

    /**
     * @return HasOne<Studentassessments, $this>
     */
    public function studentassessments(): HasOne
    {
        return $this->hasOne(Studentassessments::class, 'enrollmentId');
    }
}
