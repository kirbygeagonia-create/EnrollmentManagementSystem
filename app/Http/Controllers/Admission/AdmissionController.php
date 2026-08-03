<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Addresses;
use App\Models\Admissionrequirements;
use App\Models\Admissions;
use App\Models\Courses;
use App\Models\Documents;
use App\Models\Educationalinstitutions;
use App\Models\Guardians;
use App\Models\Religions;
use App\Models\Studenteducationalbackgrounds;
use App\Models\Studentrequirementsubmissions;
use App\Models\Students;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdmissionController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display applicant queue (pending/approved).
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Admissions::class);

        $query = Admissions::with(['student', 'course', 'term', 'evaluatedBy'])
            ->when($request->status, fn ($q, $status) => $q->where('admissionStatus', $status))
            ->when($request->search, fn ($q, $search) => $q->whereHas('student', fn ($sq) => $sq->where('lastName', 'like', "%{$search}%")->orWhere('firstName', 'like', "%{$search}%")->orWhere('schoolIdNumber', $search)))
            ->latest();

        $admissions = $query->paginate(20)->withQueryString();

        return Inertia::render('Admission/Index', [
            'admissions' => $admissions,
            'filters' => $request->only(['status', 'search']),
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
            'applicantType' => 'required|in:firstYear,transferee,continuing,shifter',
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
            'educationalBackgrounds.*.institutionType' => 'required_with:educationalBackgrounds|in:elementary,secondary,seniorHigh,college,graduate',
            'educationalBackgrounds.*.cityMunicipality' => 'nullable|string|max:100',
            'educationalBackgrounds.*.province' => 'nullable|string|max:100',
            'educationalBackgrounds.*.levelCompleted' => 'required_with:educationalBackgrounds|in:elementary,secondary,seniorHigh,college,graduate',
            'educationalBackgrounds.*.strandTrack' => 'nullable|string|max:100',
            'educationalBackgrounds.*.yearCompleted' => 'nullable|date',
            'educationalBackgrounds.*.honorsCertifications' => 'nullable|string|max:500',
        ]);

        $student = Students::create([
            'schoolIdNumber' => $validated['schoolIdNumber'],
            'lastName' => $validated['lastName'],
            'firstName' => $validated['firstName'],
            'middleName' => $validated['middleName'],
            'suffix' => $validated['suffix'],
            'gender' => $validated['gender'],
            'birthdate' => $validated['birthdate'],
            'birthplace' => $validated['birthplace'],
            'citizenship' => $validated['citizenship'],
            'religionId' => $validated['religionId'],
            'civilStatus' => $validated['civilStatus'],
            'contactNumber' => $validated['contactNumber'],
            'telephoneNumber' => $validated['telephoneNumber'],
            'email' => $validated['email'],
            'username' => $validated['username'],
            'passwordHash' => bcrypt($validated['password']),
            'status' => 'active',
            'semestersCompleted' => 0,
            'yearsInInstitution' => 0,
        ]);

        foreach ($validated['addresses'] as $addr) {
            Addresses::create(array_merge($addr, ['studentId' => $student->studentId]));
        }

        foreach ($validated['guardians'] as $guardian) {
            Guardians::create(array_merge($guardian, ['studentId' => $student->studentId]));
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
                    'strandTrack' => $bg['strandTrack'],
                    'yearCompleted' => $bg['yearCompleted'],
                    'honorsCertifications' => $bg['honorsCertifications'],
                ]);
            }
        }

        $admission = Admissions::create([
            'studentId' => $student->studentId,
            'courseId' => $validated['courseId'],
            'termId' => $validated['termId'],
            'applicantType' => $validated['applicantType'],
            'admissionStatus' => 'pending',
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
            ]);
        }

        return redirect()->route('admission.index')->with('success', 'Applicant registered successfully.');
    }

    /**
     * Show admission details with requirements.
     */
    public function show(Admissions $admission): Response
    {
        $this->authorize('view', $admission);

        $admission->load([
            'student.addresses',
            'student.guardians',
            'student.educationalBackgrounds.institution',
            'course',
            'term.academicYear',
            'requirementSubmissions.requirement',
            'documents',
            'examResults',
        ]);

        return Inertia::render('Admission/Show', [
            'admission' => $admission,
            'requirements' => Admissionrequirements::where('appliesTo', $admission->applicantType)
                ->orWhere('appliesTo', 'all')
                ->get(),
        ]);
    }

    /**
     * Submit requirement document.
     */
    public function submitRequirement(Request $request, Admissions $admission, Admissionrequirements $requirement): RedirectResponse
    {
        $this->authorize('submitRequirements', $admission);

        $validated = $request->validate([
            'file' => 'required|file|max:10240',
            'remarks' => 'nullable|string',
        ]);

        $submission = $admission->requirementSubmissions()
            ->where('requirementId', $requirement->requirementId)
            ->firstOrFail();

        $path = $request->file('file')->store('admission-documents', 's3');

        Documents::create([
            'submissionId' => $submission->submissionId,
            'fileUrl' => $path,
            'fileType' => $request->file('file')->getMimeType(),
            'uploadedDate' => now(),
        ]);

        $submission->update([
            'submissionStatus' => 'submitted',
            'submittedDate' => now(),
            'remarks' => $validated['remarks'],
        ]);

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

        $submission->update([
            'submissionStatus' => $request->boolean('approved') ? 'verified' : 'rejected',
            'remarks' => $request->remarks,
        ]);

        return back()->with('success', 'Requirement verified.');
    }

    /**
     * Approve admission.
     */
    public function approve(Admissions $admission): RedirectResponse
    {
        $this->authorize('approve', $admission);

        $admission->update([
            'admissionStatus' => 'approved',
            'evaluatedBy' => Auth::id(),
            'evaluatedDate' => now(),
        ]);

        return back()->with('success', 'Admission approved.');
    }

    /**
     * Reject admission.
     */
    public function reject(Request $request, Admissions $admission): RedirectResponse
    {
        $this->authorize('reject', $admission);

        $admission->update([
            'admissionStatus' => 'rejected',
            'evaluatedBy' => Auth::id(),
            'evaluatedDate' => now(),
        ]);

        return back()->with('success', 'Admission rejected.');
    }
}
