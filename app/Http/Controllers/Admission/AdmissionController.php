<?php

namespace App\Http\Controllers\Admission;

use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\EnrollmentStatus;
use App\Enums\InstitutionType;
use App\Enums\LevelCompleted;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Addresses;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Documents;
use App\Models\Educationalinstitutions;
use App\Models\Enrollments;
use App\Models\Guardians;
use App\Models\Religions;
use App\Models\Studenteducationalbackgrounds;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use App\Policies\AdmissionPolicy;
use App\Services\EnrollmentIssuer;
use App\Support\StudentRecordDefaults;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display applicant queue (pending/approved).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Admissions::class);

        $query = Admissions::with(['student', 'course', 'term.academicYear', 'evaluatedByUser'])
            ->when($request->status, fn ($q, $status) => $q->where('admissionStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('admissionId');

        $admissions = $query->paginate(20)->withQueryString();

        // Status counts across the whole filtered set — the summary tiles must
        // describe the full queue, not just the current page (audit 2026-09-25).
        $statusCounts = (clone $query)
            ->reorder()
            ->selectRaw('admissionStatus, count(*) as aggregate')
            ->groupBy('admissionStatus')
            ->pluck('aggregate', 'admissionStatus');

        return Inertia::render('Admission/Index', [
            'admissions' => $admissions,
            'filters' => $request->only(['status', 'search']),
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'pending' => (int) ($statusCounts[AdmissionStatus::Pending->value] ?? 0),
                'approved' => (int) ($statusCounts[AdmissionStatus::Approved->value] ?? 0),
                'rejected' => (int) ($statusCounts[AdmissionStatus::Rejected->value] ?? 0),
            ],
        ]);
    }

    /**
     * Show new applicant wizard.
     */
    public function create(): Response
    {
        $this->authorize('create', Admissions::class);

        return Inertia::render('Admission/Create', [
            'courses' => Courses::where('requiresEntranceExam', false)->orWhere('requiresEntranceExam', true)->get(['courseId', 'courseName', 'courseCode', 'requiresEntranceExam']),
            'terms' => Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']),
            'religions' => Religions::all(['religionId', 'religionName']),
        ]);
    }

    /**
     * Store new applicant (creates student + admission).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Admissions::class);

        $validated = $request->validate([
            'schoolIdNumber' => 'required|string|max:50|unique:students,schoolIdNumber',
            'lastName' => 'required|string|max:100',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'suffix' => 'nullable|string|max:20',
            'gender' => 'required|in:male,female',
            'birthdate' => 'required|date',
            'birthplace' => 'required|string|max:255',
            'citizenship' => 'required|string|max:100',
            'religionId' => 'required|exists:religions,religionId',
            'civilStatus' => 'required|in:single,married,widowed,separated',
            'contactNumber' => 'required|string|max:20',
            'telephoneNumber' => 'nullable|string|max:20',
            'email' => 'required|email|max:255|unique:students,email',
            'username' => 'required|string|max:50|unique:students,username',
            'password' => 'required|string|min:8|confirmed',
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'applicantType' => ['required', Rule::enum(ApplicantType::class)],
            'addresses' => 'required|array|min:1',
            'addresses.*.addressType' => 'required|in:home,current,permanent',
            'addresses.*.houseBuildingNo' => 'nullable|string|max:100',
            'addresses.*.street' => 'nullable|string|max:255',
            'addresses.*.sitioPurok' => 'nullable|string|max:100',
            'addresses.*.barangay' => 'required|string|max:100',
            'addresses.*.cityMunicipality' => 'required|string|max:100',
            'addresses.*.district' => 'nullable|string|max:100',
            'addresses.*.province' => 'required|string|max:100',
            'addresses.*.region' => 'nullable|string|max:100',
            'addresses.*.zipCode' => 'nullable|string|max:20',
            'addresses.*.country' => 'required|string|max:100',
            'guardians' => 'required|array|min:1',
            'guardians.*.relationship' => 'required|in:mother,father,guardian,other',
            'guardians.*.fullName' => 'required|string|max:255',
            'guardians.*.contactNumber' => 'required|string|max:20',
            'guardians.*.email' => 'nullable|email|max:255',
            'guardians.*.isEmergencyContact' => 'boolean',
            'guardians.*.isAuthorizedToActOnBehalf' => 'boolean',
            'educationalBackgrounds' => 'nullable|array',
            'educationalBackgrounds.*.institutionName' => 'required_with:educationalBackgrounds|string|max:255',
            'educationalBackgrounds.*.institutionType' => ['required_with:educationalBackgrounds', Rule::enum(InstitutionType::class)],
            // educationalinstitutions.cityMunicipality / .province are NOT NULL, so a
            // background row cannot be stored without them.
            'educationalBackgrounds.*.cityMunicipality' => 'required_with:educationalBackgrounds|string|max:100',
            'educationalBackgrounds.*.province' => 'required_with:educationalBackgrounds|string|max:100',
            'educationalBackgrounds.*.levelCompleted' => ['required_with:educationalBackgrounds', Rule::enum(LevelCompleted::class)],
            'educationalBackgrounds.*.strandTrack' => 'nullable|string|max:100',
            // studenteducationalbackgrounds.yearCompleted is a NOT NULL date.
            'educationalBackgrounds.*.yearCompleted' => 'required_with:educationalBackgrounds|date',
            'educationalBackgrounds.*.honorsCertifications' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($validated) {
            $student = Students::create(StudentRecordDefaults::person([
                'schoolIdNumber' => $validated['schoolIdNumber'],
                'lastName' => $validated['lastName'],
                'firstName' => $validated['firstName'],
                'middleName' => $validated['middleName'] ?? null,
                'suffix' => $validated['suffix'] ?? null,
                'gender' => $validated['gender'],
                'birthdate' => $validated['birthdate'],
                'birthplace' => $validated['birthplace'],
                'citizenship' => $validated['citizenship'],
                'religionId' => $validated['religionId'],
                'civilStatus' => $validated['civilStatus'],
                'contactNumber' => $validated['contactNumber'],
                'telephoneNumber' => $validated['telephoneNumber'] ?? null,
                'email' => $validated['email'],
                'username' => $validated['username'],
                'passwordHash' => bcrypt($validated['password']),
                'status' => 'active',
                'semestersCompleted' => 0,
                'yearsInInstitution' => 0,
            ]));

            foreach ($validated['addresses'] as $addr) {
                Addresses::create(StudentRecordDefaults::address(array_merge($addr, ['studentId' => $student->studentId])));
            }

            foreach ($validated['guardians'] as $guardian) {
                Guardians::create(StudentRecordDefaults::guardian(array_merge($guardian, ['studentId' => $student->studentId])));
            }

            if (! empty($validated['educationalBackgrounds'])) {
                foreach ($validated['educationalBackgrounds'] as $bg) {
                    $institution = Educationalinstitutions::firstOrCreate(
                        ['institutionName' => $bg['institutionName']],
                        [
                            'institutionType' => $bg['institutionType'],
                            'cityMunicipality' => $bg['cityMunicipality'],
                            'province' => $bg['province'],
                        ]
                    );
                    Studenteducationalbackgrounds::create([
                        'studentId' => $student->studentId,
                        'institutionId' => $institution->institutionId,
                        'levelCompleted' => $bg['levelCompleted'],
                        'strandTrack' => $bg['strandTrack'] ?? '',
                        'yearCompleted' => $bg['yearCompleted'],
                        'honorsCertifications' => $bg['honorsCertifications'] ?? '',
                        'supportingDocumentPath' => $bg['supportingDocumentPath'] ?? '',
                    ]);
                }
            }

            $admission = Admissions::create([
                'studentId' => $student->studentId,
                'courseId' => $validated['courseId'],
                'termId' => $validated['termId'],
                'applicantType' => $validated['applicantType'],
                'admissionStatus' => AdmissionStatus::Pending->value,
            ]);

            // Create requirement submissions
            $requirements = Admissionrequirements::where('appliesTo', $validated['applicantType'])
                ->orWhere('appliesTo', 'all')
                ->get();

            foreach ($requirements as $req) {
                Studentrequirementsubmissions::create([
                    'admissionId' => $admission->admissionId,
                    'requirementId' => $req->requirementId,
                    'submissionStatus' => 'pending',
                    'submittedDate' => now(),
                    'remarks' => '',
                ]);
            }
        });

        return redirect()->route('admission.index')->with('success', 'Applicant registered successfully.');
    }

    /**
     * Show admission details with requirements.
     */
    public function show(Admissions $admission, AdmissionPolicy $admissionPolicy): Response
    {
        $this->authorize('view', $admission);

        $admission->load([
            'student.addresses',
            'student.guardians',
            'student.educationalBackgrounds.institution',
            'course',
            'term.academicYear',
            'requirementSubmissions.requirement',
            'requirementSubmissions.documents',
            'documents',
            'examResults',
        ]);

        return Inertia::render('Admission/Show', [
            'admission' => $admission,
            'requirements' => Admissionrequirements::where('appliesTo', $admission->applicantType)
                ->orWhere('appliesTo', 'all')
                ->get(),
            // The gate's own readiness list. Approve is closed until this is
            // empty, and the desk prints these reasons rather than letting the
            // officer discover them by clicking into a 403.
            'approvalBlockers' => $admissionPolicy->approvalBlockers($admission),
        ]);
    }

    /**
     * Stream one uploaded requirement document.
     *
     * Uploads go to the default disk, which is private: it is served only with a
     * signed URL, so a plain link to the stored path cannot work. Answering here
     * keeps the file behind the same policy as its admission, which is what lets
     * the desk open the paper before it signs the requirement off.
     */
    public function document(Documents $document): StreamedResponse
    {
        $admission = $document->submission?->admission;
        abort_unless($admission !== null, 404, 'This document no longer belongs to an admission.');
        $this->authorize('view', $admission);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless(filled($document->fileUrl) && $disk->exists($document->fileUrl), 404, 'The stored file is missing from disk.');

        return $disk->response($document->fileUrl);
    }

    /**
     * Submit requirement document.
     */
    public function submitRequirement(Request $request, Admissions $admission, Admissionrequirements $requirement): RedirectResponse
    {
        $this->authorize('submitRequirements', $admission);

        $validated = $request->validate([
            // Audit §3.4: mirror the frontend's accepted document types
            // (Admission/Show.jsx restricts the picker to these extensions);
            // backend must enforce the same allow-list.
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx',
            'remarks' => 'nullable|string',
        ]);

        $submission = $admission->requirementSubmissions()
            ->where('requirementId', $requirement->requirementId)
            ->firstOrFail();

        $disk = config('filesystems.default', 'public');
        $path = $request->file('file')->store('admission-documents', $disk);

        DB::transaction(function () use ($submission, $path, $request, $validated) {
            Documents::create([
                'submissionId' => $submission->submissionId,
                'fileUrl' => $path,
                'fileType' => $request->file('file')->getMimeType(),
                'uploadedDate' => now(),
            ]);

            $submission->update([
                'submissionStatus' => 'submitted',
                'submittedDate' => now(),
                'remarks' => $validated['remarks'] ?? null,
            ]);
        });

        return back()->with('success', 'Document submitted successfully.');
    }

    /**
     * Verify requirement.
     */
    public function verifyRequirement(Request $request, Admissions $admission, Admissionrequirements $requirement): RedirectResponse
    {
        $this->authorize('verifyRequirements', $admission);

        $submission = $admission->requirementSubmissions()
            ->where('requirementId', $requirement->requirementId)
            ->firstOrFail();

        DB::transaction(function () use ($submission, $request) {
            $submission->update([
                'submissionStatus' => $request->boolean('approved') ? 'verified' : 'rejected',
                'remarks' => $request->remarks,
            ]);
        });

        return back()->with('success', 'Requirement verified.');
    }

    /**
     * Approve admission.
     */
    public function approve(Admissions $admission): RedirectResponse
    {
        $this->authorize('approve', $admission);

        $enrollment = DB::transaction(function () use ($admission) {
            $admission->update([
                'admissionStatus' => 'approved',
                'evaluatedBy' => Auth::user()->userId,
                'evaluatedDate' => now(),
            ]);

            // Ruling 3 (G-4): one active enrollment per student per term, read at every
            // creation point. The lookup is by student and term rather than by admission,
            // because the defect was two active rows holding one seat — not two rows for
            // one application. A dropped record does not hold the seat, so a student the
            // Registrar dropped (ruling 17) can be enrolled in the term again.
            $standing = EnrollmentIssuer::seatHolder((int) $admission->studentId, (int) $admission->termId);

            if ($standing !== null) {
                return $standing;
            }

            // academicStanding is deliberately left out: whether the student is
            // regular or irregular is an academic judgement the Department
            // Evaluation desk makes from the grades on file, and the Registrar
            // confirms it at approval. Stamping "regular" here pre-decided it
            // on an admission officer's say-so and made every document that
            // prints the standing report an unverified default.
            return Enrollments::create([
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'admissionId' => $admission->admissionId,
                // G-2: the record decides, not a constant. For an applicant this is year 1
                // because they have no completed year to point at — which is the same answer
                // the old hard-code gave — but a student returning after a leave or a
                // credited year is placed where their own history puts them instead of
                // arriving one year short and being corrected downstream.
                'yearLevel' => Enrollments::derivedYearLevel(
                    (int) $admission->studentId,
                    (int) $admission->termId
                ),
                'studentType' => $admission->applicantType->value,
                'enrollmentType' => 'new',
                'evaluatedBy' => Auth::user()->userId,
                'enrollmentStatus' => EnrollmentStatus::Pending,
                // Item 7: the version the record is priced against is fixed at creation.
                // Left null it falls through to "the newest catalog", which is a tie the
                // database breaks arbitrarily — and the subjects, the load band and the
                // fee sheet all read that choice.
                'curriculumId' => Curriculums::currentFor($admission->courseId)?->curriculumId,
            ]);
        });

        return back()->with('success', $enrollment->wasRecentlyCreated
            ? 'Admission approved and student moved to Evaluation queue.'
            : "Admission approved — this student already holds enrollment #{$enrollment->enrollmentId} in the term, so no second enrollment was created.");
    }

    /**
     * Reject admission.
     */
    public function reject(Request $request, Admissions $admission): RedirectResponse
    {
        $this->authorize('reject', $admission);

        DB::transaction(function () use ($admission) {
            $admission->update([
                'admissionStatus' => 'rejected',
                'evaluatedBy' => Auth::user()->userId,
                'evaluatedDate' => now(),
            ]);
        });

        return back()->with('success', 'Admission rejected.');
    }
}
