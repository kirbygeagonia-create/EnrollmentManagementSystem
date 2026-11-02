<?php

/**
 * Pass 410 — the arithmetic the register's own checkers found in the two closures, and one bad pointer.
 *
 * §28.8's "Who owes what" table and §28.3's "Who must act" column are two readings of one claim, and
 * `lane69-owes-table-check.php` compares them. Closing G-37 and G-42 without moving their actor cells left the
 * column naming seven owed slots while the table totalled five — the register-row lesson this document states and
 * then commits anyway — and the closure sentences I wrote inside the two list cells named closed identifiers, which
 * the reader indexes as still owed. Both cells are now written in the comma form the instrument parses, and the
 * closed rows name no actor at all: an item with no owner is what "closed" means.
 *
 * Separately: `lane37-section-reference-sweep.php` caught the two §28.5 rows added by pass 407 pointing at a
 * section that does not exist — §7.9 is a numbering the ADS guide uses, not this document's. They now cite §13.3
 * ("How the Load Is Decided") and §24.10 ("Module 8 — Scheduling and Blocking"), which is where those two
 * mechanisms are actually written.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass410.php [--apply]
 */
$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = false;
foreach (array_slice($GLOBALS['argv'], 1) as $arg) {
    if ($arg === '--apply') {
        $apply = true;
    } else {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';

$registrarCell = 'G-33 (only its vocabulary: fixed list or free text), G-35 (where a notice takes the desk) — both asked on '
    ."Documentation/School-Decision-Sheet-2026-10-29.md with what each answer costs and how it comes back off; the "
    .'mechanisms behind G-33 and G-34 are built and pinned, and the audit action word list was answered and built on '
    .'2026-11-02.';

$ownerCell = 'G-13 (install-time: DEPLOYMENT.md §2 is where the proxy instruction lives, and ems:preflight now answers '
    .'whether the forwarded scheme can be trusted, reading the TRUSTED_PROXIES value it judges), G-41 (whether a '
    .'browser check belongs in the pipeline — §14’s console-error and empty/loading/responsive clause has no '
    .'instrument, so it is unverified rather than passing). The live-session question was answered and built on '
    .'2026-11-02, so it is no longer owed here.';

$cellEdits = [
    // §28.5's two new rows: point at sections this document actually has.
    ['What is the maximum study load one student may carry in a term', '§7.3', '§13.3; ADS problem 13'],
    ['What is a faculty member’s teaching load limit', '§7.9', '§24.10; ADS problem 14'],
    // §28.3's two closed rows name nobody, which is what a closure means for a column that asks who owes the work.
    ['The audit trail stores the row change, not the act', 'Registrar', 'No one — closed 2026-11-02'],
    ['A deactivated account was refused at login by one line of prose', 'Project owner', 'No one — closed 2026-11-02'],
    // §28.8's list cells, in the comma form the checker parses, with closed identifiers left out of the charge.
    ['G-33 (only its vocabulary: fixed list or free text)', 'G-33 (only its vocabulary', $registrarCell],
    ['G-13 (install-time: DEPLOYMENT.md', 'G-13 (install-time', $ownerCell],
];

$textEdits = [
    // §28.8's two count cells: the Registrar row moved 3 → 2 in pass 403 and the owner row in pass 405; both stay
    // consistent with the lists above, so nothing is rewritten here. The one sentence that still says "three" is the
    // Registrar row's own lead, repaired in pass 403, and §28.5's row count is weighed by lane212 rather than asserted.
    ['G-33 (only its vocabulary: fixed list or free text), G-35 (where a notice takes the desk), G-37',
        'G-33 (only its vocabulary: fixed list or free text), G-35 (where a notice takes the desk)'],
];

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

$liveTextEdits = [];
foreach ($textEdits as $i => [$from, $to]) {
    $hits = substr_count($xml, $from);
    if ($hits === 0) {
        echo "skipped text edit {$i}: its needle is already gone (an earlier pass took the clause)\n";

        continue;
    }
    if ($hits !== 1) {
        $fail("text edit {$i} occurs {$hits} time(s), expected 1 or 0.");
    }
    $liveTextEdits[] = [$from, $to];
}

foreach ($cellEdits as $i => [$anchor, $prefix, $to]) {
    if (preg_match('/[<>]/', $to) === 1) {
        $fail("cell edit {$i} holds a raw angle bracket.");
    }
    $anchors = substr_count($xml, $anchor);
    if ($anchors !== 1) {
        $fail("cell edit {$i} anchor occurred {$anchors} time(s), expected 1: ".substr($anchor, 0, 56));
    }
    printf("ok   cell edit %d anchor unique\n", $i);
}

printf("%s%d cell edit(s) and %d text edit(s) prepared.\n", $apply ? '' : 'DRY RUN — ', count($cellEdits), count($liveTextEdits));

$engineBackup = $root.'/storage/app/scratch/pass410-engine-backup.docx';
docxApply($docx, $engineBackup, $liveTextEdits, $cellEdits, $apply);

if (! $apply) {
    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the written docx did not reopen.');
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    'the two §28.5 rows now cite real sections' => str_contains($after, '§13.3; ADS problem 13')
        && str_contains($after, '§24.10; ADS problem 14') && ! str_contains($after, '§7.9; ADS problem 14'),
    'the closed rows name no actor' => substr_count($after, 'No one — closed 2026-11-02') === 2,
    'the Registrar list reads as a pair' => substr_count($after, $registrarCell) === 1,
    'the owner list reads as a pair' => substr_count($after, $ownerCell) === 1,
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($engineBackup, $docx);
    $fail('read-back refused ['.implode('; ', $failed).'] — restored from the engine backup.');
}

echo "written, and every read-back check agreed.\n";
