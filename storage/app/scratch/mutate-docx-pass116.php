<?php

/**
 * Mutation-proof for refresh-docx-pass116.php.
 *
 * A guard that cannot fail is not a guard, and pass 116 rewrites a sentence inside the authority docx — the
 * one place a bad write is expensive to undo. Each mutant is a deliberately broken input (a document whose
 * anchor is gone, a Preflight copy whose cited range holds something else, a replacement string that smuggles
 * markup) fed through the *real* pass script, retargeted only to a temp copy of the document. Every mutant
 * must be refused, and refused by the guard meant to catch it; the unmutated control must still pass.
 * A survivor exits non-zero.
 *
 * usage: php storage/app/scratch/mutate-docx-pass116.php
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tmp = $root.'/storage/app/scratch/mut116';
@mkdir($tmp, 0777, true);

$docxReal = $root.'/Documentation/EMS Complete Documentation.docx';
$pass = file_get_contents($root.'/storage/app/scratch/refresh-docx-pass116.php');
$preflightReal = file_get_contents($root.'/app/Console/Commands/Preflight.php');

$zip = new ZipArchive;
$zip->open($docxReal) === true || exit(1);
$docxXml = $zip->getFromName('word/document.xml');
$zip->close();

$anchor = 'if (substr_count($xml, $old) !== 1) {';
if (substr_count($pass, $anchor) !== 1) {
    exit("REFUSE: the pass script no longer carries its pre-check anchor exactly once\n");
}
if (substr_count($pass, "\$src = \$root.'/Documentation/EMS Complete Documentation.docx';") !== 1) {
    exit("REFUSE: the pass script's document path moved; retargeting would silently test nothing\n");
}

/** Copy the real docx, swap in $xml, return the path. */
$mutantDocx = function (string $name, string $xml) use ($tmp, $docxReal): string {
    $path = $tmp.'/'.$name.'.docx';
    copy($docxReal, $path);
    $z = new ZipArchive;
    $z->open($path) === true || exit(1);
    $z->addFromString('word/document.xml', $xml);
    $z->close();

    return $path;
};

/**
 * Build a runnable mutant of the pass script: fixed root, its own docx, optionally a different Preflight
 * copy and an overridden replacement string. Returns [path to script, exit code, output].
 */
$run = function (string $name, string $docx, ?string $preflight, ?string $newOverride) use (
    $tmp, $php, $pass, $root, $anchor
): array {
    $code = str_replace(
        ['$root = dirname(__DIR__, 3);', "\$src = \$root.'/Documentation/EMS Complete Documentation.docx';"],
        ['$root = '.var_export($root, true).';', '$src = '.var_export($docx, true).';'],
        $pass
    );
    if ($preflight !== null) {
        $pf = $tmp.'/'.$name.'-Preflight.php';
        file_put_contents($pf, $preflight);
        $code = str_replace("\$L = file(\$root.'/app/Console/Commands/Preflight.php');",
            '$L = file('.var_export($pf, true).');', $code);
        if (! str_contains($code, $name.'-Preflight.php')) {
            exit("REFUSE: could not point the mutant at a mutated Preflight copy for {$name}\n");
        }
    }
    if ($newOverride !== null) {
        $code = str_replace($anchor, '$new = '.$newOverride.";".$anchor, $code);
        if (! str_contains($code, '$new = '.$newOverride.';')) {
            exit("REFUSE: could not override the replacement string for a mutant\n");
        }
    }
    $script = $tmp.'/'.$name.'.php';
    // A mutant that quietly kept the real document would be a mutant that tests nothing (and, with --apply,
    // a mutant that writes to the authority file), so insist the retarget landed. The forward-slash tail of
    // the path survives var_export's backslash escaping, so match on that.
    if (! str_contains($code, '/mut116/'.$name.'.docx')) {
        exit("REFUSE: the mutant did not get retargeted to its own copy of the document ({$name})\n");
    }
    file_put_contents($script, $code);
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($script).' 2>&1', $out, $rc);

    return [$rc, implode("\n", $out)];
};

$mutants = [
    'anchor-absent' => [
        str_replace('the disk rule at :302-325)', 'the disk rule elsewhere)', $docxXml),
        null, null, 'not where this pass expects',
    ],
    'range-not-the-disk-rule' => [
        $docxXml,
        str_replace('private function uploadDiskIsPrivate(): array', 'private function somethingElse(): array', $preflightReal),
        null, 'REFUSE guard: the new range is inside the disk rule',
    ],
    'range-past-end-of-file' => [
        $docxXml,
        implode("\n", array_slice(explode("\n", $preflightReal), 0, 300)),
        null, 'REFUSE guard: the new range does not run past the file',
    ],
    'stale-range-kept' => [
        $docxXml, null, "'the disk rule at :302-325; and the note)'", 'REFUSE guard: the stale range is gone',
    ],
    'note-sentence-absent' => [
        $docxXml, null, "'the disk rule at :344-367)'", 'REFUSE guard: the note sentence landed once',
    ],
    'backtick-smuggled' => [
        $docxXml, null, "'the disk rule at `:344-367` with a stray backtick)'", 'REFUSE guard: no markdown backticks crept in',
    ],
    'paragraph-smuggled' => [
        $docxXml, null, "'the disk rule at :344-367</w:p><w:p>)'", 'REFUSE guard: no paragraph gained or lost',
    ],
    'run-smuggled' => [
        $docxXml, null, "'the disk rule at :344-367<w:r><w:t>x)'", 'REFUSE guard: no run gained or lost',
    ],
    'earlier-citation-disturbed' => [
        $docxXml, null, "'the disk rule at :344-367, and Preflight.php:83-99 again)'",
        'REFUSE guard: the three earlier citations were left alone',
    ],
];

$fails = 0;
foreach ($mutants as $name => [$xml, $preflight, $override, $expected]) {
    $docx = $mutantDocx($name, $xml);
    [$rc, $joined] = $run($name, $docx, $preflight, $override);
    if ($rc !== 0 && str_contains($joined, $expected)) {
        printf("caught    %-28s exit %d — %s\n", $name, $rc, $expected);
    } else {
        $fails++;
        printf("SURVIVED  %-27s exit=%d expected=%s\n%s\n", $name, $rc, $expected,
            str_replace("\n", "\n          ", substr($joined, 0, 800)));
    }
}

// Control: the same harness on an untouched document must still report a clean dry run, or every "caught"
// above only proves the harness breaks things rather than that the guards do.
$docx = $mutantDocx('control', $docxXml);
[$rc, $joined] = $run('control', $docx, null, null);
if ($rc === 0 && str_contains($joined, 'DRY RUN')) {
    echo "control     clean — dry run, exit 0\n";
} else {
    $fails++;
    echo "CONTROL WRONG: the harness refused a good document, so no verdict above means anything\n"
        .str_replace("\n", "\n  ", $joined)."\n";
}

printf("\n%s\n", $fails === 0
    ? 'ALL MUTANTS CAUGHT — pass 116 guards are live'
    : $fails." mutant(s) survived — do not apply");

exit($fails === 0 ? 0 : 1);
