<?php

namespace App\Http\Controllers\Blocking;

use App\Enums\DayOfWeek;
use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\OfficeId;
use App\Enums\WorkflowStepStatus;
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
use App\Services\PrintService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $query = Blocks::with(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor'])
            // A block's places are students, not subject rows: two students carrying
            // three subjects each fill two seats, not six, and this is the number
            // assignStudents() weighs against maxStudents. Dropped rows are excluded so
            // a leave-out does not keep a seat reserved.
            ->withCount(['enrolledSubjects as students_in_block_count' => fn ($q) => $q->select(DB::raw('count(distinct enrollmentId)'))
                ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)])
            ->when($request->search, fn ($q, $s) => $q->where('blockName', 'like', "%{$s}%"))
            ->when($request->courseId, fn ($q, $id) => $q->where('courseId', $id))
            ->when($request->termId, fn ($q, $id) => $q->where('termId', $id))
            ->when($request->yearLevel, fn ($q, $level) => $q->where('yearLevel', $level))
            ->orderByDesc('blockId');

        $blocks = $query->paginate(20)->withQueryString();

        return Inertia::render('Blocking/Index', [
            'blocks' => $blocks,
            'courses' => Courses::all(['courseId', 'courseName', 'courseCode']),
            'terms' => Academicterms::with('academicYear')->get(['termId', 'semester', 'academicYearId']),
            'filters' => $request->only(['search', 'courseId', 'termId', 'yearLevel']),
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
            // The roster table prints each row's subject code; without this the
            // Subject column renders an em-dash for every assigned student.
            'enrolledSubjects.subject',
        ]);

        $capacity = $block->maxStudents;
        // Seats are students: two subjects apiece fill one place, not two. Dropped rows
        // are excluded, and the count is taken the same way assignStudents() takes it.
        $enrolled = $block->enrolledSubjects
            ->filter(fn ($es) => $es->status !== EnrolledSubjectStatus::Dropped)
            ->pluck('enrollmentId')
            ->unique()
            ->count();
        $available = $capacity - $enrolled;

        // Eligible enrollments for assignment (mirrors assignStudents eligibility logic)
        $eligibleEnrollments = Enrollments::with(['student', 'enrolledSubjects.subject'])
            ->where('courseId', $block->courseId)
            ->where('termId', $block->termId)
            ->where('yearLevel', $block->yearLevel)
            ->where('enrollmentStatus', EnrollmentStatus::Enrolled)
            ->whereNotIn('enrollmentId', function ($q) use ($block) {
                $q->select('enrollmentId')
                    ->from('enrolledsubjects')
                    ->where('blockId', $block->blockId);
            })
            ->get()
            ->filter(function ($enrollment) {
                $workflow = $enrollment->enrollmentworkflow;
                if (! $workflow) {
                    return false;
                }
                $nextPendingStep = $workflow->workflowsteps()
                    ->where('stepStatus', WorkflowStepStatus::Pending->value)
                    ->orderBy('stepOrder')
                    ->first();

                return $nextPendingStep && $nextPendingStep->officeId === OfficeId::Blocking->value;
            })
            ->map(function ($enrollment) {
                return [
                    'enrollmentId' => $enrollment->enrollmentId,
                    'student' => [
                        'firstName' => $enrollment->student->firstName,
                        'lastName' => $enrollment->student->lastName,
                        'middleName' => $enrollment->student->middleName,
                        'schoolIdNumber' => $enrollment->student->schoolIdNumber,
                    ],
                    'subjects' => $enrollment->enrolledSubjects->map(function ($es) {
                        return [
                            'enrolledSubjectId' => $es->enrolledSubjectId,
                            'subjectCode' => $es->subject->subjectCode,
                            'subjectName' => $es->subject->subjectName,
                        ];
                    })->values()->toArray(),
                ];
            })
            ->values();

        return Inertia::render('Blocking/Show', [
            'block' => $block,
            'capacity' => $capacity,
            'enrolled' => $enrolled,
            'available' => $available,
            'subjects' => Subjects::all(['subjectId', 'subjectCode', 'subjectName']),
            'rooms' => Rooms::all(['roomId', 'roomName', 'capacity', 'building']),
            'instructors' => Staffusers::where('officeId', '!=', OfficeId::Registrar->value)->get(['userId', 'firstName', 'lastName', 'middleName', 'role', 'officeId', 'unitId']),
            'days' => collect(DayOfWeek::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'eligibleEnrollments' => $eligibleEnrollments, 'canDelete' => $this->undeletableReasons($block) === [],
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

        // schedules.blockId and enrolledsubjects.blockId are NOT NULL RESTRICT keys,
        // so deleting a block that has been scheduled or seated answers with a raw
        // database error page. This is the desk where blocks are built, so the row on
        // screen nearly always has children — name them instead. The Reference Data
        // catalog refuses the same delete for the same reason.
        //
        // The detail screen asks this same question before it offers the action at all, so
        // a control that can only answer 403 cannot exist; undeletableReasons() is one copy.
        $usages = $this->undeletableReasons($block);

        if ($usages !== []) {
            $named = collect($usages)->map(fn (int $count, string $label): string => "{$count} {$label}(s)")->implode(', ');

            return back()->with('error', "Cannot delete block {$block->blockName}: it is still named by {$named}. Clear those first.");
        }

        $block->delete();

        return redirect()->route('blocking.index')->with('success', 'Block deleted.');
    }

    /**
     * Manage schedules for block.
     */
    public function storeSchedule(Request $request, Blocks $block): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');
        $this->ensureNotFinalized($block);

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

        // Room capacity warning: advisory only — the hard gate is at assignment
        // time (room_capacity), so a too-small room must not silently veto a
        // legal schedule. Warn, and still add it.
        $warning = null;
        $room = Rooms::find($validated['roomId']);
        if ($room && $block->maxStudents > $room->capacity) {
            $warning = "Block maxStudents ({$block->maxStudents}) exceeds room capacity ({$room->capacity}) for room {$room->roomName}. Schedule was still added — assignments to this room will be capped.";
        }

        // Persist within transaction
        return DB::transaction(function () use ($validated, $block, $warning) {
            $schedule = Schedules::create([
                'blockId' => $block->blockId,
                'subjectId' => $validated['subjectId'],
                'instructorId' => $validated['instructorId'],
                'roomId' => $validated['roomId'],
            ]);

            foreach ($validated['meetings'] as $meeting) {
                Schedulemeetings::create(array_merge($meeting, ['scheduleId' => $schedule->scheduleId]));
            }

            return $warning
                ? back()->with('success', 'Schedule added.')->with('warning', $warning)
                : back()->with('success', 'Schedule added.');
        });
    }

    /**
     * Update schedule (instructor, room, meetings).
     */
    public function updateSchedule(Request $request, Schedules $schedule): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');
        $this->ensureNotFinalized($schedule->block);

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

        // Exclude the schedule's own meetings: they are replaced by the
        // proposed ones, so they must not be read as conflicts.
        $conflicts = $this->detectConflicts($tempSchedule, $schedule->scheduleId);
        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages(['conflicts' => $conflicts->toArray()]);
        }

        // Room capacity warning: advisory only (see storeSchedule).
        $warning = null;
        if (isset($validated['roomId'])) {
            $room = Rooms::find($validated['roomId']);
            $block = $schedule->block;
            if ($room && $block && $block->maxStudents > $room->capacity) {
                $warning = "Block maxStudents ({$block->maxStudents}) exceeds room capacity ({$room->capacity}) for room {$room->roomName}. Schedule was still updated — assignments to this room will be capped.";
            }
        }

        return DB::transaction(function () use ($validated, $schedule, $warning) {
            $schedule->update(array_intersect_key($validated, array_flip(['instructorId', 'roomId'])));

            if (isset($validated['meetings'])) {
                $schedule->meetings()->delete();
                foreach ($validated['meetings'] as $meeting) {
                    Schedulemeetings::create(array_merge($meeting, ['scheduleId' => $schedule->scheduleId]));
                }
            }

            return $warning
                ? back()->with('success', 'Schedule updated.')->with('warning', $warning)
                : back()->with('success', 'Schedule updated.');
        });
    }

    /**
     * Delete schedule.
     */
    public function destroySchedule(Schedules $schedule): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');
        $this->ensureNotFinalized($schedule->block);

        // Guard: only if no enrolledsubjects reference it (or null them out first)
        $enrolledCount = Enrolledsubjects::where('scheduleId', $schedule->scheduleId)
            ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
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
     * Finalize the block's timetable (item 9). Mirrors the assessment finalize
     * pattern (idempotency guard): re-clicking Finalize on an already-final
     * block returns an info flash instead of a validation error.
     */
    public function finalize(Blocks $block): RedirectResponse
    {
        $this->authorize('blocking.manageSchedules');

        if ($block->scheduleStatus === 'final') {
            return back()->with('info', 'Block schedule is already finalized.');
        }

        $block->update(['scheduleStatus' => 'final']);

        return back()->with('success', 'Block schedule finalized. Timetable slots are now locked.');
    }

    /**
     * Guard shared by every schedule mutation point (item 9): a finalized
     * timetable can no longer be modified. Student assignment stays open.
     */
    private function ensureNotFinalized(?Blocks $block): void
    {
        if ($block && $block->scheduleStatus === 'final') {
            throw ValidationException::withMessages([
                'schedule' => 'This block schedule is finalized and cannot be modified.',
            ]);
        }
    }

    /**
     * Detect schedule conflicts (instructor/room/time overlap).
     * Two meetings overlap if same dayOfWeek AND startTime < other.endTime AND endTime > other.startTime.
     */
    private function detectConflicts(Schedules $schedule, ?int $exceptScheduleId = null)
    {
        $meetings = $schedule->meetings;
        $conflicts = collect();

        foreach ($meetings as $meeting) {
            // Instructor conflict. $exceptScheduleId drops the edited schedule's
            // own still-present meetings — updateSchedule replaces them, so they
            // must not be read as conflicts with the proposed times.
            $instructorConflict = Schedulemeetings::whereHas('schedule', fn ($q) => $q->where('instructorId', $schedule->instructorId))
                ->where('dayOfWeek', $meeting->dayOfWeek)
                ->where(function ($q) use ($meeting) {
                    $q->where('startTime', '<', $meeting->endTime)
                        ->where('endTime', '>', $meeting->startTime);
                })
                ->where('meetingId', '!=', $meeting->meetingId)
                ->when($exceptScheduleId, fn ($q) => $q->where('scheduleId', '!=', $exceptScheduleId))
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
                ->when($exceptScheduleId, fn ($q) => $q->where('scheduleId', '!=', $exceptScheduleId))
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

        // Block capacity enforcement (count distinct students, not subject rows)
        $currentEnrolled = Enrolledsubjects::where('blockId', $block->blockId)
            ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
            ->distinct('enrollmentId')
            ->count('enrollmentId');
        $requestedCount = count($validated['enrollmentIds']);
        if ($currentEnrolled + $requestedCount > $block->maxStudents) {
            throw ValidationException::withMessages([
                'capacity' => "Block capacity exceeded. Current students: {$currentEnrolled}, Max: {$block->maxStudents}, Requested: {$requestedCount}.",
            ]);
        }

        // Room capacity check for this schedule's room
        $room = $schedule->room;
        if ($room) {
            $roomEnrolled = Enrolledsubjects::where('scheduleId', $schedule->scheduleId)
                ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
                ->distinct('enrollmentId')
                ->count('enrollmentId');
            if ($roomEnrolled + $requestedCount > $room->capacity) {
                throw ValidationException::withMessages([
                    'room_capacity' => "Room capacity exceeded. Room {$room->roomName} capacity: {$room->capacity}, Current enrolled students: {$roomEnrolled}, Requested: {$requestedCount}.",
                ]);
            }
        }

        $assigned = 0;
        DB::transaction(function () use ($validated, $block, $schedule, &$assigned) {
            foreach ($validated['enrollmentIds'] as $enrollmentId) {
                $enrollment = Enrollments::findOrFail($enrollmentId);

                // Verify enrollment is enrolled and workflow at Academic Department step (office 5)
                if ($enrollment->enrollmentStatus !== EnrollmentStatus::Enrolled) {
                    continue;
                }

                $workflow = $enrollment->enrollmentworkflow;
                if (! $workflow || $workflow->workflowsteps()->where('stepStatus', WorkflowStepStatus::Pending->value)->orderBy('stepOrder')->first()?->officeId !== OfficeId::Blocking->value) {
                    continue;
                }

                // Assign to block and schedule. blockId marks block membership
                // on every subject row; scheduleId goes ONLY to the row whose
                // subject matches the chosen schedule — stamping every row with
                // one scheduleId would print IT101's meeting time on the CS102
                // row of the printed subject load.
                // ponytail: one scheduleId per subject row — a lecture+lab
                // subject's second meeting can't be stamped; a per-student
                // schedule pivot is the upgrade path if that ever matters.
                $enrollment->enrolledSubjects()
                    ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
                    ->update(['blockId' => $block->blockId]);

                $enrollment->enrolledSubjects()
                    ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
                    ->where('subjectId', $schedule->subjectId)
                    ->update(['scheduleId' => $schedule->scheduleId]);

                // Sign the Blocking step now that the student is assigned
                $this->workflowService->signStepByOffice($workflow, OfficeId::Blocking->value, Auth::user());
                $assigned++;
            }
        });

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
        DB::transaction(function () use ($validated, $block, &$unassigned) {
            foreach ($validated['enrollmentIds'] as $enrollmentId) {
                $enrollment = Enrollments::findOrFail($enrollmentId);

                $updated = $enrollment->enrolledSubjects()
                    ->where('status', '!=', EnrolledSubjectStatus::Dropped->value)
                    ->where('blockId', $block->blockId)
                    ->update([
                        'blockId' => null,
                        'scheduleId' => null,
                    ]);

                if ($updated > 0) {
                    $unassigned += $updated;
                }
            }
        });

        return back()->with('success', "{$unassigned} enrollment subject(s) unassigned from block.");
    }

    /**
     * Print block & schedule.
     */
    public function printBlockSchedule(Blocks $block, PrintService $printService): Response
    {
        $this->authorize('blocking.printBlockSchedule', $block);

        $block->load([
            'course', 'term.academicYear',
            'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings',
            'enrolledSubjects',
        ]);

        // A window print is a document issue: it has to appear in the trail, or a
        // printed roster leaves no evidence anyone ever held it. The issue belongs to
        // the block, not to whichever enrollment is listed first inside it.
        $printService->recordIssue(
            null,
            DocumentType::BlockSchedule,
            Auth::user()->userId,
            null,
            $block->blockId
        );

        return Inertia::render('Blocking/PrintSchedule', [
            'block' => $block,
        ]);
    }

    /**
     * Download the block & schedule roster as a PDF (landscape, one block).
     */
    public function downloadBlockSchedule(Blocks $block, PrintService $printService): BinaryFileResponse
    {
        $this->authorize('blocking.printBlockSchedule', $block);

        return $printService
            ->printBlockSchedule($block, Auth::user()->userId)
            ->asDownload("block-schedule-{$block->blockName}.pdf");
    }

    /**
     * The children that make a block undeletable, labelled for the refusal message.
     *
     * Written once and read twice: destroy() refuses on it, and show() shares it as `canDelete` so
     * the detail screen offers the button only for a block the delete would actually accept. Audit
     * lane 2 found the handler and its four tests with no control anywhere — a desk could retire an
     * empty block only by crafting the request. Placed last in the class because nineteen citations
     * in the documentation point into this file by line.
     *
     * @return array<string, int>
     */
    private function undeletableReasons(Blocks $block): array
    {
        return array_filter([
            'schedule' => $block->schedules()->count(),
            'enrolled subject' => $block->enrolledSubjects()->count(),
        ]);
    }
}
