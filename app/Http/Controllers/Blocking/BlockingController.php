<?php

namespace App\Http\Controllers\Blocking;

use App\Enums\DayOfWeek;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrollments;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Subjects;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BlockingController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private WorkflowService $workflowService
    ) {}

    /**
     * Display block manager.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Blocks::class);

        $query = Blocks::with(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor', 'enrolledSubjects'])
            ->when($request->courseId, fn ($q, $id) => $q->where('courseId', $id))
            ->when($request->termId, fn ($q, $id) => $q->where('termId', $id))
            ->when($request->yearLevel, fn ($q, $level) => $q->where('yearLevel', $level))
            ->latest();

        $blocks = $query->paginate(20)->withQueryString();

        return Inertia::render('Blocking/Index', [
            'blocks' => $blocks,
            'courses' => Courses::all(['courseId', 'courseName', 'courseCode']),
            'terms' => Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']),
            'filters' => $request->only(['courseId', 'termId', 'yearLevel']),
        ]);
    }

    /**
     * Show block details with capacity and students.
     */
    public function show(Blocks $block): Response
    {
        $this->authorize('view', $block);

        $block->load([
            'course', 'term.academicYear',
            'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings',
            'enrolledSubjects.enrollment.student',
        ]);

        $capacity = $block->maxStudents;
        $enrolled = $block->enrolledSubjects->count();
        $available = $capacity - $enrolled;

        return Inertia::render('Blocking/Show', [
            'block' => $block,
            'capacity' => $capacity,
            'enrolled' => $enrolled,
            'available' => $available,
            'subjects' => Subjects::all(['subjectId', 'subjectCode', 'subjectName']),
            'rooms' => Rooms::all(['roomId', 'roomName', 'capacity', 'building']),
            'instructors' => Staffusers::where('officeId', '!=', 1)->get(['userId', 'firstName', 'lastName']),
            'days' => DayOfWeek::cases(),
        ]);
    }

    /**
     * Create new block.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);

        $validated = $request->validate([
            'courseId' => 'required|exists:courses,courseId',
            'termId' => 'required|exists:academicterms,termId',
            'yearLevel' => 'required|integer|min:1|max:5',
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]);

        $block = Blocks::create($validated);

        return redirect()->route('blocking.show', $block)->with('success', 'Block created.');
    }

    /**
     * Update block.
     */
    public function update(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);

        $block->update($request->validate([
            'blockName' => 'required|string|max:50',
            'maxStudents' => 'required|integer|min:1',
        ]));

        return back()->with('success', 'Block updated.');
    }

    /**
     * Delete block.
     */
    public function destroy(Blocks $block): RedirectResponse
    {
        $this->authorize('manageBlocks', Blocks::class);

        $block->delete();

        return redirect()->route('blocking.index')->with('success', 'Block deleted.');
    }

    /**
     * Manage schedules for block.
     */
    public function storeSchedule(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('manageSchedules', Blocks::class);

        $validated = $request->validate([
            'subjectId' => 'required|exists:subjects,subjectId',
            'instructorId' => 'required|exists:staffusers,userId',
            'roomId' => 'required|exists:rooms,roomId',
            'meetings' => 'required|array|min:1',
            'meetings.*.dayOfWeek' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'meetings.*.startTime' => 'required|date_format:H:i',
            'meetings.*.endTime' => 'required|date_format:H:i|after:meetings.*.startTime',
        ]);

        $schedule = Schedules::create([
            'blockId' => $block->blockId,
            'subjectId' => $validated['subjectId'],
            'instructorId' => $validated['instructorId'],
            'roomId' => $validated['roomId'],
        ]);

        foreach ($validated['meetings'] as $meeting) {
            Schedulemeetings::create(array_merge($meeting, ['scheduleId' => $schedule->scheduleId]));
        }

        // Conflict detection
        $conflicts = $this->detectConflicts($schedule);
        if ($conflicts->isNotEmpty()) {
            return back()->with('warning', 'Schedule conflicts detected: '.$conflicts->implode('conflict', ', '));
        }

        return back()->with('success', 'Schedule added.');
    }

    /**
     * Detect schedule conflicts (instructor/room/time overlap).
     */
    private function detectConflicts(Schedules $schedule)
    {
        $meetings = $schedule->meetings;
        $conflicts = collect();

        foreach ($meetings as $meeting) {
            // Instructor conflict
            $instructorConflict = Schedulemeetings::whereHas('schedule', fn ($q) => $q->where('instructorId', $schedule->instructorId))
                ->where('dayOfWeek', $meeting->dayOfWeek)
                ->where(function ($q) use ($meeting) {
                    $q->where('startTime', '<', $meeting->endTime)
                        ->where('endTime', '>', $meeting->startTime);
                })
                ->where('meetingId', '!=', $meeting->meetingId)
                ->exists();

            if ($instructorConflict) {
                $conflicts->push("Instructor conflict on {$meeting->dayOfWeek->value} {$meeting->startTime}-{$meeting->endTime}");
            }

            // Room conflict
            $roomConflict = Schedulemeetings::whereHas('schedule', fn ($q) => $q->where('roomId', $schedule->roomId))
                ->where('dayOfWeek', $meeting->dayOfWeek)
                ->where(function ($q) use ($meeting) {
                    $q->where('startTime', '<', $meeting->endTime)
                        ->where('endTime', '>', $meeting->startTime);
                })
                ->where('meetingId', '!=', $meeting->meetingId)
                ->exists();

            if ($roomConflict) {
                $conflicts->push("Room conflict on {$meeting->dayOfWeek->value} {$meeting->startTime}-{$meeting->endTime}");
            }
        }

        return $conflicts;
    }

    /**
     * Assign students to block.
     * BR13/BR14: Workflow step for Academic Department (office 5) must be completed in order
     */
    public function assignStudents(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('assignStudents', [Enrollments::class, $block]);

        $validated = $request->validate([
            'enrollmentIds' => 'required|array',
            'enrollmentIds.*' => 'exists:enrollments,enrollmentId',
            'scheduleId' => 'required|exists:schedules,scheduleId',
        ]);

        $schedule = Schedules::findOrFail($validated['scheduleId']);

        foreach ($validated['enrollmentIds'] as $enrollmentId) {
            $enrollment = Enrollments::findOrFail($enrollmentId);

            // Verify enrollment is enrolled and workflow at Academic Department step (office 5)
            if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
                continue;
            }

            $workflow = $enrollment->enrollmentworkflow;
            if (! $workflow || $workflow->workflowsteps()->where('stepStatus', 'pending')->orderBy('stepOrder')->first()?->officeId !== 5) {
                continue;
            }

            // Assign to block and schedule
            $enrollment->enrolledSubjects()
                ->where('status', '!=', 'dropped')
                ->update([
                    'blockId' => $block->blockId,
                    'scheduleId' => $schedule->scheduleId,
                ]);

            // Sign the Blocking step (office 5) now that the student is assigned
            $this->workflowService->signStepByOffice($workflow, 5, Auth::user());
        }

        return back()->with('success', 'Students assigned to block.');
    }

    /**
     * Print block & schedule.
     */
    public function printBlockSchedule(Blocks $block): Response
    {
        $this->authorize('printBlockSchedule', $block);

        $block->load([
            'course', 'term.academicYear',
            'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings',
        ]);

        return Inertia::render('Blocking/PrintSchedule', [
            'block' => $block,
        ]);
    }
}
