<?php

namespace App\Console\Commands;

use App\Enums\DayOfWeek;
use App\Models\Academicterms;
use App\Models\Blocks;
use App\Models\Rooms;
use App\Models\Schedulemeetings;
use App\Models\Schedules;
use App\Models\Staffusers;
use App\Models\Subjects;
use App\Support\TimetableConflicts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reads a timetable sheet and reports what it would publish and what it would refuse, then writes
 * only when `--commit` is given and every row was accepted. The prompt asks for a structured-format
 * import with preview and validation (§7.9); the shape follows `ems:import:students`, which the owner
 * ruled should be approached as a dry run first, because the column map and the refusals are the part
 * a department has to agree with before a row may be inserted.
 *
 * Three rules this command exists to honour.
 *
 * **One row is enough to refuse the whole sheet.** A timetable is read as a set: half a published week
 * is worse than none, because the desks that already booked a room cannot see what did not arrive. So
 * `--commit` on a sheet carrying any refusal writes nothing at all and says which rows.
 *
 * **A collision is named before publication, by the rule the desk already uses.** The three questions
 * live in `App\Support\TimetableConflicts` and are asked twice here: against the meetings already on
 * file, and between the rows of the sheet itself. The second pass is the one a preview cannot skip —
 * two rows that double-book a room with each other are invisible to a database query, because neither
 * is in the database yet.
 *
 * **Names resolve; ids are not accepted.** A department's sheet carries `BSIT 2A`, `GEED 101`, a room
 * and a teacher — not primary keys. Every lookup is by the label the Reference Data screens show, and
 * a label matching nothing or matching twice is refused with the candidates printed, because the quiet
 * failure of an importer is the row that attaches to the wrong subject at the wrong hour.
 */
class ImportTimetable extends Command
{
    protected $signature = 'ems:import:timetable
                    {--csv= : A sheet carrying exactly the columns --template lists}
                    {--template : Print the column map and exit without reading anything}
                    {--term= : The academicterms.termId the sheet belongs to; required with --csv}
                    {--commit : Publish the sheet, but only when every row was accepted}
                    {--limit= : Read at most this many data rows}';

    protected $description = 'Preview a timetable sheet against the Blocking rules; publish only a sheet with no refusal';

    /**
     * The column map: CSV header => what the desk calls the value, and what it is matched against.
     *
     * Every column is required, so the map carries no optional marker: a sheet whose room cell is
     * blank is not a record with one field left out, it is a meeting nobody can book.
     *
     * @var array<string, array{what: string, lookup: string}>
     */
    private const COLUMNS = [
        'block_name' => ['what' => 'Block/section name, unique within the term', 'lookup' => 'blocks.blockName in the term asked for'],
        'subject_code' => ['what' => 'Subject code as Reference Data holds it', 'lookup' => 'subjects.subjectCode'],
        'instructor' => ['what' => 'Account name, or a full name belonging to exactly one account', 'lookup' => 'staffusers.username'],
        'room' => ['what' => 'Room name as Reference Data holds it', 'lookup' => 'rooms.roomName'],
        'day_of_week' => ['what' => 'One of the seven days, spelled in full', 'lookup' => 'schedulemeetings.dayOfWeek'],
        'start_time' => ['what' => 'Start, 24-hour H:i', 'lookup' => 'schedulemeetings.startTime'],
        'end_time' => ['what' => 'End, 24-hour H:i, after the start', 'lookup' => 'schedulemeetings.endTime'],
    ];

    /** @var array<int, array<int, string>> */
    private array $verdicts = [];

    public function handle(): int
    {
        if ($this->option('template')) {
            return $this->printTemplate();
        }

        $path = (string) $this->option('csv');

        if ($path === '' || ! is_readable($path)) {
            $this->error("No readable CSV at '{$path}'. Pass --csv=path, or --template to print the columns a sheet must carry.");

            return self::FAILURE;
        }

        $term = $this->resolveTerm();

        if ($term === null) {
            return self::FAILURE;
        }

        $rows = $this->readRows($path);

        if (count($rows) < 2) {
            $this->error('The sheet carries no data rows beside its header.');

            return self::FAILURE;
        }

        $header = array_shift($rows);
        $problems = $this->checkHeader($header);

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            $this->line('');
            $this->line('Run --template for the column list this tool maps.');

            return self::FAILURE;
        }

        $map = $this->columnIndex($header);
        $width = count($header);
        $lookups = $this->loadLookups((int) $term->termId);

        /** @var array<int, array<string, mixed>|null> $cells */
        $cells = [];
        /** @var array<int, list<string>> $reasons */
        $reasons = [];
        $lines = [];

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            [$read, $why] = $this->checkRow($row, $map, $width, $lookups);
            $cells[$line] = $read;
            $reasons[$line] = $why;

            if ($read !== null) {
                $lines[] = $read + ['row' => $line];
            }
        }

        $this->attachConflicts($lines, $reasons, $lookups);

        foreach ($cells as $line => $read) {
            $this->verdicts[] = $this->verdictRow($line, $read, $reasons[$line]);
        }

        $this->table(['Row', 'Block', 'Subject', 'Room', 'Instructor', 'When', 'Verdict'], $this->verdicts);

        // One reason per line, outside the table. A cell that carries three sentences wrapped is three
        // sentences nobody can read off a console, and the reader has to fix every one of them.
        foreach ($reasons as $line => $why) {
            foreach ($why as $reason) {
                $this->line("row {$line} — {$reason}");
            }
        }

        $schedules = $this->group($lines);
        $refused = count(array_filter($reasons, fn (array $why): bool => $why !== []));

        $this->line('');
        $this->line(sprintf(
            'Term: %s %s — termId %d',
            $lookups['years'][$term->academicYearId] ?? "academicYearId {$term->academicYearId}",
            $term->semester->value,
            (int) $term->termId
        ));
        $this->info(sprintf(
            'Rows read: %d   accepted: %d   refused: %d   would create: %d schedule(s) over %d meeting line(s)',
            count($cells),
            count($cells) - $refused,
            $refused,
            count($schedules),
            count($lines)
        ));

        foreach ($this->capacityAdvisories($schedules, $lookups) as $advisory) {
            $this->warn($advisory);
        }

        if ($refused > 0) {
            $this->line('');
            $this->error("The sheet is refused as a whole: {$refused} of ".count($cells).' row(s) carry a reason.');

            if ($this->option('commit')) {
                $this->error('--commit was given, and nothing was written. A half-published week is the state this guard exists to prevent.');
            }

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $this->line('');
            $this->line('<fg=yellow>Nothing was written.</> This was a preview. Re-run with --commit to publish these '.count($schedules).' schedule(s).');

            return self::SUCCESS;
        }

        return $this->publish($schedules);
    }

    private function printTemplate(): int
    {
        $this->line('Column map — a sheet must carry every header below, in any order, and no others.');
        $this->line('One row is one meeting: a block that meets twice a week carries two rows sharing');
        $this->line('block, subject, instructor and room, which the tool then publishes as one schedule');
        $this->line('with two meetings — the same shape the desk form writes.');
        $this->line('');

        $this->table(
            ['CSV header', 'Accepted', 'Resolved against'],
            collect(self::COLUMNS)->map(fn (array $rule, string $header): array => [
                $header,
                $rule['what'],
                $rule['lookup'],
            ])->all()
        );

        $this->line('');
        $this->line('Header row to copy:');
        $this->line(implode(',', array_keys(self::COLUMNS)));
        $this->line('');
        $this->line('Days accepted: '.implode(', ', array_map(fn (DayOfWeek $d): string => $d->value, DayOfWeek::cases())));
        $this->line('The term is not a column — pass --term=<academicterms.termId>, because block names repeat across terms');
        $this->line('and a sheet that did not name one could book a retired timetable.');

        return self::SUCCESS;
    }

    private function resolveTerm(): ?Academicterms
    {
        $given = trim((string) $this->option('term'));

        if ($given === '') {
            $this->error('No term named. Pass --term=<academicterms.termId>: a timetable belongs to one term, and block names repeat across terms.');
        } elseif (! ctype_digit($given)) {
            $this->error("--term must be the term's id, not '{$given}'.");
        } else {
            $term = Academicterms::find((int) $given);

            if ($term !== null) {
                return $term;
            }

            $this->error("No academicterm answers termId {$given}.");
        }

        $this->line('');
        $this->table(
            ['termId', 'Academic year', 'Semester', 'Starts'],
            Academicterms::query()->with('academicYear')->orderBy('termId')->get()
                ->map(fn (Academicterms $t): array => [
                    (string) $t->termId,
                    $t->academicYear->yearLabel,
                    $t->semester->value,
                    $t->startDate->toDateString(),
                ])
                ->all()
        );

        return null;
    }

    /**
     * Everything a row is matched against, read once.
     *
     * Blocks are keyed by name inside the named term only, which is what makes `--term` load-bearing:
     * `blocks.blockName` carries no unique key, so the term is the only thing that turns a name into
     * exactly one row.
     *
     * Measured on live `ems` 2026-10-27, which is why the instructor column resolves the way it does:
     * 21 accounts, no duplicate `username`, and one duplicate `firstName lastName`. A full name is
     * therefore honoured only when it belongs to one account, and the account name is tried first.
     *
     * @return array{
     *   blocks: array<string, list<Blocks>>,
     *   blockById: array<int, Blocks>,
     *   subjects: array<string, int>,
     *   rooms: array<string, int>,
     *   roomById: array<int, Rooms>,
     *   usernames: array<string, int>,
     *   names: array<string, list<string>>,
     *   years: array<int, string>
     * }
     */
    private function loadLookups(int $termId): array
    {
        $blocks = [];
        $blockById = [];

        foreach (Blocks::query()->where('termId', $termId)->get() as $block) {
            $blocks[strtolower(trim($block->blockName))][] = $block;
            $blockById[(int) $block->blockId] = $block;
        }

        $byUsername = [];
        $byName = [];

        foreach (Staffusers::query()->get(['userId', 'firstName', 'lastName', 'username']) as $staff) {
            $byUsername[strtolower($staff->username)] = (int) $staff->userId;
            $byName[strtolower(trim($staff->firstName.' '.$staff->lastName))][] = $staff->username;
        }

        $subjects = [];

        foreach (Subjects::query()->get(['subjectId', 'subjectCode']) as $subject) {
            $subjects[strtolower(trim($subject->subjectCode))] = (int) $subject->subjectId;
        }

        $rooms = [];
        $roomById = [];

        foreach (Rooms::query()->get() as $room) {
            $rooms[strtolower(trim($room->roomName))] = (int) $room->roomId;
            $roomById[(int) $room->roomId] = $room;
        }

        return [
            'blocks' => $blocks,
            'blockById' => $blockById,
            'subjects' => $subjects,
            'rooms' => $rooms,
            'roomById' => $roomById,
            'usernames' => $byUsername,
            'names' => $byName,
            'years' => DB::table('academicyears')->pluck('yearLabel', 'academicYearId')->all(),
        ];
    }

    /**
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     * @param  array<string, mixed>  $lookups
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private function checkRow(array $row, array $map, int $width, array $lookups): array
    {
        if (count($row) !== $width) {
            return [null, [
                'the row carries '.count($row).' fields where the header declares '.$width
                .' — an unquoted comma inside a value splits it into two and shifts every column after it',
            ]];
        }

        $reasons = [];

        foreach (self::COLUMNS as $header => $rule) {
            $value = $this->cell($row, $map, $header);

            if ($value === '') {
                $reasons[] = "{$header} is required ({$rule['what']})";
            } elseif (mb_strlen($value) > 150) {
                $reasons[] = "{$header} is ".mb_strlen($value).' characters, longer than the 150 '.$rule['lookup'].' holds';
            }
        }

        if ($reasons !== []) {
            return [null, $reasons];
        }

        $blockValue = $this->cell($row, $map, 'block_name');
        $day = $this->resolveDay($this->cell($row, $map, 'day_of_week'), $reasons);
        $start = $this->clock($this->cell($row, $map, 'start_time'), 'start_time', $reasons);
        $end = $this->clock($this->cell($row, $map, 'end_time'), 'end_time', $reasons);
        $blockId = $this->resolveBlock($blockValue, $lookups, $reasons);

        if ($blockId !== null && $lookups['blockById'][$blockId]->scheduleStatus === 'final') {
            $reasons[] = "block_name '{$blockValue}' is finalized, and Blocking accepts no schedule onto a finalized timetable — the same guard the desk screen runs";
        }

        if ($start !== null && $end !== null && ! ($start < $end)) {
            $reasons[] = "end_time '{$end}' is not after start_time '{$start}' — a meeting cannot end before it begins, and a zero-length one is not a class";
        }

        $subjectId = $this->resolve($lookups['subjects'], $this->cell($row, $map, 'subject_code'), 'subject_code', 'subjects.subjectCode', $reasons);
        $roomId = $this->resolve($lookups['rooms'], $this->cell($row, $map, 'room'), 'room', 'rooms.roomName', $reasons);
        $instructorId = $this->resolveInstructor($this->cell($row, $map, 'instructor'), $lookups, $reasons);

        if ($reasons !== []) {
            return [null, $reasons];
        }

        return [[
            'block' => $blockValue,
            'subject' => $this->cell($row, $map, 'subject_code'),
            'instructor' => $this->cell($row, $map, 'instructor'),
            'room' => $this->cell($row, $map, 'room'),
            'dayOfWeek' => $day,
            'startTime' => $start,
            'endTime' => $end,
            'blockId' => $blockId,
            'subjectId' => $subjectId,
            'instructorId' => $instructorId,
            'roomId' => $roomId,
        ], []];
    }

    /**
     * @param  array<string, mixed>  $lookups
     */
    private function resolveBlock(string $value, array $lookups, array &$reasons): ?int
    {
        $matches = $lookups['blocks'][strtolower(trim($value))] ?? [];

        if ($matches === []) {
            $reasons[] = "block_name '{$value}' names no block in the term asked for — seat it in Blocking first, or pass the term the block actually sits in";

            return null;
        }

        if (count($matches) > 1) {
            $reasons[] = "block_name '{$value}' matches ".count($matches).' blocks in this term ('
                .collect($matches)->map(fn (Blocks $b): string => "blockId {$b->blockId}")->implode(', ')
                .') — the name alone does not choose one';

            return null;
        }

        return (int) $matches[0]->blockId;
    }

    /**
     * @param  array<string, int>  $table
     */
    private function resolve(array $table, string $value, string $header, string $where, array &$reasons): ?int
    {
        $id = $table[strtolower(trim($value))] ?? null;

        if ($id === null) {
            $reasons[] = "{$header} '{$value}' matches no {$where}";

            return null;
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $lookups
     */
    private function resolveInstructor(string $value, array $lookups, array &$reasons): ?int
    {
        $key = strtolower(trim($value));
        $byUsername = $lookups['usernames'][$key] ?? null;

        if ($byUsername !== null) {
            return (int) $byUsername;
        }

        $names = $lookups['names'][$key] ?? [];

        if (count($names) > 1) {
            $reasons[] = "instructor '{$value}' is the name of ".count($names).' accounts ('.implode(', ', $names)
                .') — put the account name in the sheet, because a full name is not unique on this install';

            return null;
        }

        if ($names === []) {
            $reasons[] = "instructor '{$value}' matches no staffusers.username and no single staff name";

            return null;
        }

        return (int) $lookups['usernames'][strtolower($names[0])];
    }

    /** @param list<string> $reasons */
    private function resolveDay(string $value, array &$reasons): ?string
    {
        foreach (DayOfWeek::cases() as $case) {
            if (strtolower($value) === strtolower($case->value)) {
                return $case->value;
            }
        }

        $reasons[] = "day_of_week '{$value}' is not one of "
            .implode(', ', array_map(fn (DayOfWeek $d): string => $d->value, DayOfWeek::cases()))
            .' — abbreviations are refused, because "Thu" and "Thur" would both mean one day';

        return null;
    }

    /**
     * The `HH:MM` shape `TimetableConflicts::overlaps()` compares as text, or a reason. Zero-padding
     * here is not decoration: `8:00 < 10:00` is false as text, so a value kept un-padded would order
     * against the meetings already on file the wrong way.
     *
     * @param  list<string>  $reasons
     */
    private function clock(string $value, string $header, array &$reasons): ?string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $m) === 1
            && (int) $m[1] <= 23 && (int) $m[2] <= 59) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        $reasons[] = "{$header} '{$value}' is not a 24-hour time this tool can order (expected H:i, such as 08:00 or 8:00)";

        return null;
    }

    /**
     * Both conflict surfaces, with every message written onto each row it names. A sheet that blamed
     * only one of a colliding pair would send the reader to fix one row and re-book the other.
     *
     * @param  list<array<string, mixed>>  $lines
     * @param  array<int, list<string>>  $reasons
     * @param  array<string, mixed>  $lookups
     */
    private function attachConflicts(array $lines, array &$reasons, array $lookups): void
    {
        // Projected to the shape the shared rule reads, so a row's display cells cannot be mistaken
        // for part of the collision question.
        $asMeetings = array_map(fn (array $l): array => [
            'row' => (int) $l['row'],
            'blockId' => (int) $l['blockId'],
            'instructorId' => (int) $l['instructorId'],
            'roomId' => (int) $l['roomId'],
            'dayOfWeek' => (string) $l['dayOfWeek'],
            'startTime' => (string) $l['startTime'],
            'endTime' => (string) $l['endTime'],
        ], $lines);

        foreach (TimetableConflicts::withinSheet($asMeetings) as $conflict) {
            $rows = $this->rowsNamed($conflict, $asMeetings);

            if ($rows === []) {
                $this->warn($conflict);

                continue;
            }

            foreach ($rows as $row) {
                $reasons[$row][] = $conflict;
            }
        }

        foreach ($this->group($lines) as $group) {
            $schedule = new Schedules([
                'blockId' => $group['blockId'],
                'subjectId' => $group['subjectId'],
                'instructorId' => $group['instructorId'],
                'roomId' => $group['roomId'],
            ]);
            $schedule->setRelation('block', $lookups['blockById'][$group['blockId']] ?? null);
            $schedule->setRelation('meetings', collect($group['lines'])
                ->map(fn (array $l): Schedulemeetings => new Schedulemeetings([
                    'dayOfWeek' => $l['dayOfWeek'],
                    'startTime' => $l['startTime'],
                    'endTime' => $l['endTime'],
                ])));

            $rows = collect($group['lines'])->map(fn (array $l): int => (int) $l['row'])->all();

            foreach (TimetableConflicts::againstDatabase($schedule) as $conflict) {
                foreach ($rows as $row) {
                    $reasons[$row][] = $conflict.' — that hour is already on file for the same officer, room or block';
                }
            }
        }
    }

    /**
     * The rows a `withinSheet` message names. The message is the only carrier of the pairing, so it is
     * parsed back rather than guessed at — and if its shape ever changes, no row is charged and the
     * sentence is printed instead, which is louder than silently clearing a collision.
     *
     * @param  list<array<string, mixed>>  $asMeetings
     * @return list<int>
     */
    private function rowsNamed(string $conflict, array $asMeetings): array
    {
        if (preg_match('/between rows (\d+) and (\d+) of this file/', $conflict, $m) !== 1) {
            return [];
        }

        $known = collect($asMeetings)->map(fn (array $l): int => (int) $l['row'])->all();
        $rows = [(int) $m[1], (int) $m[2]];

        return array_values(array_filter($rows, fn (int $r): bool => in_array($r, $known, true)));
    }

    /**
     * Rows sharing block, subject, instructor and room become one schedule carrying all their meetings.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function group(array $lines): array
    {
        $groups = [];

        foreach ($lines as $line) {
            $key = "{$line['blockId']}|{$line['subjectId']}|{$line['instructorId']}|{$line['roomId']}";

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'blockId' => $line['blockId'],
                    'subjectId' => $line['subjectId'],
                    'instructorId' => $line['instructorId'],
                    'roomId' => $line['roomId'],
                    'lines' => [],
                ];
            }

            $groups[$key]['lines'][] = $line;
        }

        return array_values($groups);
    }

    /**
     * The advisory the desk gives, kept here so a sheet cannot book a group into a room the assignments
     * will not fit. It refuses nothing: the hard cap is enforced at assignment time (`room_capacity`).
     *
     * @param  list<array<string, mixed>>  $schedules
     * @param  array<string, mixed>  $lookups
     * @return list<string>
     */
    private function capacityAdvisories(array $schedules, array $lookups): array
    {
        $seen = [];
        $advisories = [];

        foreach ($schedules as $group) {
            $block = $lookups['blockById'][$group['blockId']] ?? null;
            $room = $lookups['roomById'][$group['roomId']] ?? null;

            if ($block === null || $room === null || $block->maxStudents <= $room->capacity) {
                continue;
            }

            $key = "{$block->blockId}|{$room->roomId}";

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $advisories[] = "Block {$block->blockName} holds {$block->maxStudents} students and room {$room->roomName} seats {$room->capacity}."
                .' Would still be published — assignments to this room will be capped, exactly as the desk warns.';
        }

        return $advisories;
    }

    /** @param array<string, mixed>|null $read @param list<string> $why @return array<int, string> */
    private function verdictRow(int $line, ?array $read, array $why): array
    {
        return [
            (string) $line,
            $read['block'] ?? '(not resolved)',
            (string) ($read['subject'] ?? ''),
            (string) ($read['room'] ?? ''),
            (string) ($read['instructor'] ?? ''),
            $read === null ? '' : "{$read['dayOfWeek']} {$read['startTime']}-{$read['endTime']}",
            $why === [] ? 'would publish' : 'refuse',
        ];
    }

    /**
     * One transaction for the whole sheet, because a sheet is one week of somebody's timetable: a
     * failure halfway would leave rooms booked for Monday and free for Tuesday.
     *
     * @param  list<array<string, mixed>>  $schedules
     */
    private function publish(array $schedules): int
    {
        $written = DB::transaction(function () use ($schedules): int {
            $meetings = 0;

            foreach ($schedules as $group) {
                $schedule = Schedules::create([
                    'blockId' => $group['blockId'],
                    'subjectId' => $group['subjectId'],
                    'instructorId' => $group['instructorId'],
                    'roomId' => $group['roomId'],
                ]);

                foreach ($group['lines'] as $line) {
                    Schedulemeetings::create([
                        'scheduleId' => $schedule->scheduleId,
                        'dayOfWeek' => $line['dayOfWeek'],
                        'startTime' => $line['startTime'],
                        'endTime' => $line['endTime'],
                    ]);

                    $meetings++;
                }
            }

            return $meetings;
        });

        $this->info("Published: {$written} meeting line(s) over ".count($schedules).' schedule(s).');

        return self::SUCCESS;
    }

    /** @return list<array<int, string|null>> */
    private function readRows(string $path): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY | \SplFileObject::DROP_NEW_LINE);

        $limit = $this->option('limit');
        $max = $limit === null ? null : (int) $limit;
        $rows = [];

        foreach ($file as $row) {
            if (! is_array($row) || $row === [null] || $row === []) {
                continue;
            }

            $rows[] = $row;

            if ($max !== null && count($rows) >= $max + 1) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $header
     * @return list<string>
     */
    private function checkHeader(array $header): array
    {
        $given = $this->columnIndex($header);
        $problems = [];

        foreach (self::COLUMNS as $name => $rule) {
            if (! isset($given[strtolower($name)])) {
                $problems[] = "Missing required column: '{$name}' ({$rule['what']}).";
            }
        }

        foreach (array_keys($given) as $name) {
            if ($name !== '' && ! isset(self::COLUMNS[$this->canonical($name)])) {
                $problems[] = "Unknown column: '{$name}' — this tool maps no field to it.";
            }
        }

        return $problems;
    }

    /**
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function columnIndex(array $header): array
    {
        $index = [];

        foreach ($header as $position => $cell) {
            $index[strtolower(trim((string) $cell))] = $position;
        }

        return $index;
    }

    private function canonical(string $lowerName): string
    {
        foreach (array_keys(self::COLUMNS) as $name) {
            if (strtolower($name) === $lowerName) {
                return $name;
            }
        }

        return $lowerName;
    }

    /**
     * @param  array<string, int>  $map
     * @param  array<int, string|null>  $row
     */
    private function cell(array $row, array $map, string $header): string
    {
        $position = $map[strtolower($header)] ?? null;

        return $position === null ? '' : trim((string) ($row[$position] ?? ''));
    }
}
