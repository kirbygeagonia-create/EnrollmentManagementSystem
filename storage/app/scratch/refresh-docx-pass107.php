<?php

/**
 * Pass 107 — the line citations this batch's own edits moved, and three counts the document got wrong.
 *
 * Seven citations, each re-measured by meaning rather than by arithmetic: the number has to land on the
 * construct the sentence describes, so every replacement below is checked against the real file before the
 * document is written (see the `$cite` guards). Four of them had moved with this batch's edits
 * (Auditlogs.php, UserManagementController.php, MegaAppLauncher.jsx, DevReferenceDataSeeder.php ×2), and
 * two were already wrong when this session opened (DeskSubNav.jsx, and the RbacSeeder span that hid the
 * first of the seventeen calls).
 *
 * Three claims were not citation drift at all, and each was measured before being rewritten:
 *   · "the seeder prints no role figure at all" — RbacSeeder.php:483 prints `Role::count()`. The hardcoded
 *     "13"/"14" pair is gone; what remains is a measured count, which is a different sentence.
 *   · "ninety-four permissions" — live ems reads 93, and this section's own §22 header says 93.
 *   · "15 of 15 staff accounts" — the install now has 21 accounts, and all 21 still answer the three
 *     queues (probe-live-queue-answers-21.php, discriminated by mutate-queue-probe.php).
 *
 * Offsets are re-derived from the file on every run; edits apply highest-first so no earlier offset is
 * shifted; one offset carries exactly one edit. DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

/** Queue a whole-string swap, insisting on the occurrence count the plan says it should have. */
$swap = function (string $from, string $to, string $label, int $expect = 1) use ($xml, &$edits, &$refuse): void {
    $n = substr_count($xml, $from);
    if ($n !== $expect) {
        $refuse("{$label}: anchor appears {$n} time(s), expected {$expect} — 「{$from}」");

        return;
    }

    $at = 0;
    for ($i = 0; $i < $expect; $i++) {
        $at = strpos($xml, $from, $at);
        if ($at === false) {
            $refuse("{$label}: occurrence {$i} vanished mid-scan");

            return;
        }
        $edits[] = [$at, strlen($from), $to, $label.($expect > 1 ? ' #'.($i + 1) : '')];
        $at += strlen($from);
    }
};

// ─── what each corrected number must prove about the code, read from the file right now ────────────────
$fileLines = [];
$linesOf = function (string $rel) use ($root, &$fileLines): array {
    return $fileLines[$rel] ??= file($root.'/'.$rel);
};

/** The slice of source a citation will point at after the edit, flattened to one line. */
$span = function (string $rel, int $from, int $to) use ($linesOf): string {
    return trim(preg_replace('/\s+/', ' ', implode(' ', array_slice($linesOf($rel), $from - 1, $to - $from + 1))));
};

// ─── 1. the two chrome citations that name Blocking's Phase 6 ──────────────────────────────────────────
// Both must land on the descriptor's own `phase:` line, which is what the sentence is about.
foreach ([
    ['resources/js/Components/navigation/DeskSubNav.jsx', 71, 74],
    ['resources/js/Components/navigation/MegaAppLauncher.jsx', 133, 139],
] as [$rel, $old, $new]) {
    $base = basename($rel);
    $line = trim($linesOf($rel)[$new - 1]);
    if ($line !== "phase: 'Phase 6',") {
        $refuse("{$base}: line {$new} reads 「{$line}」, not the Phase 6 label the sentence cites");

        continue;
    }
    $swap($base.':'.$old, $base.':'.$new, "cite {$base}");
}

// AuthenticatedLayout.jsx:19 is the third cite in the same sentence and is NOT edited — prove it holds.
$layoutLine = trim($linesOf('resources/js/Layouts/AuthenticatedLayout.jsx')[18]);
if (! str_contains($layoutLine, "blocking:") || ! str_contains($layoutLine, "phase: 'Phase 6'")) {
    $refuse('AuthenticatedLayout.jsx:19 no longer names the blocking phase — the untouched cite is stale too');
}

// ─── 2. the model cast span, which this batch pushed below its own new docblock ────────────────────────
$spanText = $span('app/Models/Auditlogs.php', 31, 41);
foreach (['protected $fillable', "'oldValues' => 'array'", "'adminOverride' => 'boolean'"] as $must) {
    if (! str_contains($spanText, $must)) {
        $refuse("Auditlogs.php:31-41 does not hold 「{$must}」 — the cite would still be wrong");
    }
}
if (str_contains($spanText, 'booted')) {
    $refuse('Auditlogs.php:31-41 has swallowed the booted() hook, which the sentence does not describe');
}
$swap('Auditlogs.php:16-25', 'Auditlogs.php:31-41', 'cite Auditlogs.php casts');

// ─── 3. the Settings screen's read and write, which this batch's deletions shifted ─────────────────────
// Measured: settings() at 463-472 reads the table, updateSetting() at 474-483 writes it.
$spanText = $span('app/Http/Controllers/Admin/UserManagementController.php', 463, 483);
foreach (['public function settings(', 'Settings::all()', 'public function updateSetting(', '$setting->update('] as $must) {
    if (! str_contains($spanText, $must)) {
        $refuse("UserManagementController.php:463-483 does not hold 「{$must}」");
    }
}
$swap('UserManagementController.php:380-397', 'UserManagementController.php:463-483', 'cite Settings screen');

// ─── 4. the admission-requirements block, cited twice ─────────────────────────────────────────────────
// Both sentences name the seeded requirement set; the block is now 725-736 and 711-722 is the fee list.
$spanText = $span('database/seeders/DevReferenceDataSeeder.php', 725, 736);
foreach (['PSA Birth Certificate', 'Form 138', 'Honorable Dismissal', 'Good Moral Character', 'Admissionrequirements::firstOrCreate'] as $must) {
    if (! str_contains($spanText, $must)) {
        $refuse("DevReferenceDataSeeder.php:725-736 does not hold 「{$must}」");
    }
}
if (str_contains($spanText, 'Laboratory Fee')) {
    $refuse('DevReferenceDataSeeder.php:725-736 still reaches the fee list — wrong block');
}
$swap('DevReferenceDataSeeder.php:711-722', 'DevReferenceDataSeeder.php:725-736', 'cite admission requirements', 2);

// ─── 5. the seventeen role calls ──────────────────────────────────────────────────────────────────────
// "run from :226 to :477" cannot be checked by a reader: only 16 calls sit in that span, because :226 is
// the closing parenthesis of the first one. 223-474 are the call lines themselves.
$calls = 0;
for ($i = 223; $i <= 474; $i++) {
    $calls += substr_count($linesOf('database/seeders/RbacSeeder.php')[$i - 1], 'Role::updateOrCreate(');
}
if ($calls !== 17) {
    $refuse("RbacSeeder.php:223-474 holds {$calls} Role::updateOrCreate calls, not the seventeen the sentence states");
}
$swap('RbacSeeder.php:226 to :477', 'RbacSeeder.php:223 to :474', 'cite the role calls');

// ─── 6. what the seeder actually prints ───────────────────────────────────────────────────────────────
foreach ([198 => 'count($createdPermissions)', 483 => 'Role::count()'] as $ln => $must) {
    if (! str_contains($linesOf('database/seeders/RbacSeeder.php')[$ln - 1], $must)) {
        $refuse("RbacSeeder.php:{$ln} does not print the measured count 「{$must}」");
    }
}
$swap(
    'were both removed, so the seeder prints no role figure at all and no count can be read from its output.',
    'were both removed, so no figure the seeder prints is one it remembers: the two counts that remain — '
    ."'Created N permissions across M modules' and 'Created N desk and faculty roles with module "
    ."permissions' — are read from the database at RbacSeeder.php:198 and :483 instead of typed in here.",
    'the seeder output clause'
);

// ─── 7. the permission total, and the account denominator ─────────────────────────────────────────────
// Live ems: 93 permissions, 17 roles, 21 staff accounts; §22's own header already states 93 in digits.
$counts = [];
try {
    require_once $root.'/vendor/autoload.php';
    $app = require_once $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

    // The connection comes from the ordinary DB_* env through the framework, never from a literal here.
    foreach (['permissions', 'roles', 'staffusers'] as $table) {
        $counts[$table] = (int) Illuminate\Support\Facades\DB::table($table)->count();
    }
} catch (Throwable $e) {
    $refuse('live ems unreachable ('.$e->getMessage().'), so the three counts would be written unmeasured');
}

if ($counts !== []) {
    printf("live ems measured: %d permissions, %d roles, %d staff accounts\n",
        $counts['permissions'], $counts['roles'], $counts['staffusers']);
    if ($counts['permissions'] !== 93 || $counts['roles'] !== 17 || $counts['staffusers'] !== 21) {
        $refuse('the counts this pass writes no longer match live ems — re-measure before editing');
    }
}
$swap('ninety-four permissions', 'ninety-three permissions', 'the permission total');
$swap(
    'answer for 15 of 15 staff accounts',
    'answer for 21 of 21 staff accounts — re-measured 2026-10-13, after the six per-college department '
    .'desks joined the install, by asking each account the permission its own policy asks; the denominator '
    .'moved and the verdict did not',
    'the queue denominator'
);

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-38s byte %8d  %4d → %4d chars\n", $label, $off, $len, strlen($to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    // The three chrome cites in one sentence: two moved, the third was already right and must stay.
    'every old citation string is gone' => substr_count($xml, 'Auditlogs.php:16-25') === 0
        && substr_count($xml, 'UserManagementController.php:380-397') === 0
        && substr_count($xml, 'DevReferenceDataSeeder.php:711-722') === 0
        && substr_count($xml, 'DeskSubNav.jsx:71') === 0
        && substr_count($xml, 'MegaAppLauncher.jsx:133') === 0
        && substr_count($xml, 'RbacSeeder.php:226') === 0
        && substr_count($xml, 'ninety-four') === 0
        && substr_count($xml, '15 of 15 staff accounts') === 0
        && substr_count($xml, 'no role figure at all') === 0,
    'the corrected citations are present, the right number of times' => substr_count($xml, 'Auditlogs.php:31-41') === 1
        && substr_count($xml, 'UserManagementController.php:463-483') === 1
        && substr_count($xml, 'DevReferenceDataSeeder.php:725-736') === 2
        && substr_count($xml, 'DeskSubNav.jsx:74') === 1
        && substr_count($xml, 'MegaAppLauncher.jsx:139') === 1
        && substr_count($xml, 'RbacSeeder.php:223 to :474') === 1,
    'AuthenticatedLayout.jsx:19 survives untouched' => substr_count($xml, 'AuthenticatedLayout.jsx:19') === 1,
    // The document must agree with itself: §22's header states the total in digits, §22.9 in words.
    'the permission total now matches §22’s own header' => substr_count($xml, 'ninety-three permissions') === 1
        && str_contains($xml, '93 permissions across 14 modules'),
    'the seeder clause names the two lines it measured' => substr_count($xml, 'RbacSeeder.php:198 and :483') === 1,
    'the queue sentence now reads 21 of 21' => substr_count($xml, 'answer for 21 of 21 staff accounts') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass107.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
