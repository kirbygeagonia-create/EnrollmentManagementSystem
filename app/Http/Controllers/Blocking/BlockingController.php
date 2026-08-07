<?php

namespace App\Http\Controllers\Blocking;

use App\Enums\DayOfWeek;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use App\Models\Blocks;
use App\Models\Courses;
use App\Models\Enrolledsubjects;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $this->authorize('blocking.viewAny');

        $query = Blocks::with(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor', 'enrolledSubjects'])
            ->when($request->courseId, fn ($q, $id) => $q->where('courseId', $id))
            ->when($request->termId, fn ($q, $id) => $q->where('termId', $id))
            ->when($request->yearLevel, fn ($q, $level) => $q->where('yearLevel', $level))
            ->orderByDesc('blockId');

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
        $this->authorize('blocking.view', $block);

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
            'days' => collect(DayOfWeek::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    /**
     * Create new block.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('blocking.manageBlocks');

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
        $this->authorize('blocking.manageBlocks');

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
        $this->authorize('blocking.manageBlocks');

        $block->delete();

        return redirect()->route('blocking.index')->with('success', 'Block deleted.');
    }

    /**
     * Manage schedules for block.
     */
    public function storeSchedule(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');

        $validated = $request->validate([
            'subjectId' => 'required|exists:subjects,subjectId',
            'instructorId' => 'required|exists:staffusers,userId',
            'roomId' => 'required|exists:rooms,roomId',
            'meetings' => 'required|array|min:1',
            'meetings.*.dayOfWeek' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'meetings.*.startTime' => 'required|date_format:H:i',
            'meetings.*.endTime' => 'required|date_format:H:i|after:meetings.*.startTime',
        ]);

        // Run conflict detection BEFORE persisting
        $tempSchedule = new Schedules([
            'blockId' => $block->blockId,
            'subjectId' => $validated['subjectId'],
            'instructorId' => $validated['instructorId'],
            'roomId' => $validated['roomId'],
        ]);
        $tempSchedule->setRelation('meetings', collect($validated['meetings'])->map(fn ($m) => new Schedulemeetings($m)));

        $conflicts = $this->detectConflicts($tempSchedule);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['conflicts' => $conflicts->toArray()]);
        }

        // Room capacity warning: if block's maxStudents exceeds room capacity
        $room = Rooms::find($validated['roomId']);
        if ($room && $block->maxStudents > $room->capacity) {
            return back()->with('warning', "Block maxStudents ({$block->maxStudents}) exceeds room capacity ({$room->capacity}) for room {$room->roomName}.");
        }

        // Persist within transaction
        return DB::transaction(function () use ($validated, $block) {
            $schedule = Schedules::create([
                'blockId' => $block->blockId,
                'subjectId' => $validated['subjectId'],
                'instructorId' => $validated['instructorId'],
                'roomId' => $validated['roomId'],
            ]);

            foreach ($validated['meetings'] as $meeting) {
                Schedulemeetings::create(array_merge($meeting, ['scheduleId' => $schedule->scheduleId]));
            }

            return back()->with('success', 'Schedule added.');
        });
    }

    /**
     * Update schedule (instructor, room, meetings).
     */
    public function updateSchedule(Request $request, Schedules $schedule): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');

        $validated = $request->validate([
            'instructorId' => 'sometimes|required|exists:staffusers,userId',
            'roomId' => 'sometimes|required|exists:rooms,roomId',
            'meetings' => 'sometimes|required|array|min:1',
            'meetings.*.dayOfWeek' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'meetings.*.startTime' => 'required|date_format:H:i',
            'meetings.*.endTime' => 'required|date_format:H:i|after:meetings.*.startTime',
        ]);

        // Build temp schedule with proposed changes for conflict detection
        $tempSchedule = $schedule->replicate();
        $tempSchedule->fill(array_intersect_key($validated, array_flip(['instructorId', 'roomId'])));
        if (isset($validated['meetings'])) {
            $tempSchedule->setRelation('meetings', collect($validated['meetings'])->map(fn ($m) => new Schedulemeetings($m)));
        } else {
            $tempSchedule->load('meetings');
        }

        $conflicts = $this->detectConflicts($tempSchedule);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['conflicts' => $conflicts->toArray()]);
        }

        // Room capacity warning
        if (isset($validated['roomId'])) {
            $room = Rooms::find($validated['roomId']);
            $block = $schedule->block;
            if ($room && $block && $block->maxStudents > $room->capacity) {
                return back()->with('warning', "Block maxStudents ({$block->maxStudents}) exceeds room capacity ({$room->capacity}) for room {$room->roomName}.");
            }
        }

        return DB::transaction(function () use ($validated, $schedule) {
            $schedule->update(array_intersect_key($validated, array_flip(['instructorId', 'roomId'])));

            if (isset($validated['meetings'])) {
                $schedule->meetings()->delete();
                foreach ($validated['meetings'] as $meeting) {
                    Schedulemeetings::create(array_merge($meeting, ['scheduleId' => $schedule->scheduleId]));
                }
            }

            return back()->with('success', 'Schedule updated.');
        });
    }

    /**
     * Delete schedule.
     */
    public function destroySchedule(Schedules $schedule): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');

        // Guard: only if no enrolledsubjects reference it (or null them out first)
        $enrolledCount = Enrolledsubjects::where('scheduleId', $schedule->scheduleId)
            ->where('status', '!=', 'dropped')
            ->count();

        if ($enrolledCount > 0) {
            throw ValidationException::withMessages([
                'schedule' => "Cannot delete schedule: {$enrolledCount} enrolled student(s) still reference it. Unassign them first.",
            ]);
        }

        $schedule->meetings()->delete();
        $schedule->delete();

        return back()->with('success', 'Schedule deleted.');
    }

    /**
     * Detect schedule conflicts (instructor/room/time overlap).
     * Two meetings overlap if same dayOfWeek AND startTime < other.endTime AND endTime > other.startTime.
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
        $this->authorize('blocking.assignStudents', $block);

        $validated = $request->validate([
            'enrollmentIds' => 'required|array',
            'enrollmentIds.*' => 'exists:enrollments,enrollmentId',
            'scheduleId' => 'required|exists:schedules,scheduleId',
        ]);

        $schedule = Schedules::findOrFail($validated['scheduleId']);

        // Block capacity enforcement
        $currentEnrolled = Enrolledsubjects::where('blockId', $block->blockId)
            ->where('status', '!=', 'dropped')
            ->count();
        $requestedCount = count($validated['enrollmentIds']);
        if ($currentEnrolled + $requestedCount > $block->maxStudents) {
            throw ValidationException::withMessages([
                'capacity' => "Block capacity exceeded. Current: {$currentEnrolled}, Max: {$block->maxStudents}, Requested: {$requestedCount}.",
            ]);
        }

        // Room capacity check for this schedule's room
        $room = $schedule->room;
        if ($room) {
            $roomEnrolled = Enrolledsubjects::where('scheduleId', $schedule->scheduleId)
                ->where('status', '!=', 'dropped')
                ->count();
            if ($roomEnrolled + $requestedCount > $room->capacity) {
                throw ValidationException::withMessages([
                    'room_capacity' => "Room capacity exceeded. Room {$room->roomName} capacity: {$room->capacity}, Current enrolled: {$roomEnrolled}, Requested: {$requestedCount}.",
                ]);
            }
        }

        $assigned = 0;
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
            $assigned++;
        }

        return back()->with('success', "{$assigned} student(s) assigned to block.");
    }

    /**
     * Unassign students from block (correction, not workflow regression).
     */
    public function unassignStudents(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('blocking.assignStudents', $block);

        $validated = $request->validate([
            'enrollmentIds' => 'required|array',
            'enrollmentIds.*' => 'exists:enrollments,enrollmentId',
        ]);

        $unassigned = 0;
        foreach ($validated['enrollmentIds'] as $enrollmentId) {
            $enrollment = Enrollments::findOrFail($enrollmentId);

            $updated = $enrollment->enrolledSubjects()
                ->where('status', '!=', 'dropped')
                ->where('blockId', $block->blockId)
                ->update([
                    'blockId' => null,
                    'scheduleId' => null,
                ]);

            if ($updated > 0) {
                $unassigned += $updated;
            }
        }

        return back()->with('success', "{$unassigned} enrollment subject(s) unassigned from block.");
    }

    /**
     * Print block & schedule.
     */
    public function printBlockSchedule(Blocks $block): Response
    {
        $this->authorize('blocking.printBlockSchedule', $block);

        $block->load([
            'course', 'term.academicYear',
            'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings',
        ]);

        return Inertia::render('Blocking/PrintSchedule', [
            'block' => $block,
        ]);
    }
}
