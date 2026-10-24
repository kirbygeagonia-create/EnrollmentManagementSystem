<?php

/**
 * Pass 186 — three register rows still describe as open what the owner ruled on 2026-10-24.
 *
 * The decision sheet was answered in the working tree first; this is the authority document following
 * in the same batch, which is the standing rule. Three rows, and only three, say something the ruling
 * has now settled:
 *
 *   §28.5  the certificate row ends "The words themselves are still the school's" while stating the
 *          sentences are the developer's choice — ruled: as printed, they are the school's now.
 *   §28.2  C-7 ends "Whether every term must be re-determined as a matter of policy is the open
 *          question." — ruled: every term.
 *   §28.2  C-10 says the production answer is owed; reading the listeners narrowed what a worker is
 *          ever for here, and the owner answered for this install.
 *
 * §28.2's C-2, C-4, C-6 and C-8 rows already led "Answered", so they are deliberately untouched: the
 * sheet was the lagging artifact for those, not the document.
 *
 *   php storage/app/scratch/refresh-docx-pass186.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass186.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    [
        'The words themselves are still the school’s — the sentences now printed are the ones the developer chose, and §25 records them as the code’s wording rather than SEAIT’s.',
        'The words were ruled on 2026-10-24 to be the school’s: the sentences the certificate prints today — student type in its own words, enrollment kind stated separately — are adopted as SEAIT’s wording, and §25 now records them as ruled rather than as a developer’s choice awaiting the office.',
    ],
    [
        'Whether every term must be re-determined as a matter of policy is the open question.',
        'Ruled 2026-10-24 by the owner: every term must be re-determined. That is the strictest reading and also the state the build already forces, so no code follows — the removed Assessment box stays removed, nothing copies a grant forward, and the Scholarship desk’s act is what uncovers the account.',
    ],
    [
        'What is queued is exactly those two listeners — PDF rendering has never been queued work, so §6’s browser requirement applies either way.',
        'What is queued is exactly those two listeners, and reading them on 2026-10-24 narrowed what a worker is ever for here: SendWorkflowNotification::notify() does nothing but Notifications::create() — plain inserts of the in-app bell rows for staff accounts, with no app/Notifications/ directory and no mail channel anywhere in the path. So a worker delivers the desk’s bell and nothing else; PDF rendering has never been queued work, so §6’s browser requirement applies either way. C-10 is answered for this install on 2026-10-24: it runs sync, and DEPLOYMENT.md §5 stands for whoever provisions a real server.',
    ],
];

$staged = $xml;
foreach ($edits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    // Edit 1 is the certificate sentence, which the document carries TWICE by design: §25.11 row 2303 and
    // §28.5 row 2592 state the same question, which is this register's own convention for a school
    // question. Both must move in the same pass or the document would contradict itself.
    $want = $i === 0 ? 2 : 1;
    $say($n === $want, 'edit '.($i + 1).' found exactly '.$want.' time(s) (count '.$n.')');
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($to, $ch), 'edit '.($i + 1).' replacement carries no '.$ch);
    }
    $staged = str_replace($from, $to, $staged);
}

$say(substr_count($staged, $edits[0][0]) === 0, 'no copy of the developer-wording claim survives in either register');
$say(substr_count($staged, 'The words were ruled on 2026-10-24') === 2, 'both §25.11 and §28.5 carry the ruling');
$say(substr_count($staged, 'is the open question.') === 0, 'C-7 no longer states itself as an open question');
$say(substr_count($staged, 'rather than SEAIT’s.') === 0, 'the certificate row no longer calls its wording the developer’s');
$say(substr_count($staged, 'Ruled 2026-10-24') + substr_count($staged, 'ruled on 2026-10-24') >= 2, 'both rulings carry their own date');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
$lenDelta = strlen($staged) - strlen($xml);
printf("\n  staged byte delta: %+d\n", $lenDelta);

printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: three rows would carry the 2026-10-24 rulings.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass186.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: the docx cannot be reopened for the write — nothing written.\n");
}
if ($z->getFromName('word/document.xml') !== $xml) {
    exit("REFUSED: document.xml changed under this pass since it was read — nothing written.\n");
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
