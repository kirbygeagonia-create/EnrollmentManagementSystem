<?php
/**
 * Lane 107b: read the ENUM order from the eager load, and pin the payload the sort join used to widen.
 *
 * The fee-sheet screens' new enum cases read `studentType` off the TOP level of a `studentassessments` row. That key
 * is not the sheet's: it arrived because `ListSort` joined `enrollments` and the builder emitted a bare `select *`,
 * which answered the hop's columns too — and on a Name order the same join reached `students`, whose `passwordHash`,
 * `birthdate` and `contactNumber` then shipped inside every row the cashier and the assessment officer saw.
 * `Students::$hidden` cannot withhold them, because the value is not riding on a Students model.
 *
 * So per class: the enum case reads where the SCREEN reads (`enrollment.studentType`), and a new case asserts that a
 * row carries the same columns sorted and unsorted, naming the three fields that leaked.
 *
 * Run: php storage/app/scratch/lane107b-read-the-relation-not-the-leak.php [--apply]
 */

$apply = in_array('--apply', $argv, true);
$root = dirname(__DIR__, 3);

$topType = "array_map(fn (array \$r): string => is_array(\$r['studentType'] ?? null) ? (string) \$r['studentType']['value'] : (string) (\$r['studentType'] ?? ''), \$this->rows(\$types))";
$relType = "array_map(fn (array \$r): string => (string) (\$r['enrollment']['studentType'] ?? ''), \$this->rows(\$types))";
$topStand = "array_map(fn (array \$r): string => is_array(\$r['academicStanding'] ?? null) ? (string) \$r['academicStanding']['value'] : (string) (\$r['academicStanding'] ?? ''), \$this->rows(\$standings))";
$relStand = "array_map(fn (array \$r): string => (string) (\$r['enrollment']['academicStanding'] ?? ''), \$this->rows(\$standings))";

/**
 * The tail of a class file: the last method's brace and the class's own brace. Unique, because every other
 * closing brace in the file is followed by a blank line and another method, not by `}` at column zero.
 */
const TAIL = "    }\n}\n";

$leakCase = function (string $actor, string $route, string $prop, string $make, string $why): string {
    return <<<PHP

    #[Test]
    public function an_order_that_joins_a_relation_ships_the_rows_own_columns_and_nothing_else(): void
    {
        // {$why}
        // The join exists only to order the page, and a builder with no select list emits `select *`, which then
        // answers the hop's columns too: this row used to arrive carrying the student's passwordHash, birthdate
        // and contact number, and no `$hidden` on the Students model can withhold them, because the value is not
        // riding on a Students model — it is an attribute of the row being listed. The screen reads those fields
        // off its own eager load, so pinning the select changes nothing the desk asks for and everything it ships.
        {$make}

        \$plain = \$this->rows(\$this->actingAs(\$this->{$actor})->get(route('{$route}')));
        \$twoHop = \$this->rows(\$this->actingAs(\$this->{$actor})->get(route('{$route}', ['sort' => 'name', 'direction' => 'asc'])));
        \$oneHop = \$this->rows(\$this->actingAs(\$this->{$actor})->get(route('{$route}', ['sort' => 'studentType', 'direction' => 'asc'])));

        \$keys = function (array \$rows): array {
            \$out = array_keys(\$rows[0]);
            sort(\$out);

            return \$out;
        };

        \$this->assertSame(\$keys(\$plain), \$keys(\$twoHop), 'a Name order must not change which columns a {$prop} row carries');
        \$this->assertSame(\$keys(\$plain), \$keys(\$oneHop), 'a Student Type order must not change which columns a {$prop} row carries');
        foreach (['passwordHash', 'birthdate', 'contactNumber'] as \$leaked) {
            \$this->assertArrayNotHasKey(\$leaked, \$twoHop[0], "{\$leaked} reached the payload through the join");
        }
    }
PHP;
};

$edits = [
    'tests/Feature/Sorting/AssessmentQueueSortTest.php' => [
        ['from' => $topType, 'to' => $relType],
        ['from' => $topStand, 'to' => $relStand],
        ['from' => TAIL, 'to' => $leakCase(
            'officer', 'assessment.index', 'fee-sheet',
            "\$this->sheet('Zulueta', 'AS-020', 7000, 1000);\n        \$this->sheet('Bautista', 'AS-021', 5000, 3000);",
            'The fee sheet is the row and the student sits two hops away — the widest join this helper builds.'
        )."\n".TAIL,
        ],
    ],
    'tests/Feature/Sorting/AccountingQueueSortTest.php' => [
        ['from' => $topType, 'to' => $relType],
        ['from' => $topStand, 'to' => $relStand],
        ['from' => TAIL, 'to' => $leakCase(
            'cashier', 'accounting.index', 'fee-sheet',
            "\$this->sheet('Zulueta', 'AC-020', 7000, 1000, '2026-09-01');\n        \$this->sheet('Bautista', 'AC-021', 5000, 3000, '2026-09-02');",
            'The cashier queue joins the same two hops under a Name order, over the row a receipt is written against.'
        )."\n".TAIL,
        ],
    ],
    // The Registrar queue lists enrollments, so its enum case is right to read the row's own column: only the
    // one hop into `students` was ever foreign to it.
    'tests/Feature/Registrar/RegistrarQueueSortTest.php' => [
        ['from' => TAIL, 'to' => $leakCase(
            'registrar', 'registrar.index', 'enrollment',
            "\$this->assessed('Zulueta');\n        \$this->assessed('Bautista');",
            'An enrollment row carries its own columns; the student it belongs to arrives only through the name join.'
        )."\n".TAIL,
        ],
    ],
];

$failures = [];
$plan = [];
foreach ($edits as $file => $list) {
    $bytes = file_get_contents($root.'/'.$file);
    if ($bytes === false) {
        $failures[] = "cannot read {$file}";
        continue;
    }
    foreach ($list as $i => $edit) {
        if ($edit['from'] === $edit['to']) {
            $failures[] = "{$file} edit {$i}: needle and replacement are the same bytes (no-op)";
            continue;
        }
        $count = substr_count($bytes, $edit['from']);
        if ($count !== 1) {
            $failures[] = sprintf('%s edit %d: needle matched %d time(s), wanted exactly 1 — %s', $file, $i, $count, str_replace("\n", '\n', substr($edit['from'], 0, 72)));
            continue;
        }
        $plan[] = ['file' => $file, 'edit' => $edit];
    }
}

if ($failures !== []) {
    fwrite(STDERR, "REFUSED — nothing written:\n".implode("\n", $failures)."\n");
    exit(1);
}

echo sprintf('%d edit(s) resolved uniquely over %d file(s).', count($plan), count($edits)), PHP_EOL;
if (! $apply) {
    echo "DRY RUN\n";
    exit(0);
}

foreach ($plan as $p) {
    $path = $root.'/'.$p['file'];
    $bytes = file_get_contents($path);
    $out = str_replace($p['edit']['from'], $p['edit']['to'], $bytes);
    if ($out === $bytes) {
        fwrite(STDERR, "REFUSED: writing {$p['file']} changed nothing\n");
        exit(1);
    }
    file_put_contents($path, $out);
}

foreach (['tests/Feature/Sorting/AssessmentQueueSortTest.php', 'tests/Feature/Sorting/AccountingQueueSortTest.php', 'tests/Feature/Registrar/RegistrarQueueSortTest.php'] as $f) {
    $now = file_get_contents($root.'/'.$f);
    printf("%s: #[Test] markers = %d, top-level leaked mapper = %d, relation mapper = %d\n", $f, substr_count($now, '#[Test]'), substr_count($now, $topType), substr_count($now, $relType));
}
echo "APPLIED\n";
