<?php

/**
 * Pass 197 — §1.1's living revision row records passes 191, 192, 194, 195 and 196, none of which were written
 * down when they ran.
 *
 * The record is appended to the row that already carries this issue, because a new row would have to state a
 * date and a figure, and AGENTS.md's rule is that a dated row must not be re-aged. No new section pointer is
 * written: naming a register again moves the count the §-reference sweep prints, which is drift this repository
 * has paid for twice.
 *
 *   php storage/app/scratch/refresh-docx-pass197.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass197.php --apply  # write
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
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$from = 'and the citation agrees with the scholarship row, which had been citing that span correctly all along.';
$to = $from
    .' Passes 191 and 192 then gave thirteen bare numbers in the risk, shift and permission tables the file '
    .'they belong to, each bound by reading the sentence against the code it describes rather than by the '
    .'neighbour a checker would guess; two of the numbers were addressable only by position inside their row, '
    .'because the same figure is cited elsewhere in the document. Passes 194 and 195 rebuilt the data '
    .'dictionary’s Seeded? column — six cells re-taken one at a time, each quoting a line that is not the '
    .'create call its own text claims, plus one citation that had been written without its file extension and '
    .'two more that the introducing sentence, not the cell, owns. That column had never been checkable at '
    .'all: the file is named in the prose above the table, and a checker that needs the filename in the cell '
    .'reads no citation there. Pass 196 then drained the queue the meaning sweep had kept for the workflow '
    .'service. Every pointer into that file still carried the numbering from before the second-signature '
    .'refusal was added: the office-scope test was cited four times at the throw that refuses a repeat '
    .'signature, the step-order rule twice at two private constant maps, and the completion status at the '
    .'close of a docblock. One of those sentences quoted source code — the call the service uses to drop the '
    .'Assessment box for returning students — and that call left the file on 2026-10-01, when the skip became '
    .'a filter over the box list. No instrument could see it: the stale range lands on live lines of the same '
    .'file, the file is named, and the sweep only judges a pair when the prose names a method rather than '
    .'quoting an expression. The checker for that class is now the gloss checker — when a citation’s '
    .'parenthetical quotes a call, that call has to exist in the file the citation points at — and its '
    .'paraphrase and out-of-range verdicts are printed as a queue, not refused, because its precision has not '
    .'been measured.';

$n = substr_count($xml, $from);
$say($n === 1, 'the living revision row ends where this record goes (count '.$n.')');
foreach (['<', '>', '&'] as $ch) {
    $say(! str_contains($to, $ch), 'the replacement carries no raw '.$ch);
}
$say(preg_match('/[A-Za-z][A-Za-z0-9_.\-]*\.(?:php|jsx|js|md|json):\d/', $to) === 0,
    'the record writes no citation-shaped example that no file could answer');
foreach (['Passes 191 and 192', 'Passes 194 and 195', 'Pass 196 then'] as $claim) {
    $say(substr_count($xml, $claim) === 0, 'nothing in the document yet claims that '.$claim.' ran');
}
$staged = str_replace($from, $to, $xml);
$say(substr_count($staged, 'Passes 191 and 192') === 1, 'the pass-191 record appears once');
$say(substr_count($staged, 'Pass 196 then') === 1, 'the pass-196 record appears once');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  staged byte delta: %+d\n", strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: §1.1’s living row would record passes 191, 192, 194, 195 and 196.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass197.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
