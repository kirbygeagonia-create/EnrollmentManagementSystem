<?php

/**
 * Docx pass 244: §20's ID-desk sentence stated a row count the dataset has outgrown.
 *
 * Line 629 (§20, the ID desk's own screen) reads "Issued lists every enrolled record whose ID box is signed — seven on
 * this box against the two the queue holds". Measured today against live `ems`, with the controller's own predicate
 * (`IDController.php:68-73`: enrollments in `enrolled` status having a `completed` step for office 22), the tab holds
 * NINE rows — 53, 92, 1063, 1064, 1065, 1066, 1067, 1284, 1285 — and the second half of the sentence is still true:
 * `idrequests` holds exactly two `pending` rows. The count drifted because the demo dataset grew, not because the
 * screen changed.
 *
 * The other copy of the phrase is NOT touched. It sits in §28's register row that records the 2026-10-17 closure
 * ("Closed 2026-10-17 by ruling, design A: … seven on this box against the two still queued"), and a dated row is a
 * record of the day it was written — re-aging it would make the row state a figure that was not true on its own day.
 * That is lane 60's rule about dated rows, applied to a count.
 *
 * The pass measures the database in stage 1 and refuses to write unless the server answers the number it is about to
 * put in the sentence — the pass-238 discipline, because "nine" recalled from this file's own note is not evidence.
 *
 *   php storage/app/scratch/refresh-docx-pass244.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass244.php --apply
 */
$root = dirname(__DIR__, 3);
$apply = in_array('--apply', $argv, true);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$scratch = $root.'/storage/app/scratch';

$env = [];
foreach ((array) @file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
        $env[$m[1]] = trim(trim($m[2]), "\"'");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
if ($schema !== 'ems') {
    printf("REFUSED: this pass measures live rows and the environment points at %s.\n", $schema ?: '(nothing)');
    exit(1);
}
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Throwable $e) {
    printf("REFUSED: cannot read %s (%s).\n", $schema, $e->getMessage());
    exit(1);
}

// The Issued tab's own predicate, copied from IDController:68-73 — not a guess about what "issued" means.
$issuedIds = array_map(static fn (array $r): string => (string) $r['enrollmentId'], $pdo->query(
    "select e.enrollmentId from enrollments e
      where e.enrollmentStatus = 'enrolled'
        and exists (select 1 from enrollmentworkflow ew join workflowsteps ws on ws.workflowId = ew.workflowId
                    where ew.enrollmentId = e.enrollmentId and ws.officeId = 22 and ws.stepStatus = 'completed')
      order by e.enrollmentId")->fetchAll(PDO::FETCH_ASSOC));
$issued = count($issuedIds);
$pending = (int) $pdo->query("select count(*) from idrequests where status = 'pending'")->fetchColumn();
printf("measured on live %s: Issued tab holds %d row(s) [%s]; pending idrequests: %d\n",
    $schema, $issued, implode(', ', $issuedIds), $pending);

$words = [1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six', 7 => 'seven',
    8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve'];
$word = $words[$issued] ?? (string) $issued;
$queueWord = $words[$pending] ?? (string) $pending;

$fails = 0;
$note = static function (string $w) use (&$fails): void {
    printf("  %s\n", $w);
};

// --- stage 1: the sentences as they stand now --------------------------------
$zip = new ZipArchive();
if (! $zip->open($docx)) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$flat = static fn (string $s): string => html_entity_decode(preg_replace('/\s+/u', ' ', $s) ?? '', ENT_QUOTES, 'UTF-8');

$needle = 'seven on this box against the two the queue holds';
$datedNeedle = 'seven on this box against the two still queued';
$at = substr_count($xml, $needle);
$atDated = substr_count($xml, $datedNeedle);
if ($at !== 1) {
    $note(sprintf('FAIL the §20 needle occurs %d time(s); expected exactly 1 (the dated register row is a separate phrase)', $at));
    $fails++;
}
if ($atDated !== 1) {
    $note(sprintf('FAIL the dated register row phrase occurs %d time(s); expected 1 — it must be left standing', $atDated));
    $fails++;
}
if ($issued < 2 || $issued > 12) {
    $note(sprintf('FAIL the measured count %d is outside the word table — refuse to write a numeral into a prose sentence', $issued));
    $fails++;
}
if ($queueWord !== 'two') {
    $note(sprintf('FAIL the queue half of the sentence is no longer "two" (it is %s) — both halves move together or neither is edited', $queueWord));
    $fails++;
}

// A number this sentence carries is about a screen's rows; the register row is the same shape dated. Check the
// replacement is not already present, which is what makes the write meaningful.
if (substr_count($xml, $word.' on this box against the two the queue holds') > 0) {
    $note(sprintf('FAIL "%s …" is already in the document — this pass has nothing to correct', $word));
    $fails++;
}

foreach (explode("\n", $flat($xml)) as $line) {
    if (! str_contains($line, $needle)) {
        continue;
    }
    $note(sprintf('   the sentence as it stands: …%s…', trim(substr($line, max(0, (int) strpos($line, $needle) - 96), 190))));
}

if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    printf("\nDRY RUN: this pass would move ONE figure in §20 — \"%s on this box\" becomes \"%s on this box\", measured\n"
        ."        from live %s just now (%d rows: %s). The 2026-10-17 register row that also says \"seven\" is left\n"
        ."        alone: a dated row records the day it was written.\n        Re-run with --apply.\n",
        'seven', $word, $schema, $issued, implode(', ', $issuedIds));
    exit(0);
}

// The engine takes a LIST of `[from, to, anchor?, expect?]` — an associative needle=>replacement array makes $edit a
// plain string, and `[$from, $to] = $edit` then yields single characters, so the first draft of this pass was refused
// by the engine's own "replacement is identical to the needle" guard.
$edits = [[$needle, $word.' on this box against the two the queue holds', null, 1]];
require_once $scratch.'/docx-edit-engine.php';
docxApply($docx, $scratch.'/pass244-backup.docx', $edits, [], true);

$check = 0;
$after = '';
foreach ($edits as [$from, $to]) {
    $z2 = new ZipArchive();
    $z2->open($docx);
    $after = (string) $z2->getFromName('word/document.xml');
    $z2->close();
    if (substr_count($after, $from) === 0 && substr_count($after, $to) === 1) {
        $check++;
    }
}
printf("%d of %d edit(s) landed as intended.\n", $check, count($edits));
printf("the dated register row still reads \"%s\": %d\n", $datedNeedle, substr_count($after, $datedNeedle));
