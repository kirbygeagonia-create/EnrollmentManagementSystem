<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Students;
use App\Support\CollegeScope;
use App\Support\ListSort;
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

        // Ruled 2026-10-13: the directory and the quick search answer to the same boundary as the policy
        // on this file's `show`, so a desk is never offered a row its own screen then refuses.
        //
        // The Status dropdown once offered graduated and dropped, which `students.status` cannot hold —
        // `enum('active','inactive')`, and the page sent the key while this handler never read it, so
        // picking either option listed every student and then reset the control. A value outside the
        // vocabulary narrows nothing, and the echo below is what tells the desk that: `filters` reports
        // the status actually applied, never the one merely typed.
        $status = in_array($request->query('status'), array_column(StudentStatus::cases(), 'value'), true)
            ? $request->query('status')
            : null;

        $query = CollegeScope::ofStudents(Students::query(), $request->user())
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where(function ($sq) use ($search) {
                $sq->where('lastName', 'like', "%{$search}%")
                    ->orWhere('firstName', 'like', "%{$search}%")
                    ->orWhere('middleName', 'like', "%{$search}%")
                    ->orWhere('schoolIdNumber', 'like', "%{$search}%");
            }));

        // The directory paginates on the server, so the order is asked of the query: a client sort would arrange
        // the twenty rows already on screen and present that as the list. The keys below are the only ones the
        // handler obeys, and the page renders a sortable header only where this list contains its key.
        $sortable = [
            'name' => ['column' => 'lastName'],
            'firstName' => ['column' => 'firstName'],
            'schoolId' => ['column' => 'schoolIdNumber'],
            'gender' => ['column' => 'gender'],
            'status' => ['column' => 'status'],
            'studentId' => ['column' => 'studentId'],
        ];
        $sort = ListSort::apply(
            $query,
            (string) $request->input('sort', ''),
            // A directory reads top to bottom unless the desk says otherwise, so this list's own default is the
            // ascending one — the helper's absence-of-direction answer is descending, which is what a queue of
            // newest-first records wants and is not what a surname list wants.
            (string) $request->input('direction', 'asc'),
            $sortable,
            'name',
            'studentId'
        );

        return Inertia::render('Students/Index', [
            'students' => $query->paginate(20)->withQueryString(),
            // `status` is echoed as the value actually applied, and the order as the order actually applied, so a
            // control never resets while the list it describes stays narrowed.
            'filters' => [
                'search' => $request->search,
                'status' => $status,
                'sort' => $sort['column'],
                'direction' => $sort['direction'],
            ],
            'sort' => ListSort::present($sort, $sortable),
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
            'enrollments.course.unit',
            'enrollments.major',
            'enrollments.term',
            'enrollments.enrollmentworkflow.workflowsteps.office',
            'enrollments.enrollmentworkflow.workflowsteps.signedBy',
            'enrollments.payments',
            'enrollments.studentassessments',
            'enrollments.enrolledsubjects.subject',
            'studentclearances',
            'examresults',
            'idrequests',
            'studentscholarships',
        ]);

        // §10's minimum-necessary clause: being allowed to open a student's record is not being allowed to read the
        // clinic's notes, so the relation is loaded only for an account that holds the clinic's own right. The
        // Health tab then has nothing to print for anybody else — the server decided, the page did not.
        if (request()->user()->can('clinic.view')) {
            $student->load('enrollments.clinicrecords');
        }

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

        $students = CollegeScope::ofStudents(
            Students::with(['enrollments.course', 'enrollments.term']),
            $request->user()
        )
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
