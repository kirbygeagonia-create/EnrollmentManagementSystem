<?php

namespace App\Http\Controllers;

use App\Models\Students;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Search students by name or school ID number.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Students::class);

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
        $this->authorize('view', $student);

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

    /**
     * Quick search JSON endpoint for global command palette.
     */
    public function search(Request $request)
    {
        $this->authorize('quickSearch', Students::class);

        $search = $request->query('query', '');
        if (strlen($search) < 2) {
            return response()->json(['results' => []]);
        }

        $students = Students::with(['enrollments.course', 'enrollments.term'])
            ->where(function ($sq) use ($search) {
                $sq->where('lastName', 'like', "%{$search}%")
                    ->orWhere('firstName', 'like', "%{$search}%")
                    ->orWhere('middleName', 'like', "%{$search}%")
                    ->orWhere('schoolIdNumber', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get()
            ->map(function ($s) {
                $latestEnrollment = $s->enrollments->sortByDesc('enrollmentId')->first();

                return [
                    'studentId' => $s->studentId,
                    'schoolIdNumber' => $s->schoolIdNumber,
                    'fullName' => "{$s->lastName}, {$s->firstName} ".($s->middleName ? "{$s->middleName[0]}." : ''),
                    'course' => $latestEnrollment?->course->courseCode ?? 'N/A',
                    'status' => $latestEnrollment?->enrollmentStatus->value ?? $s->status,
                    'url' => route('students.show', $s->studentId),
                ];
            });

        return response()->json(['results' => $students]);
    }
}
