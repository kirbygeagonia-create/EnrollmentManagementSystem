<?php

/**
 * Mutation-proof for refresh-docx-pass117.php — run BEFORE pass 117 writes to the authority docx.
 *
 * Pass 117 rewrites five citation ranges inside `Documentation/EMS Complete Documentation.docx`. The guards
 * that decide whether the write happens are only worth what a mutant can break them for, so each mutant below
 * is a deliberately wrong pass — an anchor that is not in the document, a new range that does not hold the
 * construct it now claims, an edit that moves nothing, replacements that smuggle markup or a backtick, and one
 * that would silently undo the citation pass 116 wrote hours earlier. Every mutant must be refused, and by
 * the guard meant to catch it; the unmutated control must still report a clean dry run, or "caught" only
 * proves the harness breaks things.
 *
 * Nothing here touches the real docx: the mutants run in DRY RUN against it, and the pass script's only write
 * path is behind --apply, which this harness never passes.
 */

$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$tmp = $root.'/storage/app/scratch/mut-p117';
@mkdir($tmp, 0777, true);

$pass = file_get_contents($root.'/storage/app/scratch/refresh-docx-pass117.php');
str_contains($pass, "\$apply = in_array('--apply', \$argv, true);") || exit("REFUSE: pass 117 no longer guards --apply\n");

$mutants = [
    // name => [str_replace pairs applied to the pass source, expected REFUSE text]
    'anchor-not-in-document' => [
        [["'AdmissionController.php:121', 'AdmissionController.php:125',", "'AdmissionController.php:999', 'AdmissionController.php:125',"]],
        'appears 0 time(s), expected exactly 1',
    ],
    'new-range-does-not-hold-the-construct' => [
        [["'app/Http/Controllers/Admission/AdmissionController.php', 125, 125,", "'app/Http/Controllers/Admission/AdmissionController.php', 118, 118,"]],
        'does not contain',
    ],
    'edit-moves-nothing' => [
        [["['Rule::enum(ApplicantType::class)'], 121],", "['Rule::enum(ApplicantType::class)'], 125],"]],
        'already holds',
    ],
    'markup-smuggled' => [
        [["':289-305</w:t>',", "':289-305<w:r></w:r></w:t>',"]],
        'REFUSE guard: no run gained or lost',
    ],
    'backtick-smuggled' => [
        [["'recordIssue() at :115',", "'`recordIssue()` at :115',"]],
        'REFUSE guard: no markdown backticks crept in',
    ],
    'would-undo-pass-116' => [
        [["    [':150-174</w:t>', ':289-305</w:t>',", "    ['the disk rule at :344-367', 'the disk rule at :302-325',\n        'app/Console/Commands/Preflight.php', 344, 367, ['uploadDiskIsPrivate'], 1],\n    [':150-174</w:t>', ':289-305</w:t>',"]],
        'REFUSE guard: the disk rule citation is still :344-367',
    ],
    'range-past-end-of-file' => [
        [["'app/Services/PrintService.php', 254, 254,", "'app/Services/PrintService.php', 9999, 9999,"]],
        'REFUSE: app/Services/PrintService.php has',
    ],
];

$fails = 0;

foreach ($mutants as $name => [$pairs, $expected]) {
    $code = $pass;
    foreach ($pairs as [$from, $to]) {
        if (substr_count($code, $from) !== 1) {
            printf("SURVIVED  %-38s harness anchor missed: %s\n", $name, substr($from, 0, 60));
            $fails++;
            continue 2;
        }
        $code = str_replace($from, $to, $code);
    }
    // The pass script works out the repository root from its OWN location, so a copy placed deeper than
    // storage/app/scratch looks for the docx in the wrong place — which made every mutant in the first run of
    // this harness "survive" while the harness itself was the thing that was broken. Pin the root, then insist
    // the pin landed.
    $code = str_replace("\$root = dirname(__DIR__, 3);", '$root = '.var_export($root, true).';', $code);
    if (str_contains($code, 'dirname(__DIR__, 3)')) {
        exit("REFUSE: could not pin \$root in the mutant {$name}; it would read the docx from the wrong folder\n");
    }
    $script = $tmp.'/'.$name.'.php';
    file_put_contents($script, $code);
    $out = [];
    $rc = 0;
    exec(escapeshellarg($php).' '.escapeshellarg($script).' 2>&1', $out, $rc);
    $joined = implode("\n", $out);
    if ($rc !== 0 && str_contains($joined, $expected)) {
        printf("caught    %-38s — %s\n", $name, $expected);
    } else {
        $fails++;
        printf("SURVIVED  %-38s exit=%d expected=%s\n%s\n", $name, $rc, $expected, str_replace("\n", "\n          ", substr($joined, 0, 500)));
    }
}

// Control: the pass as written must still be a clean dry run.
$ctl = $tmp.'/control.php';
$ctlCode = str_replace("\$root = dirname(__DIR__, 3);", '$root = '.var_export($root, true).';', $pass);
$ctlCode = str_replace("\$apply = in_array('--apply', \$argv, true);", '$apply = false;', $ctlCode);
if (! str_contains($ctlCode, '$apply = false;') || str_contains($ctlCode, 'dirname(__DIR__, 3)')) {
    exit("REFUSE: the control copy was not rewritten as expected\n");
}
file_put_contents($ctl, $ctlCode);
$out = [];
$rc = 0;
exec(escapeshellarg($php).' '.escapeshellarg($ctl).' 2>&1', $out, $rc);
$joined = implode("\n", $out);
if ($rc === 0 && str_contains($joined, 'DRY RUN') && substr_count($joined, 'guard ok') === 10) {
    echo "control     clean — 10 guards ok, dry run, exit 0\n";
} else {
    $fails++;
    echo "CONTROL WRONG: the untouched pass did not report a clean dry run\n".str_replace("\n", "\n  ", $joined)."\n";
}

printf("\n%s\n", $fails === 0
    ? 'ALL MUTANTS CAUGHT — pass 117 guards are live'
    : $fails.' mutant(s) survived — do not apply pass 117');
exit($fails === 0 ? 0 : 1);
