<?php

namespace App\Console\Commands;

use App\Enums\CivilStatus;
use App\Enums\Gender;
use App\Enums\StaffStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports what a Registrar student sheet would import and what it would refuse, and writes
 * nothing — the mapping and the refusals are the deliverable, because they are the part that
 * needs the Registrar's answers before any row may be inserted.
 *
 * The rules are not invented here. Every check below is either a NOT NULL column the schema
 * declares without a server default, or a validation rule the admission intake already applies
 * (AdmissionController::store), or a lookup the column's own foreign key demands. Where the two
 * disagree the tighter bound wins and the disagreement is printed, so the divergence is found
 * here rather than at insert time.
 */
class ImportStudents extends Command
{
    protected $signature = 'ems:import:students
                    {--csv= : A CSV carrying exactly the columns --template lists}
                    {--template : Print the column map and exit without reading anything}
                    {--limit= : Read at most this many data rows}';

    protected $description = 'Dry-run a Registrar student sheet against the real rules; writes nothing';

    /**
     * The column map: CSV header => what the app does with it.
     *
     * `db` is checked against the students table, `once` against the file itself — a repeated
     * school id inside one sheet is invisible to the database until the insert that fails.
     *
     * @var array<string, array<string, mixed>>
     */
    private const COLUMNS = [
        'school_id_number' => ['field' => 'schoolIdNumber', 'what' => 'School ID number', 'required' => true, 'max' => 150, 'db' => true, 'once' => true],
        'last_name' => ['field' => 'lastName', 'what' => 'Last name', 'required' => true, 'max' => 150],
        'first_name' => ['field' => 'firstName', 'what' => 'First name', 'required' => true, 'max' => 150],
        'middle_name' => ['field' => 'middleName', 'what' => 'Middle name', 'max' => 150],
        'suffix' => ['field' => 'suffix', 'what' => 'Suffix (Jr, III…)', 'max' => 20],
        'gender' => ['field' => 'gender', 'what' => 'Gender', 'required' => true, 'enum' => Gender::class],
        'birthdate' => ['field' => 'birthdate', 'what' => 'Date of birth (YYYY-MM-DD)', 'required' => true, 'date' => true],
        'birthplace' => ['field' => 'birthplace', 'what' => 'Place of birth', 'required' => true, 'max' => 150],
        'citizenship' => ['field' => 'citizenship', 'what' => 'Citizenship', 'required' => true, 'max' => 150],
        'contact_number' => ['field' => 'contactNumber', 'what' => 'Mobile / contact number', 'required' => true, 'max' => 20],
        'telephone_number' => ['field' => 'telephoneNumber', 'what' => 'Landline, if any', 'max' => 20],
        'email' => ['field' => 'email', 'what' => 'E-mail address', 'required' => true, 'max' => 150, 'email' => true, 'db' => true, 'once' => true],
        'username' => ['field' => 'username', 'what' => 'Account name', 'required' => true, 'max' => 150, 'db' => true, 'once' => true],
        'password' => ['field' => 'passwordHash', 'what' => 'Initial password, min 8 characters', 'min' => 8],
        'civil_status' => ['field' => 'civilStatus', 'what' => 'Single / married / widowed / separated', 'enum' => CivilStatus::class],
        'religion' => ['field' => 'religionId', 'what' => 'Religion, named as Reference Data holds it', 'lookup' => 'religion'],
        'status' => ['field' => 'status', 'what' => 'active or inactive (default active)', 'enum' => StaffStatus::class],
    ];

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

        $rows = $this->readRows($path);

        if ($rows === []) {
            $this->error('The file has no data rows.');

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
        $religions = DB::table('religions')->pluck('religionId', 'religionName')->all();
        $taken = $this->existingValues();
        $seen = [];
        $verdicts = [];
        $refused = 0;
        $needsPassword = 0;

        foreach ($rows as $offset => $row) {
            $line = $offset + 2;
            $named = $this->cell($row, $map, 'school_id_number');
            [$reasons, $warnings] = $this->checkRow($row, $map, $religions, $taken, $seen, $line, $width);

            if ($warnings !== []) {
                $needsPassword++;
            }

            if ($reasons !== []) {
                $refused++;
            }

            $verdicts[] = [
                $line,
                $named === '' ? '(blank)' : $named,
                $reasons === [] ? 'would create' : 'refuse',
                implode('; ', array_merge($reasons, $warnings)),
            ];
        }

        $this->table(['Row', 'School ID', 'Verdict', 'Why'], $verdicts);

        $this->line('');
        $this->info(sprintf('Rows read: %d   would create: %d   refused: %d', count($rows), count($rows) - $refused, $refused));

        if ($needsPassword > 0) {
            $this->warn("{$needsPassword} row(s) carry no password. students.passwordHash is NOT NULL and a spreadsheet has no business holding one, so the write path must set it — see the note below.");
        }

        $this->line('');
        $this->line('<fg=yellow>Nothing was written.</> This command has no write path. A student row is also not a student yet:');
        $this->line('  intake creates an address (required), an admission, and only later an enrollment — none of which this');
        $this->line('  tool touches. Those are the columns and the desk rules still to be agreed with the Registrar.');

        return self::SUCCESS;
    }

    private function printTemplate(): int
    {
        $this->line('Column map — a sheet must carry every header marked "yes" and no others;');
        $this->line('optional columns may be left off entirely. Names match case-insensitively.');
        $this->line('');

        $this->table(
            ['CSV header', 'Required', 'Maps to', 'Accepted'],
            collect(self::COLUMNS)->map(fn (array $rule, string $header): array => [
                $header,
                ($rule['required'] ?? false) ? 'yes' : 'no',
                $rule['field'],
                $this->acceptedNote($rule),
            ])->all()
        );

        $this->line('');
        $this->line('Header row to copy:');
        $this->line(implode(',', array_keys(self::COLUMNS)));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $rule
     */
    private function acceptedNote(array $rule): string
    {
        if (isset($rule['enum'])) {
            /** @var class-string<\BackedEnum> $enum */
            $enum = $rule['enum'];

            return implode(' | ', array_map(fn ($c) => $c->value, $enum::cases()));
        }

        return match (true) {
            isset($rule['date']) => 'a calendar date',
            isset($rule['email']) => 'a valid address, unique in the table',
            isset($rule['lookup']) => 'matched against Reference Data',
            isset($rule['min']) => 'min '.$rule['min'].' characters',
            default => 'up to '.($rule['max'] ?? 150).' characters',
        };
    }

    /**
     * @return array<int, array<int, string|null>>
     */
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

            if ($max !== null && count($rows) > $max + 1) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  array<int, string|null>  $header
     * @return array<int, string>
     */
    private function checkHeader(array $header): array
    {
        $given = $this->columnIndex($header);
        $problems = [];

        foreach (self::COLUMNS as $name => $rule) {
            if (($rule['required'] ?? false) && ! isset($given[strtolower($name)])) {
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
     * Header name (lower-cased) => its position in the row. Optional columns may be absent, so
     * this is the only place a cell is located; readers must treat a missing key as a blank cell.
     *
     * @param  array<int, string|null>  $header
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
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     * @param  array<string, int>  $religions
     * @param  array<string, array<int, string>>  $taken
     * @param  array<string, array<string, int>>  $seen  values already met in this file, per column
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function checkRow(array $row, array $map, array $religions, array $taken, array &$seen, int $line, int $width): array
    {
        $reasons = [];
        $warnings = [];

        if (count($row) !== $width) {
            return [[
                'the row carries '.count($row).' fields where the header declares '.$width
                .' — an unquoted comma inside a value splits it into two and shifts every column after it',
            ], []];
        }

        foreach (self::COLUMNS as $header => $rule) {
            $value = $this->cell($row, $map, $header);

            if ($value === '') {
                if ($rule['required'] ?? false) {
                    $reasons[] = "{$header} is required ({$rule['what']})";
                }

                continue;
            }

            if (isset($rule['max']) && mb_strlen($value) > $rule['max']) {
                $reasons[] = "{$header} is ".mb_strlen($value).' characters, the column holds '.$rule['max'];
            }

            if (isset($rule['min']) && mb_strlen($value) < $rule['min']) {
                $reasons[] = "{$header} is shorter than {$rule['min']} characters";
            }

            if (isset($rule['enum'])) {
                /** @var class-string<\BackedEnum> $enum */
                $enum = $rule['enum'];
                $allowed = array_map(fn ($c) => $c->value, $enum::cases());

                if (! in_array($value, $allowed, true)) {
                    $reasons[] = "{$header} '{$value}' is not one of ".implode(', ', $allowed);
                }
            }

            if (($rule['date'] ?? false) && $this->parseDate($value) === null) {
                $reasons[] = "{$header} '{$value}' is not a date the system can store";
            }

            if (($rule['email'] ?? false) && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                $reasons[] = "{$header} '{$value}' is not a valid address";
            }

            if (isset($rule['lookup'])) {
                // The map's only lookup is religion, so its value names the thing in the message
                // rather than being compared against a literal it can only ever equal.
                if (! isset($religions[$value])) {
                    $reasons[] = "{$header} '{$value}' matches no {$rule['lookup']} in Reference Data";
                }
            }

            if (($rule['db'] ?? false) && in_array(strtolower($value), $taken[$header] ?? [], true)) {
                $reasons[] = "{$header} '{$value}' is already on file";
            }

            if (($rule['once'] ?? false)) {
                $key = strtolower($value);

                if (isset($seen[$header][$key])) {
                    $reasons[] = "{$header} '{$value}' repeats row {$seen[$header][$key]} of this file";
                } else {
                    $seen[$header][$key] = $line;
                }
            }
        }

        if ($this->cell($row, $map, 'password') === '') {
            $warnings[] = 'no password — the write path must set one';
        }

        return [$reasons, $warnings];
    }

    private function parseDate(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * The values already in the table, lower-cased because MySQL compares them case-insensitively
     * while this command's own file check does not.
     *
     * @return array<string, array<int, string>>
     */
    private function existingValues(): array
    {
        $taken = [];

        foreach (self::COLUMNS as $header => $rule) {
            if (! ($rule['db'] ?? false)) {
                continue;
            }

            $taken[$header] = DB::table('students')->pluck($rule['field'])
                ->map(fn ($v): string => strtolower((string) $v))
                ->all();
        }

        return $taken;
    }

    /**
     * The cell for a mapped header, or '' when the sheet carries no such column at all —
     * optional columns may legitimately be absent.
     *
     * @param  array<int, string|null>  $row
     * @param  array<string, int>  $map
     */
    private function cell(array $row, array $map, string $header): string
    {
        $position = $map[strtolower($header)] ?? null;

        return $position === null ? '' : trim((string) ($row[$position] ?? ''));
    }
}
