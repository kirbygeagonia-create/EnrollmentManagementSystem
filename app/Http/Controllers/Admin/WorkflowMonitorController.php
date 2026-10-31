<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentType;
use App\Enums\WorkflowStepStatus;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Enrollmentreturns;
use App\Models\Enrollments;
use App\Models\Offices;
use App\Services\WorkflowService;
use App\Support\WorkflowInbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where every record in the pipeline actually is (development prompt §6.1, §6.3).
 *
 * §6.1 names four places a workflow must be visible, and the fourth — an admin
 * monitoring page — was the one the build never had: the stepper sat on seven desk
 * detail pages, so the state of the *pipeline* was only ever readable one student at
 * a time, by whichever office that student's box belongs to. §6.3 then asks that the
 * admin view show the effective definition and the progress of each active enrollment.
 *
 * So this page answers both halves without inventing anything. The definition a record
 * is walking is printed from its OWN `workflowsteps` rows, which is what §6.3 calls a
 * snapshot, and the version question is stated as open rather than answered: the order
 * is still the one `WorkflowService::stepsFor()` declares, and which phases are
 * mandatory is a SEAIT ruling (matrix G-6, `School-Decision-Sheet-2026-10-27.md`).
 * Nothing here configures a workflow, because configuring one is exactly the act the
 * school has not defined yet.
 *
 * Every number comes from the same helpers the notification path uses
 * (`WorkflowInbox::nextOffice()`, `progress()`), so a desk's bell and this page cannot
 * disagree about whose turn a record is.
 */
class WorkflowMonitorController extends Controller
{
    use AuthorizesRequests;

    /**
     * The seven boxes, in the order the workflow declares them, keyed by office id.
     *
     * Read from `WorkflowService::stepLabels()` rather than from `OfficeId`, because a
     * monitoring page that listed its own offices would be a second definition of the
     * pipeline — the defect this repository removes whenever it finds one.
     *
     * @return array<int, string>
     */
    public static function boxes(): array
    {
        return WorkflowService::stepLabels();
    }

    public function index(Request $request): Response
    {
        $this->authorize('workflow.monitor');

        $boxes = self::boxes();

        $request->validate([
            'office' => ['nullable', 'integer', Rule::in(array_keys($boxes))],
            'status' => ['nullable', Rule::in(array_column(EnrollmentStatus::cases(), 'value'))],
            'studentType' => ['nullable', Rule::in(array_column(StudentType::cases(), 'value'))],
            'term' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $search = static function (Builder $q, string $term): void {
            $q->where(function (Builder $inner) use ($term): void {
                $inner->whereHas('student', static function (Builder $s) use ($term): void {
                    $s->where('lastName', 'like', "%{$term}%")
                        ->orWhere('firstName', 'like', "%{$term}%")
                        ->orWhere('schoolIdNumber', 'like', "%{$term}%");
                })->orWhereHas('course', static function (Builder $c) use ($term): void {
                    $c->where('courseCode', 'like', "%{$term}%")
                        ->orWhere('courseName', 'like', "%{$term}%");
                });
            });
        };

        // A dropped record has left the pipeline: no box may be re-signed on it and the
        // workflow's own box list never answers for it, so it is not "in progress"
        // anywhere. It stays reachable by name through the search, which is what an
        // administrator needs when they are looking for one student's history.
        $query = Enrollments::with([
            'student:studentId,schoolIdNumber,lastName,firstName,middleName',
            'course',
            'term.academicYear',
            'enrollmentworkflow.workflowsteps',
        ])
            ->whereNotIn('enrollmentStatus', [EnrollmentStatus::Dropped->value])
            ->when($request->office, static fn (Builder $q, $office) => self::whereNextBoxAt($q, (int) $office))
            ->when($request->status, static fn (Builder $q, $status) => $q->where('enrollmentStatus', $status))
            ->when($request->studentType, static fn (Builder $q, $type) => $q->where('studentType', $type))
            ->when($request->term, static fn (Builder $q, $term) => $q->where('termId', (int) $term))
            ->when($request->search, $search)
            ->orderByDesc('enrollmentId');

        // Tiles describe the whole filtered set, never the 25 rows on the page, and each
        // is taken from a clone made before paginate() puts a LIMIT/OFFSET on the builder.
        $awaiting = (clone $query)->whereHas(
            'enrollmentworkflow.workflowsteps',
            static fn (Builder $s) => $s->where('stepStatus', WorkflowStepStatus::Pending->value)
        )->count();

        $signedThrough = (clone $query)
            ->whereHas('enrollmentworkflow')
            ->whereDoesntHave(
                'enrollmentworkflow.workflowsteps',
                static fn (Builder $s) => $s->where('stepStatus', WorkflowStepStatus::Pending->value)
            )
            ->count();

        $returned = (clone $query)->whereIn('enrollmentStatus', [
            EnrollmentStatus::ReturnedToEvaluation->value,
            EnrollmentStatus::ReturnedToAdmission->value,
        ])->count();

        // §4's "missing or inaccessible workflow trackers", counted rather than assumed:
        // a record with no form has no boxes, so nothing on any desk page can show where
        // it is. This is the one tile an administrator should never have to read as zero.
        $withoutForm = (clone $query)->whereDoesntHave('enrollmentworkflow')->count();

        $total = (clone $query)->count();
        $page = $query->paginate(25)->withQueryString();

        $officeNames = Offices::query()->pluck('officeName', 'officeId')->all();
        $returns = $this->latestReturns($page->pluck('enrollmentId')->all());

        /** @var Collection<int, Enrollments> $models */
        $models = $page->getCollection();

        $rows = $models->map(function (Enrollments $enrollment) use ($boxes, $officeNames, $returns): array {
            $workflow = $enrollment->enrollmentworkflow;
            $progress = WorkflowInbox::progress($workflow);
            $nextOfficeId = WorkflowInbox::nextOffice($workflow, $enrollment->enrollmentStatus->value);
            $openReturn = $returns[$enrollment->enrollmentId] ?? null;
            $lastActed = $workflow?->workflowsteps
                ->filter(static fn ($step) => $step->signedDate !== null)
                ->max('signedDate');

            return [
                'enrollmentId' => $enrollment->enrollmentId,
                'studentId' => $enrollment->student?->studentId,
                // The name goes to the screen in its two parts, the way every other desk
                // list passes it, so the shared formatter owns the spelling of "Lastname,
                // Firstname" rather than this handler inventing a third.
                'studentLastName' => $enrollment->student?->lastName,
                'studentFirstName' => $enrollment->student?->firstName,
                'schoolIdNumber' => $enrollment->student?->schoolIdNumber,
                'courseCode' => $enrollment->course?->courseCode,
                'termLabel' => $enrollment->term
                    ? 'AY '.$enrollment->term->academicYear?->yearLabel.' · '.$enrollment->term->semester->value.' Sem'
                    : null,
                'studentType' => $enrollment->studentType->value,
                'enrollmentStatus' => $enrollment->enrollmentStatus->value,
                'workflowStatus' => $workflow?->workflowStatus?->value,
                // §6.1's four facts, each from the record's own rows: how far it has come,
                // how many boxes it has, whose turn it is, and what that desk must do.
                'signedBoxes' => $progress['signed'],
                'totalBoxes' => $progress['total'],
                'openBoxes' => $progress['remaining'],
                'nextOfficeId' => $nextOfficeId,
                'nextOfficeName' => $nextOfficeId === null ? null : ($officeNames[$nextOfficeId] ?? null),
                'nextPhaseLabel' => $nextOfficeId === null ? null : ($boxes[$nextOfficeId] ?? null),
                'isLastBox' => $progress['last'],
                // §6.1 asks for a progress indicator AND a readable step list, and says not to
                // lean on colour alone: the chain below is the record's own boxes in order, each
                // with the word the desk would use for it.
                'steps' => $workflow?->workflowsteps
                    ->sortBy('stepOrder')
                    ->values()
                    ->map(static fn ($step) => [
                        'officeId' => $step->officeId,
                        'label' => $boxes[$step->officeId] ?? null,
                        'status' => $step->stepStatus->value,
                        'signedBy' => $step->signedBy,
                        'signedDate' => $step->signedDate?->toIso8601String(),
                    ])
                    ->all() ?? [],
                'signedDate' => $lastActed?->toIso8601String(), // the latest box a desk signed; named for the column it derives from, workflowsteps.signedDate, so a checker that binds a render to a schema type can resolve it
                // The return reason is the record's, not this page's guess: the same row the
                // receiving desk reads through ReturnRecorder::openReturn().
                'returnCategory' => $openReturn?->returnCategory?->value,
                'returnCategoryLabel' => $openReturn?->returnCategory?->label(),
                'returnOriginOfficeName' => $openReturn === null
                    ? null
                    : ($openReturn->originOfficeId === null ? null : ($officeNames[$openReturn->originOfficeId] ?? null)),
                'returnDestinationLabel' => $openReturn?->destination?->label(),
                'returnNote' => $openReturn?->returnNote,
                'returnedAt' => $openReturn?->returnedAt,
            ];
        })->all();

        // The rows this page reads are the derived ones, so the paginator's own meta travels
        // beside them rather than the bare models: handing `$page` straight to Inertia
        // serialises `enrollments` records, and the desk would get a status column with no
        // phase, no next desk and no return reason beside it.
        $listed = [
            'data' => $rows,
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(),
            'total' => $page->total(),
            'links' => $page->linkCollection()->toArray(),
        ];

        return Inertia::render('Admin/WorkflowMonitor/Index', [
            'enrollments' => $listed,
            'stats' => [
                'inView' => $total,
                'awaiting' => $awaiting,
                'signedThrough' => $signedThrough,
                'returned' => $returned,
                'withoutForm' => $withoutForm,
            ],
            'filters' => $request->only(['office', 'status', 'studentType', 'term', 'search']),
            'officeChoices' => array_map(
                static fn (int $officeId, string $label) => [
                    'officeId' => $officeId,
                    'label' => $label,
                    'officeName' => $officeNames[$officeId] ?? null,
                ],
                array_keys($boxes),
                array_values($boxes)
            ),
            // These two enums carry no label() of their own: the words live in the shared
            // status-label module, so the queue badge, this dropdown and the print views all
            // spell one status the same way (§11's common status system).
            'statusChoices' => array_column(EnrollmentStatus::cases(), 'value'),
            'studentTypeChoices' => array_column(StudentType::cases(), 'value'),
            'termChoices' => Academicterms::with('academicYear:academicYearId,yearLabel')
                ->orderByDesc('termId')
                ->get()
                ->map(static fn ($term) => [
                    'termId' => $term->termId,
                    'label' => 'AY '.$term->academicYear?->yearLabel.' · '.$term->semester->value.' Sem',
                ])
                ->all(),
            // The definition the records are walking, stated as what it is: one code list,
            // not a version table. G-6 stays open and says so on the page.
            'definition' => [
                'source' => 'WorkflowService::stepsFor()',
                'boxCount' => count($boxes),
                'versioned' => false,
            ],
        ]);
    }

    /**
     * Keep only the records whose NEXT box — the lowest open step — is at this desk.
     *
     * `whereHas(open box at office)` would also match a record that has a later open box
     * at that desk while another desk is still ahead of it, which would paint a queue the
     * office cannot act on yet. The column beside it reads `WorkflowInbox::nextOffice()`,
     * so the filter and the column must answer one question or the tile contradicts the
     * list it filters (§8).
     */
    private static function whereNextBoxAt(Builder $query, int $officeId): Builder
    {
        return $query->whereExists(static function ($sub) use ($officeId): void {
            $sub->select(DB::raw(1))
                ->from('workflowsteps as nxt')
                ->join('enrollmentworkflow as wf', 'wf.workflowId', '=', 'nxt.workflowId')
                ->whereColumn('wf.enrollmentId', 'enrollments.enrollmentId')
                ->where('nxt.stepStatus', WorkflowStepStatus::Pending->value)
                ->where('nxt.officeId', $officeId)
                ->whereNotExists(static function ($earlier): void {
                    $earlier->select(DB::raw(1))
                        ->from('workflowsteps as ahead')
                        ->whereColumn('ahead.workflowId', 'nxt.workflowId')
                        ->where('ahead.stepStatus', WorkflowStepStatus::Pending->value)
                        ->whereColumn('ahead.stepOrder', '<', 'nxt.stepOrder');
                });
        });
    }

    /**
     * The newest return each record on the page carries, in one query.
     *
     * Ordered newest-first then keyed first-wins, so `unique()` keeps the row the desk
     * would read — a record returned twice shows its current send, not its oldest one.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, Enrollmentreturns>
     */
    private function latestReturns(array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        return Enrollmentreturns::query()
            ->whereIn('enrollmentId', $enrollmentIds)
            ->orderByDesc('returnedAt')
            ->orderByDesc('returnId')
            ->get()
            ->unique('enrollmentId')
            ->keyBy('enrollmentId')
            ->all();
    }
}
