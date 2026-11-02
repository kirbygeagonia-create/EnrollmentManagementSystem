<?php

/**
 * Proves `lane194-ads-artefact-citations.php` can fail.
 *
 * The census shipped on the strength of a zero exit and a clean list, which is precisely the evidence this
 * repository does not accept: "before believing a clean verdict from any checker, feed it a mutant and watch it
 * fail." A checker that cannot report a missing file is decoration over the exact defect it was written for — the
 * phantom `EnrollmentReadinessTest.php` citation it found on its first real run would have been found by nothing
 * else in the tree.
 *
 * The tool is read-only, so the plants go into a fixture document directory reached through its own `--docs=`
 * glob rather than into the tracked deliverables, and an untracked file is planted in scratch where the extension
 * rules already hide it. Everything is removed at the end, and a leg that must stay silent is asserted silent.
 *
 * Usage: php storage/app/scratch/mutate-lane194-ads-citations.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tool = 'storage/app/scratch/lane194-ads-artefact-citations.php';
$fxDir = 'storage/app/scratch/lane194-fixture';
$untrackedProbe = 'storage/app/scratch/lane194-untracked-probe.txt';
$trackedList = 'storage/app/scratch/lane194-tracked.txt';
if (! is_file($php) || ! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the interpreter or the census is missing.\n");
    exit(1);
}

$bad = 0;
$report = static function (bool $ok, string $leg, string $detail) use (&$bad): void {
    printf("%s %-34s %s\n", $ok ? 'ok  ' : 'BAD ', $leg, $detail);
    if (! $ok) {
        $bad++;
    }
};

$run = static function (string $args) use ($php, $tool): array {
    exec($php.' '.escapeshellarg($tool).' '.$args.' 2>&1', $out, $code);

    return ['text' => implode("\n", $out), 'code' => $code];
};

// The real tracked list, refreshed from the index, so the UNTRACKED legs measure git's answer and not a remembered file.
$trackedNow = 'storage/app/scratch/lane194-tracked-live.txt';
exec('git ls-files > '.escapeshellarg($trackedNow).' 2>&1');
if (! is_file($trackedNow) || trim((string) file_get_contents($trackedNow)) === '') {
    fwrite(STDERR, "REFUSED: git produced no tracked list, so the tracking legs would measure nothing.\n");
    exit(1);
}

@mkdir($root.'/'.$fxDir, 0777, true);
$fixture = static function (string $name, string $body) use ($fxDir, $root): void {
    if (file_put_contents($root.'/'.$fxDir.'/'.$name, $body) === false) {
        throw new RuntimeException("fixture {$name} could not be written — a plant nobody verified is not a plant");
    }
};

// Leg 1 — the population guard: a glob that matches no document must refuse, not report a clean sweep over
// nothing. This is the defect lane 43 and lane 46 both recorded as "an empty verdict that looks like a pass".
$r = $run('--docs=storage/app/scratch/lane194-nothing-*.md --tracked='.escapeshellarg($trackedNow));
$report($r['code'] === 1 && str_contains($r['text'], 'matched no document'), 'empty-population-refuses',
    str_contains($r['text'], 'matched no document') ? 'refused before judging' : trim(substr($r['text'], 0, 160)));

// Leg 2 — an unknown flag is refused by name.
$r = $run('--no-such-flag-at-all');
$report($r['code'] === 1 && str_contains($r['text'], 'flag(s) not read by this tool'), 'unknown-flag-refused-by-name',
    str_contains($r['text'], 'flag(s) not read by this tool') ? 'named the flag' : trim(substr($r['text'], 0, 160)));

// Legs 3 and 4 — the tracking half cannot be skipped by a typo or an empty list.
$r = $run('--tracked=storage/app/scratch/definitely-not-here.txt');
$report($r['code'] === 1 && str_contains($r['text'], 'is not a file'), 'missing-tracked-list-refuses',
    str_contains($r['text'], 'is not a file') ? 'refused by name' : trim(substr($r['text'], 0, 160)));

file_put_contents($root.'/storage/app/scratch/lane194-empty-tracked.txt', "\n\n");
$r = $run('--tracked=storage/app/scratch/lane194-empty-tracked.txt');
$report($r['code'] === 1 && str_contains($r['text'], 'holds no paths'), 'empty-tracked-list-refuses',
    str_contains($r['text'], 'holds no paths') ? 'refused rather than calling everything untracked' : trim(substr($r['text'], 0, 160)));

// Leg 5 — MISSING-FILE actually fires: a fixture document citing a path that exists nowhere.
$fixture('MISSING.md', "evidence in `storage/app/scratch/zz-lane194-no-such-file-9e3.md` and `tests/Feature/Zz/NopeTest.php`\n");
$r = $run('--docs=storage/app/scratch/lane194-fixture/*.md --tracked='.escapeshellarg($trackedNow));
$report($r['code'] === 1 && str_contains($r['text'], 'MISSING-FILE  storage/app/scratch/zz-lane194-no-such-file-9e3.md')
    && str_contains($r['text'], 'MISSING-FILE  tests/Feature/Zz/NopeTest.php'), 'missing-file-is-found',
    str_contains($r['text'], 'MISSING-FILE') ? 'both phantoms named' : trim(substr($r['text'], 0, 200)));

// Leg 5b — the scope limit is PRINTED. The first run of this harness found that a bare `file.md` with no directory
// part is not matched at all, so a citation written that way would be silently unjudged; the tool now says so, and
// this leg fails the day the sentence is dropped and the gap goes back to being invisible.
$report(str_contains($r['text'], 'bare filenames with no directory part are out of scope'),
    'scope-limit-is-printed', str_contains($r['text'], 'out of scope') ? 'stated in the run' : 'the limit line vanished');

// Leg 6 — UNTRACKED fires: a file that exists on this box (the planted probe, hidden by the extension rules) and
// is cited, must be reported even though it exists. This is the morning's defect, made a gate.
file_put_contents($root.'/'.$untrackedProbe, "planted by mutate-lane194-ads-citations.php so a citation can resolve to a file git has never seen\n");
$fixture('UNTRACKED.md', "the capture is `storage/app/scratch/lane194-untracked-probe.txt`\n");
@unlink($root.'/'.$fxDir.'/MISSING.md');
$r = $run('--docs=storage/app/scratch/lane194-fixture/*.md --tracked='.escapeshellarg($trackedNow));
$report($r['code'] === 1 && str_contains($r['text'], 'UNTRACKED     storage/app/scratch/lane194-untracked-probe.txt'),
    'untracked-file-is-found', str_contains($r['text'], 'UNTRACKED     storage/app/scratch/lane194-untracked-probe.txt')
        ? 'named, despite existing on disk' : trim(substr($r['text'], 0, 220)));

// Leg 7 — the BY-DESIGN exemption is read, not tolerated: the guide's own `docs/X.md` deliverable names must NOT
// be counted as problems, and must be printed so the exemption is visible.
$fixture('BYDESIGN.md', "The guide asks for `docs/SYSTEM_ARCHITECTURE.md`; this repository ships `Documentation/EVIDENCE-PACK-ADS.md` in its place.\n");
@unlink($root.'/'.$fxDir.'/UNTRACKED.md');
$r = $run('--docs=storage/app/scratch/lane194-fixture/*.md --tracked='.escapeshellarg($trackedNow));
$exempted = str_contains($r['text'], 'BY-DESIGN 1 path(s)') && str_contains($r['text'], 'docs/SYSTEM_ARCHITECTURE.md')
    && ! str_contains($r['text'], 'MISSING-FILE  docs/SYSTEM_ARCHITECTURE.md');
$report($exempted && $r['code'] === 0, 'by-design-exemption-is-honoured', $exempted
    ? 'the guide path was exempted, printed by name, and did not become a problem'
    : trim(substr($r['text'], 0, 220)));

// Leg 8 — positive control on the real tree: the shipped deliverables pass, so legs 5-7 are not a tool that only
// ever complains.
$r = $run('--tracked='.escapeshellarg($trackedNow));
$report($r['code'] === 0 && str_contains($r['text'], 'every path the ADS deliverables cite exists'),
    'real-deliverables-pass', str_contains($r['text'], 'distinct path citations')
        ? trim(strstr($r['text'], 'distinct path citations')) : trim(substr($r['text'], 0, 200)));

// Teardown: plants and fixture tree both gone, or a harness leaves a second definition of the evidence.
foreach ([$root.'/'.$fxDir.'/DELIVERY.md', $root.'/'.$fxDir.'/BYDESIGN.md', $root.'/'.$untrackedProbe,
    $root.'/storage/app/scratch/lane194-empty-tracked.txt', $root.'/'.$trackedList, $root.'/'.$trackedNow] as $p) {
    @unlink($p);
}
@rmdir($root.'/'.$fxDir);
$leftovers = glob($root.'/storage/app/scratch/lane194-{fixture*,untracked-probe*,empty-tracked*,tracked*}', GLOB_BRACE) ?: [];
$report($leftovers === [], 'plants-retired', $leftovers === [] ? 'nothing left behind' : implode(', ', array_map('basename', $leftovers)));

printf("\n%s\n", $bad === 0 ? 'ALL KILLED: the census finds phantoms and untracked evidence, honours its printed exemptions, refuses a skipped tracking half, and still passes the real deliverables.'
    : $bad.' leg(s) BAD — read them as the harness or the census, never as a clean run.');
exit($bad === 0 ? 0 : 1);
