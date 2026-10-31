<?php

namespace App\Http\Controllers\ID;

use App\Enums\EnrollmentStatus;
use App\Enums\IdRequestReason;
use App\Enums\IdRequestStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
use App\Http\Controllers\Controller;
use App\Models\Enrollments;
use App\Models\Idrequests;
use App\Services\WorkflowService;
use App\Support\DeskQueues;
use App\Support\ListSort;
use App\Support\SchoolTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IDController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WorkflowService $workflowService
    ) {}

    /**
     * Display ID request queue.
     *
     * Two tabs, because the desk asks two questions and one list answered only one. "Queue" is work still
     * open: enrollments whose EARLIEST pending step is the ID office's. "Issued" is work finished: the ID
     * box is signed. Validating a request signs the box, so the act used to remove the record from the one
     * screen that links to it — measured on live `ems` 2026-10-17, seven enrollments carry a signed ID box
     * and six of the eight request rows are validated, while the queue holds two. Those six were reachable
     * only by typing /id/N.
     *
     * A signed box is listed whether or not a request row survives beside it, and the ID Status column reads
     * "None" in that case rather than the row disappearing: a box this office signed with nothing on file is
     * evidence worth seeing. Enrolled rows only — a dropped record is retired work, the same rule
     * `ems:audit:record-states` applies to its box-without-record check.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Idrequests::class);

        $tab = $request->query('tab') === 'issued' ? 'issued' : 'queue';
        $search = $request->search;

        $withSearch = fn ($q) => $q->when($search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq
            ->where('lastName', 'like', "%{$search}%")
            ->orWhere('firstName', 'like', "%{$search}%")
            ->orWhere('schoolIdNumber', $search)));

        // The queue's population comes from App\Support\DeskQueues, which is the builder the dashboard's ID badge
        // counts: the "Awaiting validation" tab and that card are now one predicate rather than two copies of it.
        $queueBase = $withSearch(DeskQueues::id());

        $issuedBase = $withSearch(Enrollments::query()
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', WorkflowStepStatus::Completed->value)
                ->where('officeId', OfficeId::IdOffice->value)
            ));

        // Student Type and Standing ARE offered, though both are ENUM columns. Ruled 2026-10-31: an ENUM may be
        // sorted at the desk, because the school's MySQL orders it by the index each value was declared with, and the
        // declaration order is the one the school wrote into the migration. The consequence falls on the test: the
        // suite compiles the same enum() into a varchar with a CHECK and orders the stored word, so the expectation
        // is read from the connection by Tests\Support\EnumColumnOrder rather than typed into a case.
        //
        // ID Status is the one column left out, and for a reason no ENUM column raises: it is not a column of this
        // row at all. The screen paints the status of the enrollment's FIRST request, so ordering by it would mean
        // deciding what an enrollment with two requests amounts to — a question the desk has never been asked and
        // the school has not answered. It stays absent rather than invented.
        $sortable = [
            'name' => ['column' => 'lastName', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],
            'schoolId' => ['column' => 'schoolIdNumber', 'join' => 'students', 'foreign' => 'studentId', 'local' => 'enrollments.studentId'],
            'course' => ['column' => 'courseName', 'join' => 'courses', 'foreign' => 'courseId', 'local' => 'enrollments.courseId'],
            'studentType' => ['column' => 'enrollments.studentType'],
            'academicStanding' => ['column' => 'enrollments.academicStanding'],
            'enrollmentId' => ['column' => 'enrollments.enrollmentId'],
        ];

        // Clone, as the single-tab version did: paginate() mutates the builder it runs on, and both tiles are
        // computed from these same bases afterwards — so the order is applied to this clone and never to the bases
        // the counts are taken from.
        $list = (clone ($tab === 'issued' ? $issuedBase : $queueBase))
            ->with(['student:studentId,schoolIdNumber,lastName,firstName,middleName', 'course', 'term', 'idrequests', 'enrollmentworkflow']);

        $sort = ListSort::apply(
            $list,
            (string) $request->input('sort', ''),
            (string) $request->input('direction', ''),
            $sortable,
            'enrollmentId',
            'enrollments.enrollmentId'
        );

        $enrollments = $list->paginate(20)->withQueryString();

        // Each tile counts the set its own tab shows. Counting validated rows inside the queue asked one
        // enrollment to hold a signed ID box and a pending one at once, which is why the tile read zero on
        // a database holding six validated requests (audit 2026-09-25 kept the first-request rule).
        $queueStats = $this->firstRequestStats($queueBase);
        $issuedStats = $this->firstRequestStats($issuedBase);

        return Inertia::render('ID/Index', [
            'enrollments' => $enrollments,
            'tab' => $tab,
            'tabs' => [
                'queue' => ['label' => 'Awaiting validation', 'count' => $queueStats['total']],
                'issued' => ['label' => 'Issued', 'count' => $issuedStats['total']],
            ],
            'stats' => [
                'pending' => $queueStats['pending'],
                'validated' => $issuedStats['validated'],
            ],
            // Four keys travel back: the tab the desk is standing on, the search it typed, and the order it asked
            // for. A key the screen sent and the handler never returns is a control that resets while the list
            // stays narrowed by the others — the defect §28.3 owns for filters.
            'filters' => $request->only(['search', 'tab']) + [
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
        ]);
    }

    /**
     * The size of a set, and the status of the first request on each enrollment in it.
     *
     * "First" is the row the desk screen opens — show() loads `$enrollment->idrequests->first()` — so the
     * counts describe what a row on this screen actually leads to.
     *
     * @param  Builder<Enrollments>  $base
     * @return array{total: int, pending: int, validated: int}
     */
    private function firstRequestStats($base): array
    {
        $rows = (clone $base)
            ->join('idrequests', fn ($join) => $join
                ->on('idrequests.enrollmentId', '=', 'enrollments.enrollmentId')
                ->whereRaw('idrequests.idrequestId = (SELECT MIN(ir2.idrequestId) FROM idrequests ir2 WHERE ir2.enrollmentId = enrollments.enrollmentId)'))
            ->selectRaw('idrequests.status as status, count(*) as aggregate')
            ->groupBy('idrequests.status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (clone $base)->count(),
            'pending' => (int) ($rows[IdRequestStatus::Pending->value] ?? 0),
            'validated' => (int) ($rows[IdRequestStatus::Validated->value] ?? 0),
        ];
    }

    /**
     * Show ID validation desk.
     * Phase 8: ID request, photo, emergency contact, blood type
     */
    public function show(Request $request, Enrollments $enrollment): Response
    {
        $this->authorize('id.viewAtDesk', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'idrequests.validatedBy', 'enrollmentworkflow.workflowsteps.office', 'enrollmentworkflow.workflowsteps.signedBy']);

        $idRequest = $enrollment->idrequests->first();

        return Inertia::render('ID/Show', [
            'enrollment' => $enrollment,
            'idRequest' => $idRequest,
            // Which list the officer came from, so the screen can send them back to it. Read off the link the
            // desk screen itself builds (Index passes ?tab=issued), not off the session: an officer who opens
            // /id/92 directly is answering for the queue.
            'backTab' => $request->query('tab') === 'issued' ? 'issued' : 'queue',
            'requestReasons' => collect(IdRequestReason::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => match ($c) {
                    IdRequestReason::NewStudent => 'New Student',
                    IdRequestReason::Shifted => 'Shifted Program',
                    IdRequestReason::Lost => 'Lost Replacement',
                    IdRequestReason::Replaced => 'Damaged Replacement',
                    IdRequestReason::Renewed => 'Annual Renewal',
                },
            ])->values(),
        ]);
    }

    /**
     * Create ID request.
     */
    public function create(Request $request, Enrollments $enrollment): RedirectResponse
    {
        $this->authorize('id.create', $enrollment);

        $validated = $request->validate([
            'requestReason' => 'required|in:newStudent,lost,replaced,renewed,shifted',
            'emergencyContactName' => 'required|string|max:255',
            'emergencyContactNumber' => 'required|string|max:20',
            'bloodType' => 'required|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            // cardPhotoPath is deliberately not accepted here. Ruled 2026-10-10: a typed
            // path satisfied IDPolicy::validate's "must carry a captured face photo" rule
            // without any file behind it, and no form sends this field — the picture only
            // ever arrives through attachPhoto(), which stores it and checks the write.
        ]);

        $idRequest = Idrequests::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'requestReason' => $validated['requestReason'],
            'emergencyContactName' => $validated['emergencyContactName'],
            'emergencyContactNumber' => $validated['emergencyContactNumber'],
            'bloodType' => $validated['bloodType'],
            'requestDate' => SchoolTime::today(),
            'status' => IdRequestStatus::Pending,
        ]);

        return redirect()->route('id.show', $enrollment)->with('success', 'ID request created.');
    }

    /**
     * Attach the captured face photo to the ID request (validation prep).
     */
    public function attachPhoto(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('id.photo.attach', $idRequest);

        $validated = $request->validate([
            'photo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        // Named rather than the default disk: config/filesystems.php and DEPLOYMENT.md §2.
        $path = $request->file('photo')->store('id-photos', 'documents');

        // Audit lane 7: a failed write returns false rather than raising, and the
        // request would then carry a path with no file behind it into validation.
        if ($path === false) {
            return back()->with('error', 'The photo could not be saved, so this request is unchanged and still needs a face photo. Capture or choose the picture again. If it fails a second time the server is short of storage space — tell your office head so they can have it checked.');
        }

        $superseded = $idRequest->cardPhotoPath;

        $idRequest->update(['cardPhotoPath' => $path]);

        // Ruled 2026-10-10: a retake replaces the picture on the record, so the blurred
        // attempt is removed rather than left in the folder holding student photographs.
        // After the update, and only for a path this record no longer uses — a rejected
        // upload never reaches this line, so a failed attempt cannot destroy a good photo.
        if (filled($superseded) && $superseded !== $path) {
            Storage::disk('documents')->delete($superseded);
        }

        return back()->with('success', 'Face photo attached to the ID request.');
    }

    /**
     * Stream the captured face photo back to the desk.
     *
     * The stored path sits on the private documents disk, which no URL reaches at
     * all, so an <img> pointed at it cannot load. This answers on the same policy as
     * the request screen that shows the photo.
     */
    public function photo(Idrequests $idRequest): StreamedResponse
    {
        $this->authorize('view', $idRequest);

        $disk = Storage::disk('documents');
        abort_unless(filled($idRequest->cardPhotoPath) && $disk->exists($idRequest->cardPhotoPath), 404, 'No face photo is stored for this request.');

        return $disk->response($idRequest->cardPhotoPath);
    }

    /**
     * Validate ID: mark the request validated and sign the ID Office
     * workflow step.
     */
    public function validate(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('id.validateRequest', $idRequest);

        DB::transaction(function () use ($idRequest) {
            $idRequest->update([
                'status' => IdRequestStatus::Validated,
                'validatedBy' => Auth::user()->userId,
                'validatedDate' => now(),
            ]);

            // Sign the ID Office workflow step
            $workflow = $idRequest->enrollment->enrollmentworkflow;
            if ($workflow) {
                $this->workflowService->signStepByOffice($workflow, OfficeId::IdOffice->value, Auth::user());
            }
        });

        return back()->with('success', 'ID validated successfully.');
    }

    /**
     * Record what did not match on an open request.
     *
     * Validation is the desk's only affirmative act, and until now it was its
     * only act: an officer who found the face at the window did not match the
     * file, or the emergency contact was wrong, had nowhere on the record to say
     * so. This writes that note against the request while it is still pending,
     * so the correction is attributed to the desk that saw it rather than
     * inventing a "held" status no workflow box can represent.
     */
    public function recordRemark(Request $request, Idrequests $idRequest): RedirectResponse
    {
        $this->authorize('id.remarkRequest', $idRequest);

        $validated = $request->validate([
            'mismatchRemark' => ['required', 'string', 'max:255'],
        ]);

        $idRequest->update(['mismatchRemark' => $validated['mismatchRemark']]);

        return back()->with('success', 'Mismatch recorded on the ID request.');
    }
}
