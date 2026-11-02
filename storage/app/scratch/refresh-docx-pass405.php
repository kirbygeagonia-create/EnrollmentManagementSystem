<?php

/**
 * Pass 405 — §28.3 G-42's second half closed: a deactivated account stops at its next request, and the two
 * registers that charged that half to somebody else move with it.
 *
 * The owner ruled item 2 of the 2026-10-29 sheet on 2026-11-02 — stop at the next request — and the guard is
 * `App\Http\Middleware\RejectDeactivatedAccount`, appended to the web group so every request re-reads the account.
 * The refusal says the same sentence the login screen has always said, because two answers to "why was I refused"
 * is the defect this document names for year levels, for status tones and for receipt words; it now lives in
 * `App\Support\DenialReason`, which is where the project already keeps a refusal's reason.
 *
 * Three places move, and the third is the one a reader would miss: §28.8 charges owed items to an actor by a column,
 * so closing a row has to shrink the count beside it (lane 61's register-row lesson) — the Project owner's three
 * becomes two, and the list loses G-42 by name.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass405.php [--apply]
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

$verdictCell = 'Closed 2026-11-02 on the owner’s ruling of the 2026-10-29 sheet’s item 2 — stop at the next request. '
    .'`App\Http\Middleware\RejectDeactivatedAccount` is appended to the web group and re-reads the account’s own '
    .'status on every request, so a desk that was signed in when it was switched off is refused at the next act '
    .'rather than at the next login. The refusal carries the sentence the login screen already prints — one '
    .'definition, held with the other refusal reasons — flashes the desk’s input so nothing typed mid-form is '
    .'discarded, and signs the session out; the record itself is untouched, so another account can finish the act. '
    .'The status is read, never the role, because prompt §9 asks that the two stay distinct and an account may hold '
    .'every right and still be closed. Pinned by `tests/Feature/Auth/DeactivatedAccountStopsMidSessionTest.php`, '
    .'which is directional on purpose: the same account and the same route are asked on both sides of the flip, so '
    .'a guard that refused everything would fail the case as surely as one that refuses nothing; proved by '
    .'`storage/app/scratch/mutate-g42-account-stop-tests.php`, whose first leg unregisters the guard — the one '
    .'defect every case that reads the class file would sleep through. Its session-invalidation leg is WITHHELD with '
    .'its reason in the harness, because `logout()` already ends the signed-in state, so a plant that only drops '
    .'`invalidate()` cannot fail.';

$answerCell = 'Answered and built 2026-11-02: stop at the next request. `App\Http\Middleware\RejectDeactivatedAccount` '
    .'re-reads the account on every request in the web group, so the question of how long a live session may survive '
    .'its own deactivation has one answer — as long as it takes to make one more request. §28.3 G-42.';

$owesCell = 'G-13 (install-time: DEPLOYMENT.md §2 is where the proxy instruction lives, and ems:preflight now answers '
    .'whether the forwarded scheme can be trusted, reading the TRUSTED_PROXIES value it judges), G-41 (whether a '
    .'browser check belongs in the pipeline — §14’s console-error and empty/loading/responsive clause has no '
    .'instrument, so it is unverified rather than passing); G-42 was answered and built on 2026-11-02.';

$cellEdits = [
    ['A deactivated account was refused at login by one line of prose and no test', 'Half closed 2026-10-29', $verdictCell],
    ['How long may a signed-in session live after a desk deactivates the account?', 'The refusal at the login screen is built', $answerCell],
    ['G-13 (install-time: DEPLOYMENT.md', '3', '2'],
    ['G-13 (install-time: DEPLOYMENT.md', 'G-13 (install-time', $owesCell],
];

foreach ($cellEdits as $i => [$anchor, $prefix, $to]) {
    $zip = new ZipArchive;
    if ($zip->open($docx) !== true) {
        $fail('the docx did not open.');
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $anchors = substr_count($xml, $anchor);
    if ($anchors !== 1) {
        $fail("cell edit {$i} row anchor occurred {$anchors} time(s), expected exactly 1: ".substr($anchor, 0, 60));
    }
    if (preg_match('/[<>]/', $to) === 1) {
        $fail("cell edit {$i} holds a raw angle bracket.");
    }
    printf("ok   cell edit %d anchor unique (%d bytes of cell text)\n", $i, strlen($to));
}

printf("%s%d cell edit(s) prepared.\n", $apply ? '' : 'DRY RUN — ', count($cellEdits));

$engineBackup = $root.'/storage/app/scratch/pass405-engine-backup.docx';
docxApply($docx, $engineBackup, [], $cellEdits, $apply);

if (! $apply) {
    exit(0);
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    $fail('the written docx did not reopen for the read-back.');
}
$after = (string) $zip->getFromName('word/document.xml');
$zip->close();

$checks = [
    'the closed verdict landed' => str_contains($after, 'Closed 2026-11-02 on the owner’s ruling of the 2026-10-29 sheet’s item 2'),
    'the old half-closed verdict is gone from that row' => ! str_contains($after, 'Half closed 2026-10-29. The refusal is pinned by a pair'),
    'the school-question row is answered' => str_contains($after, 'Answered and built 2026-11-02: stop at the next request'),
    'the owner tally moved with its list' => substr_count($after, $owesCell) === 1,
    'the retired charge is gone' => ! str_contains($after, 'G-42 (the remaining half'),
];
$failed = array_keys(array_filter($checks, static fn (bool $v): bool => ! $v));
if ($failed !== []) {
    copy($engineBackup, $docx);
    $fail('read-back refused ['.implode('; ', $failed).'] — the docx was restored from the engine backup.');
}

echo "written, and every read-back check agreed.\n";
