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
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Idrequests::class);

        $base = Enrollments::query()
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereHas('enrollmentworkflow.workflowsteps', fn ($q) => $q
                ->where('stepStatus', WorkflowStepStatus::Pending->value)
                ->where('officeId', OfficeId::IdOffice->value)
                ->whereRaw('stepOrder = (SELECT MIN(ws.stepOrder) FROM workflowsteps ws WHERE ws.workflowId = workflowsteps.workflowId AND ws.stepStatus = ?)', ['pending'])
            )
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)));

        $enrollments = (clone $base)
            ->with(['student', 'course', 'term', 'idrequests', 'enrollmentworkflow'])
            ->orderByDesc('enrollmentId')
            ->paginate(20)->withQueryString();

        // ID-request status counts across the whole filtered set — first
        // request per enrollment, matching what the table shows (audit 2026-09-25).
        $idStats = (clone $base)
            ->join('idrequests', fn ($join) => $join
                ->on('idrequests.enrollmentId', '=', 'enrollments.enrollmentId')
                ->whereRaw('idrequests.idrequestId = (SELECT MIN(ir2.idrequestId) FROM idrequests ir2 WHERE ir2.enrollmentId = enrollments.enrollmentId)'))
            ->selectRaw('idrequests.status as status, count(*) as aggregate')
            ->groupBy('idrequests.status')
            ->pluck('aggregate', 'status');

        return Inertia::render('ID/Index', [
            'enrollments' => $enrollments,
            'stats' => [
                'pending' => (int) ($idStats[IdRequestStatus::Pending->value] ?? 0),
                'validated' => (int) ($idStats[IdRequestStatus::Validated->value] ?? 0),
            ],
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Show ID validation desk.
     * Phase 8: ID request, photo, emergency contact, blood type
     */
    public function show(Enrollments $enrollment): Response
    {
        $this->authorize('id.viewAtDesk', $enrollment);

        $enrollment->load(['student', 'course', 'term', 'idrequests.validatedBy', 'enrollmentworkflow.workflowsteps.office', 'enrollmentworkflow.workflowsteps.signedBy']);

        $idRequest = $enrollment->idrequests->first();

        return Inertia::render('ID/Show', [
            'enrollment' => $enrollment,
            'idRequest' => $idRequest,
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
            'requestDate' => now(),
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

        $idRequest->update(['cardPhotoPath' => $path]);

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
