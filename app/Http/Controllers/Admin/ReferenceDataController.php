<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppliesTo;
use App\Enums\CoverageType;
use App\Enums\FeeUnitBasis;
use App\Enums\Semester;
use App\Enums\SemesterOffered;
use App\Enums\SubjectType;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Academicunits;
use App\Models\Academicyears;
use App\Models\Admissionrequirements;
use App\Models\Blocks;
use App\Models\Clearancerequirements;
use App\Models\Courses;
use App\Models\Curriculums;
use App\Models\Curriculumsubjects;
use App\Models\Feetypes;
use App\Models\Majors;
use App\Models\Offices;
use App\Models\Rooms;
use App\Models\Scholarshiptypes;
use App\Models\Subjects;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReferenceDataController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display reference data dashboard.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', Courses::class);

        return Inertia::render('Admin/ReferenceData/Index', [
            'stats' => [
                'courses' => Courses::count(),
                'majors' => Majors::count(),
                'curriculums' => Curriculums::count(),
                'subjects' => Subjects::count(),
                'terms' => Academicterms::count(),
                'feeTypes' => Feetypes::count(),
                'scholarshipTypes' => Scholarshiptypes::count(),
                'offices' => Offices::count(),
                'rooms' => Rooms::count(),
                'blocks' => Blocks::count(),
            ],
        ]);
    }

    // ============ COURSES ============
    public function courses(Request $request): Response
    {
        $this->authorize('manageCourses', Courses::class);

        $courses = Courses::with('unit')->latest()->paginate(20);
        $units = Academicunits::all(['unitId', 'unitName']);

        return Inertia::render('Admin/ReferenceData/Courses', [
            'courses' => $courses,
            'units' => $units,
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);

        $request->validate([
            'unitId' => 'required|exists:academicunits,unitId',
            'courseName' => 'required|string|max:255',
            'courseCode' => 'required|string|max:50|unique:courses,courseCode',
            'requiresEntranceExam' => 'boolean',
            'requiresRetentionExam' => 'boolean',
        ]);

        Courses::create($request->all());

        return back()->with('success', 'Course created.');
    }

    public function updateCourse(Request $request, Courses $course): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);

        $course->update($request->validate([
            'unitId' => 'required|exists:academicunits,unitId',
            'courseName' => 'required|string|max:255',
            'courseCode' => 'required|string|max:50|unique:courses,courseCode,'.$course->courseId.',courseId',
            'requiresEntranceExam' => 'boolean',
            'requiresRetentionExam' => 'boolean',
        ]));

        return back()->with('success', 'Course updated.');
    }

    public function destroyCourse(Courses $course): RedirectResponse
    {
        $this->authorize('manageCourses', Courses::class);
        $course->delete();

        return back()->with('success', 'Course deleted.');
    }

    // ============ MAJORS ============
    public function majors(Request $request): Response
    {
        $this->authorize('manageMajors', Majors::class);

        $majors = Majors::with('course')->latest()->paginate(20);
        $courses = Courses::all(['courseId', 'courseName']);

        return Inertia::render('Admin/ReferenceData/Majors', [
            'majors' => $majors,
            'courses' => $courses,
        ]);
    }

    public function storeMajor(Request $request): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);

        Majors::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Major created.');
    }

    public function updateMajor(Request $request, Majors $major): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);
        $major->update($request->validate(['majorName' => 'required|string|max:255']));

        return back()->with('success', 'Major updated.');
    }

    public function destroyMajor(Majors $major): RedirectResponse
    {
        $this->authorize('manageMajors', Majors::class);
        $major->delete();

        return back()->with('success', 'Major deleted.');
    }

    // ============ CURRICULUMS ============
    public function curriculums(Request $request): Response
    {
        $this->authorize('manageCurriculums', Curriculums::class);

        $curriculums = Curriculums::with(['course', 'major'])->latest()->paginate(20);
        $courses = Courses::all(['courseId', 'courseName']);
        $majors = Majors::all(['majorId', 'majorName']);

        return Inertia::render('Admin/ReferenceData/Curriculums', [
            'curriculums' => $curriculums,
            'courses' => $courses,
            'majors' => $majors,
        ]);
    }

    public function storeCurriculum(Request $request): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);

        Curriculums::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorId' => 'nullable|exists:majors,majorId',
            'effectiveYear' => 'required|date',
            'curriculumName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Curriculum created.');
    }

    public function updateCurriculum(Request $request, Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);
        $curriculum->update($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'majorId' => 'nullable|exists:majors,majorId',
            'effectiveYear' => 'required|date',
            'curriculumName' => 'required|string|max:255',
        ]));

        return back()->with('success', 'Curriculum updated.');
    }

    public function destroyCurriculum(Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculums', Curriculums::class);
        $curriculum->delete();

        return back()->with('success', 'Curriculum deleted.');
    }

    // ============ CURRICULUM SUBJECTS ============
    public function curriculumSubjects(Request $request, Curriculums $curriculum): Response
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);

        $subjects = Curriculumsubjects::with(['subject', 'prerequisiteSubject'])
            ->where('curriculumId', $curriculum->curriculumId)
            ->orderBy('yearLevel')
            ->orderBy('semesterOffered')
            ->get();

        $allSubjects = Subjects::all(['subjectId', 'subjectCode', 'subjectName']);
        $semesters = collect(SemesterOffered::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values();

        return Inertia::render('Admin/ReferenceData/CurriculumSubjects', [
            'curriculum' => $curriculum->load(['course', 'major']),
            'subjects' => $subjects,
            'allSubjects' => $allSubjects,
            'semesters' => $semesters,
        ]);
    }

    public function storeCurriculumSubject(Request $request, Curriculums $curriculum): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);

        Curriculumsubjects::create(array_merge($request->validate([
            'subjectId' => 'required|exists:subjects,subjectId',
            'prerequisiteSubjectId' => 'nullable|exists:subjects,subjectId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'semesterOffered' => 'required|in:1st,2nd,summer',
        ]), ['curriculumId' => $curriculum->curriculumId]));

        return back()->with('success', 'Curriculum subject added.');
    }

    public function updateCurriculumSubject(Request $request, Curriculumsubjects $cs): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);
        $cs->update($request->validate([
            'subjectId' => 'required|exists:subjects,subjectId',
            'prerequisiteSubjectId' => 'nullable|exists:subjects,subjectId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'semesterOffered' => 'required|in:1st,2nd,summer',
        ]));

        return back()->with('success', 'Curriculum subject updated.');
    }

    public function destroyCurriculumSubject(Curriculumsubjects $cs): RedirectResponse
    {
        $this->authorize('manageCurriculumSubjects', Curriculumsubjects::class);
        $cs->delete();

        return back()->with('success', 'Curriculum subject deleted.');
    }

    // ============ SUBJECTS ============
    public function subjects(Request $request): Response
    {
        $this->authorize('manageSubjects', Subjects::class);

        $subjects = Subjects::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/Subjects', [
            'subjects' => $subjects,
            'subjectTypes' => collect(SubjectType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    public function storeSubject(Request $request): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);

        Subjects::create($request->validate([
            'subjectCode' => 'required|string|max:20|unique:subjects,subjectCode',
            'subjectName' => 'required|string|max:255',
            'lectureUnits' => 'required|numeric|min:0',
            'labUnits' => 'required|numeric|min:0',
            'subjectType' => 'required|in:lecture,lab,lectureLab',
        ]));

        return back()->with('success', 'Subject created.');
    }

    public function updateSubject(Request $request, Subjects $subject): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);
        $subject->update($request->validate([
            'subjectCode' => 'required|string|max:20|unique:subjects,subjectCode,'.$subject->subjectId.',subjectId',
            'subjectName' => 'required|string|max:255',
            'lectureUnits' => 'required|numeric|min:0',
            'labUnits' => 'required|numeric|min:0',
            'subjectType' => 'required|in:lecture,lab,lectureLab',
        ]));

        return back()->with('success', 'Subject updated.');
    }

    public function destroySubject(Subjects $subject): RedirectResponse
    {
        $this->authorize('manageSubjects', Subjects::class);
        $subject->delete();

        return back()->with('success', 'Subject deleted.');
    }

    // ============ ACADEMIC TERMS ============
    public function terms(Request $request): Response
    {
        $this->authorize('manageTerms', Academicterms::class);

        $terms = Academicterms::with('academicYear')->latest()->paginate(20);
        $years = Academicyears::all(['academicYearId', 'yearLabel']);

        return Inertia::render('Admin/ReferenceData/Terms', [
            'terms' => $terms,
            'years' => $years,
            'semesters' => collect(Semester::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);

        Academicterms::create($request->validate([
            'academicYearId' => 'required|exists:academicyears,academicYearId',
            'semester' => 'required|in:1st,2nd,summer',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]));

        return back()->with('success', 'Term created.');
    }

    public function updateTerm(Request $request, Academicterms $term): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);
        $term->update($request->validate([
            'academicYearId' => 'required|exists:academicyears,academicYearId',
            'semester' => 'required|in:1st,2nd,summer',
            'startDate' => 'required|date',
            'endDate' => 'required|date|after:startDate',
        ]));

        return back()->with('success', 'Term updated.');
    }

    public function destroyTerm(Academicterms $term): RedirectResponse
    {
        $this->authorize('manageTerms', Academicterms::class);
        $term->delete();

        return back()->with('success', 'Term deleted.');
    }

    // ============ FEE TYPES ============
    public function feeTypes(Request $request): Response
    {
        $this->authorize('manageFeeTypes', Feetypes::class);

        $feeTypes = Feetypes::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/FeeTypes', [
            'feeTypes' => $feeTypes,
            'unitBases' => collect(FeeUnitBasis::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    public function storeFeeType(Request $request): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);

        Feetypes::create($request->validate([
            'feeName' => 'required|string|max:255',
            'defaultAmount' => 'required|numeric|min:0',
            'unitBasis' => 'required|in:perUnit,flat',
        ]));

        return back()->with('success', 'Fee type created.');
    }

    public function updateFeeType(Request $request, Feetypes $feeType): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);
        $feeType->update($request->validate([
            'feeName' => 'required|string|max:255',
            'defaultAmount' => 'required|numeric|min:0',
            'unitBasis' => 'required|in:perUnit,flat',
        ]));

        return back()->with('success', 'Fee type updated.');
    }

    public function destroyFeeType(Feetypes $feeType): RedirectResponse
    {
        $this->authorize('manageFeeTypes', Feetypes::class);
        $feeType->delete();

        return back()->with('success', 'Fee type deleted.');
    }

    // ============ SCHOLARSHIP TYPES ============
    public function scholarshipTypes(Request $request): Response
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);

        $types = Scholarshiptypes::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/ScholarshipTypes', [
            'types' => $types,
            'coverageTypes' => collect(CoverageType::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    public function storeScholarshipType(Request $request): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);

        Scholarshiptypes::create($request->validate([
            'scholarshipName' => 'required|string|max:255',
            'coverageType' => 'required|in:full,partial',
            'coveragePercent' => 'required|numeric|min:0|max:100',
        ]));

        return back()->with('success', 'Scholarship type created.');
    }

    public function updateScholarshipType(Request $request, Scholarshiptypes $type): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);
        $type->update($request->validate([
            'scholarshipName' => 'required|string|max:255',
            'coverageType' => 'required|in:full,partial',
            'coveragePercent' => 'required|numeric|min:0|max:100',
        ]));

        return back()->with('success', 'Scholarship type updated.');
    }

    public function destroyScholarshipType(Scholarshiptypes $type): RedirectResponse
    {
        $this->authorize('manageScholarshipTypes', Scholarshiptypes::class);
        $type->delete();

        return back()->with('success', 'Scholarship type deleted.');
    }

    // ============ OFFICES ============
    public function offices(Request $request): Response
    {
        $this->authorize('manageOffices', Offices::class);

        $offices = Offices::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/Offices', [
            'offices' => $offices,
        ]);
    }

    public function storeOffice(Request $request): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);
        Offices::create($request->validate(['officeName' => 'required|string|max:255']));

        return back()->with('success', 'Office created.');
    }

    public function updateOffice(Request $request, Offices $office): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);
        $office->update($request->validate(['officeName' => 'required|string|max:255']));

        return back()->with('success', 'Office updated.');
    }

    public function destroyOffice(Offices $office): RedirectResponse
    {
        $this->authorize('manageOffices', Offices::class);
        $office->delete();

        return back()->with('success', 'Office deleted.');
    }

    // ============ ROOMS ============
    public function rooms(Request $request): Response
    {
        $this->authorize('manageRooms', Rooms::class);

        $rooms = Rooms::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/Rooms', [
            'rooms' => $rooms,
        ]);
    }

    public function storeRoom(Request $request): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);
        Rooms::create($request->validate([
            'roomName' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'building' => 'nullable|string|max:100',
        ]));

        return back()->with('success', 'Room created.');
    }

    public function updateRoom(Request $request, Rooms $room): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);
        $room->update($request->validate([
            'roomName' => 'required|string|max:100',
            'capacity' => 'required|integer|min:1',
            'building' => 'nullable|string|max:100',
        ]));

        return back()->with('success', 'Room updated.');
    }

    public function destroyRoom(Rooms $room): RedirectResponse
    {
        $this->authorize('manageRooms', Rooms::class);
        $room->delete();

        return back()->with('success', 'Room deleted.');
    }

    // ============ BLOCKS ============
    public function blocks(Request $request): Response
    {
        $this->authorize('manageBlocks', Blocks::class);

        $blocks = Blocks::with(['course', 'term.academicYear'])->latest()->paginate(20);
        $courses = Courses::all(['courseId', 'courseName']);
        $terms = Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']);

        return Inertia::render('Admin/ReferenceData/Blocks', [
            'blocks' => $blocks,
            'courses' => $courses,
            'terms' => $terms,
        ]);
    }

    public function storeBlock(Request $request): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);
        Blocks::create($request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]));

        return back()->with('success', 'Block created.');
    }

    public function updateBlock(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);
        $block->update($request->validate([
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]));

        return back()->with('success', 'Block updated.');
    }

    public function destroyBlock(Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);
        $block->delete();

        return back()->with('success', 'Block deleted.');
    }

    // ============ ADMISSION REQUIREMENTS ============
    public function admissionRequirements(Request $request): Response
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);

        $requirements = Admissionrequirements::latest()->paginate(20);

        return Inertia::render('Admin/ReferenceData/AdmissionRequirements', [
            'requirements' => $requirements,
            'appliesTo' => collect(AppliesTo::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    public function storeAdmissionRequirement(Request $request): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);
        Admissionrequirements::create($request->validate([
            'requirementName' => 'required|string|max:255',
            'appliesTo' => 'required|in:firstYear,transferee,continuing,shifter,all',
            'isRequired' => 'boolean',
        ]));

        return back()->with('success', 'Requirement created.');
    }

    public function updateAdmissionRequirement(Request $request, Admissionrequirements $req): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);
        $req->update($request->validate([
            'requirementName' => 'required|string|max:255',
            'appliesTo' => 'required|in:firstYear,transferee,continuing,shifter,all',
            'isRequired' => 'boolean',
        ]));

        return back()->with('success', 'Requirement updated.');
    }

    public function destroyAdmissionRequirement(Admissionrequirements $req): RedirectResponse
    {
        $this->authorize('manageAdmissionRequirements', Admissionrequirements::class);
        $req->delete();

        return back()->with('success', 'Requirement deleted.');
    }

    // ============ CLEARANCE REQUIREMENTS ============
    public function clearanceRequirements(Request $request): Response
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);

        $requirements = Clearancerequirements::with('office')->latest()->paginate(20);
        $offices = Offices::all(['officeId', 'officeName']);

        return Inertia::render('Admin/ReferenceData/ClearanceRequirements', [
            'requirements' => $requirements,
            'offices' => $offices,
        ]);
    }

    public function storeClearanceRequirement(Request $request): RedirectResponse
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);
        Clearancerequirements::create($request->validate([
            'officeId' => 'required|exists:offices,officeId',
        ]));

        return back()->with('success', 'Clearance requirement created.');
    }

    public function destroyClearanceRequirement(Clearancerequirements $req): RedirectResponse
    {
        $this->authorize('manageClearanceRequirements', Clearancerequirements::class);
        $req->delete();

        return back()->with('success', 'Clearance requirement deleted.');
    }
}
