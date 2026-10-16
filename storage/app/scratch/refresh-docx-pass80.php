<?php

/**
 * Pass 80 — the uploaded-file lane: one silent failure closed, six citations shifted,
 * one of them wrong by meaning rather than by arithmetic, and two findings reported
 * rather than acted on.
 *
 * Order matters here. Every citation edit runs first, the prose append last, so no
 * needle count is disturbed by text this same script adds.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

/** @var array<int, array{0:string,1:string,2:int,3:string}> $edits */
$edits = [
    // Six spans that move with the six lines the guard added inside submitRequirement
    // (after old line 305) and attachPhoto (after old line 143).
    ['AdmissionController.php:364-410', 'AdmissionController.php:370-416', 3, 'approval create block, cited three times'],
    ['the seat guard at :364, the create at :377', 'the seat guard at :370, the create at :383', 1, 'short cites inside that sentence'],
    ['AdmissionController.php:377-410', 'AdmissionController.php:383-416', 1, 'the create site in the second register'],
    ['AdmissionController.php:377 and app/Services/EnrollmentIssuer.php:73', 'AdmissionController.php:383 and app/Services/EnrollmentIssuer.php:73', 1, 'the two Enrollments::create sites'],
    ['AdmissionController.php:390', 'AdmissionController.php:396', 1, 'derivedYearLevel stamp'],
    ['AdmissionController.php:395', 'AdmissionController.php:401', 1, 'enrollmentType new'],
    ['IDController.php:202-213', 'IDController.php:208-219', 1, 'recordMismatch span'],
    // Wrong by meaning: that row describes verify or reject, but cited submitRequirement.
    ['AdmissionController.php:287-305', 'AdmissionController.php:333-349', 1, 'verify row repointed at verifyRequirement'],
];

$fails = 0;

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: needle seen %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-46s %dx  %s → %s\n", $label, $expect, $from, $to);
}

$tail = 'rather than a reproduced bug.';
$added = $tail
    .' The uploaded-file lane then found the one silent failure left in the system, and it was '
    .'silent in the direction that matters: the default disk is configured throw => false, so a '
    .'write the filesystem refuses does not raise — store() hands back false, and both upload '
    .'routes carried that false into the record. On the admission desk a documents row was written '
    .'with no file behind it, the requirement was marked submitted, and the toast said it had '
    .'worked; nothing downstream re-checks the paper, because verifying a requirement is a '
    .'judgement about the submission row and not about the disk. On the ID desk the path column '
    .'came back as the string 0, and the two halves of one screen then disagreed: PHP reads 0 as '
    .'empty, so IDPolicy::validate correctly refused to sign a request with no face photo, while '
    .'the desk’s own photo-attached flag reads the same value in JavaScript, where 0 is truthy — so '
    .'the screen said a photo was on file and the policy said there was none. Both routes now '
    .'refuse the step, write nothing, and name what to do next. Five tests cover it, each failure '
    .'test paired with a control that uploads into the same folder through an ordinary disk — '
    .'without the control a green run would only prove every upload is broken. The refusing disk is '
    .'a real local adapter raising the same UnableToWriteFile a full volume raises, so the '
    .'framework’s own conversion to false runs on the way to the guard. Building that fixture '
    .'corrected an assumption worth keeping: a failure at mkdir — a directory that must be created '
    .'and cannot be — raises UnableToCreateDirectory, which is not in Laravel’s catch list, so it '
    .'escapes and lands on the styled 500 page. Only the refused write is silent, which is why the '
    .'guard tests the return value and not the exception. Two findings are reported and not acted '
    .'on. idrequests.cardPhotoPath is still accepted as free text on the create route '
    .'(IDController.php:113, written at :122) although no form sends it and any non-empty string '
    .'satisfies the photo rule, so retiring it is a ruling rather than a fix; and uploads follow '
    .'FILESYSTEM_DISK, so one line in .env plus storage:link would serve applicant documents and '
    .'student face photos by URL past both authorize() calls, which the deployment notes do not '
    .'currently mention. Six line citations here moved with the added guard, and one was wrong by '
    .'meaning rather than by arithmetic: the row that verifies or rejects a requirement cited the '
    .'submit method’s span and now cites verifyRequirement’s own.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: tail needle seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$newXml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

$parseOk = (function () use ($newXml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($newXml);
    libxml_clear_errors();

    return $r !== false;
})();

$containersSame = substr_count($xml, '<w:tbl>') === substr_count($newXml, '<w:tbl>')
    && substr_count($xml, '</w:p>') === substr_count($newXml, '</w:p>');

$guards = [
    'parses' => $parseOk,
    'containers unchanged' => $containersSame,
    'no stale :364-410' => substr_count($newXml, $esc('AdmissionController.php:364-410')) === 0,
    'no stale :287-305' => substr_count($newXml, $esc('AdmissionController.php:287-305')) === 0,
    'no stale IDController.php:202-213' => substr_count($newXml, $esc('IDController.php:202-213')) === 0,
    ':370-416 now three times' => substr_count($newXml, $esc('AdmissionController.php:370-416')) === 3,
    ':333-349 once' => substr_count($newXml, $esc('AdmissionController.php:333-349')) === 1,
    'no ns prefixes' => substr_count($newXml, 'ns1:') === 0 && substr_count($newXml, 'ns5:') === 0,
    'pass 80 phrase once' => substr_count($newXml, $esc('The uploaded-file lane then found')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%s\n", $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass80.docx');

$out = new ZipArchive;
if ($out->open($src) !== true) {
    fwrite(STDERR, "cannot reopen for write\n");
    exit(1);
}
$out->addFromString('word/document.xml', $newXml);
$out->close();

printf("WROTE %s  (%+d bytes in document.xml)\n", $src, strlen($newXml) - strlen($xml));
