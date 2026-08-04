<?php

namespace App\Http\Controllers;

use App\Models\Students;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    /**
     * Search students by name or school ID number.
     */
    public function index(Request $request): Response
    {
        $query = Students::query()
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('lastName', 'like', "%{$search}%")
                    ->orWhere('firstName', 'like', "%{$search}%")
                    ->orWhere('middleName', 'like', "%{$search}%")
                    ->orWhere('schoolIdNumber', 'like', "%{$search}%");
            }))
            ->orderBy('lastName')
            ->orderBy('firstName');

        return Inertia::render('Students/Index', [
            'students' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Student 360 view: full enrollment trail for one student.
     */
    public function show(Students $student): Response
    {
        $student->load([
            'religion',
            'addresses',
            'guardians',
            'admissions',
            'enrollments.course',
            'enrollments.major',
            'enrollments.term',
            'enrollments.enrollmentworkflow.workflowsteps.office',
            'enrollments.enrollmentworkflow.workflowsteps.signedBy',
            'enrollments.payments',
            'enrollments.studentassessments',
            'enrollments.enrolledsubjects.subject',
            'studentclearances',
            'examresults',
            'studentids',
            'studentscholarships',
        ]);

        return Inertia::render('Students/Show', [
            'student' => $student,
        ]);
    }
}
