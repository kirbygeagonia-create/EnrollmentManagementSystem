<?php

namespace App\Http\Controllers\Admission;

use App\Enums\AcademicStanding;
use App\Enums\AdmissionStatus;
use App\Enums\ApplicantType;
use App\Enums\EnrollmentStatus;
use App\Enums\InstitutionType;
use App\Enums\LevelCompleted;
use App\Enums\StudentType;
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
use App\Services\EnrollmentStateMachine;
use App\Services\ReturnRecorder;
use App\Support\CollegeScope;
use App\Support\SchoolTime;
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
     * Display applicant queue — submitted cases and the saved drafts, counted over whatever the desk filtered to.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Admissions::class);

        // Ruled 2026-10-13: the department also receives the applicants to its own college — an
        // application is the only record a not-yet-enrolled student has, and it names a course.
        $query = CollegeScope::ofAdmissions(
            Admissions::with(['student', 'course', 'term.academicYear', 'evaluatedByUser']),
            $request->user()
        )
            ->when($request->status, fn ($q, $status) => $q->where('admissionStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->orderByDesc('admissionId');

        // Status counts across the whole filtered set — the summary tiles must
        // describe the full queue, not just the current page (audit 2026-09-25).
        //
        // Taken before paginate(), not after: paginate() calls forPage(), which sets limit and offset on the
        // builder it is called on, so a clone made afterwards carries "LIMIT 20 OFFSET 20" and answers with
        // nothing once the desk leaves page 1. AccountingController has always cloned first; these three did
        // not, and page 1 is not evidence either way.
        $statusCounts = (clone $query)
            ->reorder()
            ->selectRaw('admissionStatus, count(*) as aggregate')
            ->groupBy('admissionStatus')
            ->pluck('aggregate', 'admissionStatus');

        $admissions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admission/Index', [
            'admissions' => $admissions,
            'filters' => $request->only(['status', 'search']),
            'stats' => [
                'total' => (int) $statusCounts->sum(),
                'draft' => (int) ($statusCounts[AdmissionStatus::Draft->value] ?? 0),
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

        // §7.1 of the development prompt: "Incomplete intake can be saved as a Draft / Incomplete record and
        // resumed later without re-entering all data." The desk chooses which of the two it is doing, on the
        // same form.
        //
        // A draft still has to name the person, because students.schoolIdNumber, lastName, firstName,
        // middleName, suffix, gender, birthdate, birthplace, citizenship, contactNumber, email, username and
        // passwordHash are NOT NULL without a default — a placeholder would be invented data, and in the
        // password case an invented credential. What a draft defers is the sections a returning applicant
        // brings to the counter later: addresses, guardians, and the educational background.
        $asDraft = $request->input('intakeAction') === 'draft';

        $rules = [
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
        ];

        if ($asDraft) {
            $rules['addresses'] = 'nullable|array';
            $rules['guardians'] = 'nullable|array';

            // The wizard seeds one row per section so the desk has something to type into, pre-selects the
            // picker (addressType home, relationship mother) and pre-fills the country — and posts every row
            // it was given. So relaxing the section alone is not enough: the untouched row still arrives and
            // its own barangay/city/province rules refuse the very draft whose purpose is to defer them.
            //
            // A row is untouched when every field the desk has to type is blank. Anything else is kept, so a
            // half-written address is still named rather than silently discarded.
            $typed = [
                'addresses' => ['barangay', 'cityMunicipality', 'province'],
                'guardians' => ['fullName', 'contactNumber'],
            ];

            foreach ($typed as $section => $fields) {
                $rows = array_values(array_filter(
                    (array) $request->input($section, []),
                    function ($row) use ($fields): bool {
                        return is_array($row) && collect($fields)->contains(fn (string $field): bool => filled($row[$field] ?? null));
                    }
                ));

                $request->merge([$section => $rows === [] ? null : $rows]);
            }
        }

        $validated = $request->validate($rules);

        // The two loops below run for both paths, so an absent section has to read as an empty one rather
        // than as an undefined index.
        $validated['addresses'] ??= [];
        $validated['guardians'] ??= [];

        $admission = DB::transaction(function () use ($validated, $asDraft) {
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
                'admissionStatus' => $asDraft ? AdmissionStatus::Draft->value : AdmissionStatus::Pending->value,
                // The school's civil day, not the daemon's: a desk orders the resumable cases by this date
                // (lane 51's rule — never compare now() to a DATE column).
                'draftedDate' => $asDraft ? SchoolTime::today() : null,
            ]);

            // The checklist is seeded for a draft too. Admission/Show.jsx renders the requirement *catalogue*
            // and looks its submission up, so a case with no rows still offers an attach button, and
            // submitRequirement() answers that with a 404 on firstOrFail(). Seeding here is what makes
            // §7.1's "resumed later" the useful act: the applicant who arrives next week with the missing
            // paper is met at the same counter, without the case being pushed into a desk queue it is not
            // ready for.
            $this->seedRequirementSubmissions($admission);

            return $admission;
        });

        return $asDraft
            ? redirect()->route('admission.show', $admission)->with('success', 'Intake saved as a draft — the applicant is not in any desk queue yet.')
            : redirect()->route('admission.index')->with('success', 'Applicant registered successfully.');
    }

    /**
     * One submission row per requirement this applicant's own type is asked for.
     *
     * Called by both paths of store() and again by submitDraft(), so the set of rows a case carries can never
     * depend on which button the desk pressed first. It is idempotent because the table has no unique key on
     * (admissionId, requirementId) — measured on live `ems` — so nothing else stops a second row for the same
     * requirement, and a requirement added to the catalogue while a case sat as a draft has to be picked up
     * when that case is resumed.
     *
     * @return int how many rows were created
     */
    private function seedRequirementSubmissions(Admissions $admission): int
    {
        $requirements = Admissionrequirements::where('appliesTo', $admission->applicantType->value)
            ->orWhere('appliesTo', 'all')
            ->get()
            ->reject(function (Admissionrequirements $req) use ($admission): bool {
                return Studentrequirementsubmissions::where('admissionId', $admission->admissionId)
                    ->where('requirementId', $req->requirementId)
                    ->exists();
            });

        foreach ($requirements as $req) {
            Studentrequirementsubmissions::create([
                'admissionId' => $admission->admissionId,
                'requirementId' => $req->requirementId,
                'submissionStatus' => 'pending',
                'submittedDate' => now(),
                'remarks' => '',
            ]);
        }

        return $requirements->count();
    }

    /**
     * Move a draft into the queue (§7.1's "resumed later", and §14's acceptance case).
     *
     * AdmissionPolicy::update() is the right it asks for — it is the permission-only method on that policy,
     * which is what makes a status transition the natural use of it. The refusal below is the part that must
     * not be inferred: a draft with no address and no guardian is not a complete intake, and pushing it into
     * the queue would hand the next desk a record it can only reject.
     */
    public function submitDraft(Request $request, Admissions $admission): RedirectResponse
    {
        $this->authorize('update', $admission);

        if ($admission->admissionStatus !== AdmissionStatus::Draft) {
            return back()->withErrors([
                'admissionStatus' => 'Only a saved draft can be submitted; this record is '.$admission->admissionStatus->value.'.',
            ]);
        }

        $admission->loadMissing(['student.addresses', 'student.guardians']);

        // A case whose student row is gone holds no address either, so the same answer is given for both
        // halves rather than letting a missing relation read as "nothing is missing".
        $student = $admission->student;
        $missing = [];

        if ($student === null || $student->addresses->isEmpty()) {
            $missing[] = 'an address';
        }

        if ($student === null || $student->guardians->isEmpty()) {
            $missing[] = 'a guardian or emergency contact';
        }

        if ($missing !== []) {
            return back()->withErrors([
                'intake' => 'This draft cannot be submitted yet — the record still needs '.implode(' and ', $missing).'.',
            ]);
        }

        DB::transaction(function () use ($admission) {
            $admission->update(['admissionStatus' => AdmissionStatus::Pending->value]);

            // Idempotent: the draft already carries a row per requirement, and this picks up any requirement
            // the catalogue gained while the case was parked.
            $this->seedRequirementSubmissions($admission);
        });

        return redirect()->route('admission.show', $admission)
            ->with('success', 'Intake submitted — the applicant is now in the Admission queue.');
    }

    /**
     * Show admission details with requirements.
     */
    public function show(Request $request, Admissions $admission, AdmissionPolicy $admissionPolicy): Response
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
            // §6.4 (gap G-10): Admission returns the record its approval issued. Its page is about
            // the application, so the target — the record, the destinations it allows, and whether
            // this account may return it at all — is resolved server-side here rather than guessed at
            // by the screen; null means there is nothing for THIS desk to send anywhere.
            'returnTarget' => ReturnRecorder::admissionTarget($admission, $request->user()),
            // Which acts this record actually allows, asked of the same policy the routes ask. The page used
            // to decide by the submission's own status word, so it offered Verify on a draft — and on an
            // approved case — where verifyRequirements() answers 403: work the save refuses.
            'deskActions' => [
                'submitRequirements' => $request->user()->can('submitRequirements', $admission),
                'verifyRequirements' => $request->user()->can('verifyRequirements', $admission),
                'submitDraft' => $admission->admissionStatus === AdmissionStatus::Draft
                    && $request->user()->can('update', $admission),
            ],
        ]);
    }

    /**
     * Stream one uploaded requirement document.
     *
     * Uploads go to the named private documents disk, which no URL reaches, so a
     * plain link to the stored path cannot work. Answering here keeps the file
     * behind the same policy as its admission, which is what lets the desk open the
     * paper before it signs the requirement off.
     */
    public function document(Documents $document): StreamedResponse
    {
        $admission = $document->submission?->admission;
        abort_unless($admission !== null, 404, 'This document no longer belongs to an admission.');
        $this->authorize('view', $admission);

        $disk = Storage::disk('documents');
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

        // Named rather than the default disk: config/filesystems.php and DEPLOYMENT.md §2.
        $path = $request->file('file')->store('admission-documents', 'documents');

        // Audit lane 7: the disk sets throw => false, so a failed write returns false
        // instead of raising — and the row would be recorded over a paper never written.
        if ($path === false) {
            return back()->with('error', 'Your file could not be saved, so nothing was submitted and this requirement is still waiting for it. Please attach the paper again. If it fails a second time the server is short of storage space — tell your office head so they can have it checked.');
        }

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
                // The desk re-approving an application whose student already holds the seat resumes that
                // record when — and only when — the seat-holder is sitting in `returnedToAdmission`, the
                // status G-10's own return act writes for this destination. Nothing else in the
                // application reaches it: the machine declares `returnedToAdmission => ['pending']` and no
                // other call site takes that edge, so without this branch a record the Admission desk sent
                // back can be approved again and still never re-enter the pipeline.
                if ($standing->enrollmentStatus === EnrollmentStatus::ReturnedToAdmission) {
                    app(EnrollmentStateMachine::class)->transition(
                        $standing,
                        EnrollmentStatus::Pending,
                        Auth::user(),
                        'Admission approved again after the record was returned to Admission (prompt §6.4, gap G-10)'
                    );
                }

                return $standing;
            }

            // C-2, ruled 2026-10-06: a transferee arrives Irregular — subjects carried in from
            // another school are not this program's regular progression, and the owner ruled the
            // type itself decides rather than leaving it to be noticed later. Every other
            // applicant type still leaves academicStanding for the desks: whether a first-year is
            // regular is an academic judgement the Department Evaluation desk makes from the
            // grades on file and the Registrar confirms at approval (item 16), and stamping a
            // default here would pre-decide it on an admission officer's say-so.
            return Enrollments::create([
                'studentId' => $admission->studentId,
                'courseId' => $admission->courseId,
                'termId' => $admission->termId,
                'admissionId' => $admission->admissionId,
                'academicStanding' => StudentType::from($admission->applicantType->value)->arrivesIrregular()
                    ? AcademicStanding::Irregular
                    : null,
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
