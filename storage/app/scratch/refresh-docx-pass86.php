<?php

/**
 * Pass 86 — the owner's three remaining rulings from 2026-10-10 evening: a replaced face photo
 * is now removed, an operator can see what the upload folder holds with nothing pointing at it,
 * and the demo row that claimed a validation nobody performed is back to pending.
 *
 * Three ID process citations moved with the added lines. Each new endpoint is asserted against
 * the working file by the statement it must contain, not by arithmetic.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$edits = [
    [':135-155', ':135-165', 1, 'row 2 Photo = attachPhoto(), now with the removal'],
    [':178-197', ':188-207', 1, 'row 3 Validate = validate()'],
    ['IDController.php:209-220', 'IDController.php:219-230', 1, 'row 4 Remark = recordRemark()'],
];

$fails = 0;

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-46s %s → %s\n", $label, $from, $to);
}

$tail = 'row 3 pointed at lines that are not validate() on any recent build.';
$added = $tail
    .' Three further rulings closed the same evening. A face photo the desk replaces is now removed from disk '
    .'after the record points at the new one, because a school should not keep every blurred attempt in the '
    .'folder that holds its students’ pictures; the removal sits after the write and after the update, so a '
    .'rejected upload cannot destroy the one picture a request has, and a test proves that by posting a non-image '
    .'over a good capture. A new console command, ems:uploads:prune, answers what an operator otherwise cannot: '
    .'which files on the private disk no record points at — on this machine, 144 of 146, mostly suite residue '
    .'from before the tests faked the disk. It reports and touches nothing unless told --delete, and it looks '
    .'only inside the two upload folders, which is not a detail: its first version listed the disk’s .gitignore '
    .'— the file that keeps a folder of signed forms out of a public repository — as an orphan and offered to '
    .'delete it, so the scoping rule and the test that pins it exist because the command caught itself. '
    .'Finally, the demo row this document named as claiming a validation nobody performed (idrequests 53, '
    .'created 2026-09-13, '
    .'before the validation-only flow existed) is set back to pending behind a dump, and the rule it broke now '
    .'holds as a measured fact: no validated request on the install lacks a face photo. Its workflow box is '
    .'separately recorded as completed with no validator, which no desk action could produce either, and that is '
    .'left for the owner rather than quietly repaired, because un-signing a box rewrites an audit trail.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: append tail seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$xml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

$statements = [
    ['app/Http/Controllers/ID/IDController.php', 135, 'public function attachPhoto('],
    ['app/Http/Controllers/ID/IDController.php', 165, '}'],
    ['app/Http/Controllers/ID/IDController.php', 188, 'public function validate('],
    ['app/Http/Controllers/ID/IDController.php', 207, '}'],
    ['app/Http/Controllers/ID/IDController.php', 219, 'public function recordRemark('],
    ['app/Http/Controllers/ID/IDController.php', 230, '}'],
    ['app/Http/Controllers/ID/IDController.php', 160, 'filled($superseded)'],
];

foreach ($statements as [$rel, $lineNo, $needle]) {
    $work = explode("\n", (string) file_get_contents($root.'/'.$rel));
    if (! str_contains($work[$lineNo - 1] ?? '', $needle)) {
        fwrite(STDERR, sprintf("REFUSE: %s:%d holds [%s] not [%s]\n", $rel, $lineNo, trim($work[$lineNo - 1] ?? '<past end>'), $needle));
        $fails++;
    }
}

$guards = [
    'parses as XML' => (function () use ($xml) {
        libxml_use_internal_errors(true);
        libxml_clear_errors();
        $r = @simplexml_load_string($xml);
        libxml_clear_errors();

        return $r !== false;
    })(),
    'no table gained or lost' => substr_count($original, '<w:tbl>') === substr_count($xml, '<w:tbl>'),
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stale span survives' => substr_count($xml, $esc(':135-155')) === 0
        && substr_count($xml, $esc(':178-197')) === 0
        && substr_count($xml, $esc('IDController.php:209-220')) === 0,
    'the record is in once' => substr_count($xml, $esc('Three further rulings closed the same evening')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%d statements verified; %s\n", count($statements), $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass86.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
