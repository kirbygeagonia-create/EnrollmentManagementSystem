<?php

/**
 * Pass 222 — §24's scholarshiptypes row states a label the schema cannot hold.
 *
 * The row read "Two types exist on live ems, both seeded and both still labelled provisional". `describe
 * scholarshiptypes` on live `ems` answers four columns — scholarshipTypeId, scholarshipName, coverageType
 * enum('full','partial'), coveragePercent decimal(10,2) — and no column carries a status, so nothing in the
 * repository labels either row provisional: `grep -rn provisional` over `app/` and the seeders answers zero for
 * scholarship types. The two rows are StarterReferenceDataSeeder.php:52-53, full at 100.00 and partial at 50.00.
 *
 * This is the same family as §21's three columns no table holds (G-28), reached from the other side: not a stale
 * figure but an invented field. It also explains why `lane69-open-inventory.php` charged the row as owed work — the
 * cell names a screen path (`Admin → Reference Data → Scholarship Types`) which an actor-reading classifier takes
 * for a desk that must act, and the sentence beside it carried the invented word.
 *
 *   php storage/app/scratch/refresh-docx-pass222.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass222.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$seeder = (array) file($root.'/database/seeders/StarterReferenceDataSeeder.php');
$fails = 0;
foreach ([
    [$seeder, 52, "'scholarshipName' => 'Full Scholarship', 'coverageType' => 'full', 'coveragePercent' => 100.00"],
    [$seeder, 53, "'scholarshipName' => 'Partial Scholarship', 'coverageType' => 'partial', 'coveragePercent' => 50.00"],
] as [$l, $n, $needle]) {
    $t = trim($l[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s seeder :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}

// The claim to be written says the schema has no status column: ask the server, do not recall it. The .env this
// box boots with already names `ems`, so no override is passed — a probe that silently read a scratch schema
// would answer the question about the wrong database.
require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cols = array_map(static fn ($r) => $r->Field, Illuminate\Support\Facades\DB::select('describe scholarshiptypes'));
printf("  scholarshiptypes columns on live ems: %s\n", implode(', ', $cols));
$hasStatus = array_filter($cols, static fn (string $c): bool => str_contains(strtolower($c), 'status') || str_contains(strtolower($c), 'provisional'));
if ($hasStatus !== []) {
    printf("REFUSED: the table does carry a status-shaped column (%s), so the row's word was not invented.\n", implode(', ', $hasStatus));
    exit(1);
}
$fails += 0;
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
printf("\n  needle [both still labelled provisional] occurs %d time(s) in document.xml (want 1)\n", substr_count($xml, 'both still labelled provisional'));
if (substr_count($xml, 'both still labelled provisional') !== 1) {
    echo "REFUSED: the phrase to repair is not unique, so it must go through the cell kind instead.\n";
    exit(1);
}

docxApply(
    $src,
    $root.'/storage/app/scratch/ems-docx-pre-pass222.docx',
    [['both still labelled provisional',
        'and neither is marked provisional because the table has no status column — `coverageType` is the only '
        .'discriminator, enum full or partial ( StarterReferenceDataSeeder.php:52-53 )',
        null, 1]],
    [],
    $apply
);
