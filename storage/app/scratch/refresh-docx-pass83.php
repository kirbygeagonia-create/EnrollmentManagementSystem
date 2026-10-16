<?php

/**
 * Pass 83 — the four rulings of 2026-10-10 afternoon, and the ID table's citations, two
 * of which were stale before this batch ever touched the file.
 *
 * The table cited method spans that had drifted (row 3 pointed at lines that are not
 * validate() on any recent build). Every endpoint rewritten here is asserted against the
 * working file by the text it must contain, so a number that does not sit where the
 * sentence says it sits refuses the pass.
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
    // ---- the ID process table: span = signature .. closing brace ----
    ['IDController.php:102-128', 'IDController.php:104-130', 1, 'row 1 Request = create()'],
    [':131-148', ':135-155', 1, 'row 2 Photo = attachPhoto()'],
    [':151-172', ':178-197', 1, 'row 3 Validate = validate()'],
    ['IDController.php:208-219', 'IDController.php:209-220', 1, 'row 4 Remark = recordRemark()'],

    // ---- pass 80 prose, which described the build before the rulings ----
    [
        'silent in the direction that matters: the default disk is configured throw => false',
        'silent in the direction that matters: the disk the uploads land on is configured throw => false',
        1,
        'the disk is named now, not defaulted',
    ],
    [
        'Two findings came out of it and neither was quietly fixed. The first is left standing, for a ruling: '
        .'idrequests.cardPhotoPath is still accepted as free text on the create route (IDController.php:113, '
        .'written at :122) although no form sends it and any non-empty string satisfies the photo rule, so '
        .'retiring it is a ruling rather than a fix; and uploads follow',
        'Two findings came out of it, both put to the owner and both now closed. The first: the create route '
        .'accepted cardPhotoPath as free text although no form sends it, and any non-empty string satisfied the '
        .'photo rule, so the input is gone — a request carries a face only after attachPhoto() stored one and '
        .'checked the write (IDController.php:104-130, and the test that pinned the old behaviour now asserts '
        .'the field is ignored). The second: uploads followed',
        1,
        'the cardPhotoPath ruling',
    ],
    [
        'That one is closed by order rather than by code: DEPLOYMENT.md §2 and .env.example now both state '
        .'that the default disk stays private, and an explicit named disk remains available if a deployment '
        .'ever needs public file serving for something else.',
        'That one is closed by code and by order: config/filesystems.php now defines a named documents disk '
        .'rooted where the files already are, with no serve flag and no url, the five sites that write or read '
        .'uploads name it, and DEPLOYMENT.md §2 and .env.example say the same — so no value of FILESYSTEM_DISK '
        .'can publish them, and a deployment that ever needs public file serving gets its own disk instead of '
        .'moving the uploads.',
        1,
        'the documents-disk ruling',
    ],
];

$fails = 0;

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx — %s\n", $label, $count, $expect, substr($from, 0, 80)));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-34s %s\n", $label, substr($from, 0, 40));
}

// ---- the record of the four rulings, appended to the same paragraph ----

$tail = 'and one was wrong by meaning rather than by arithmetic: the row that verifies or rejects a '
    .'requirement cited the submit method’s span and now cites verifyRequirement’s own.';

$added = $tail
    .' Four findings were then put to the owner and ruled the same afternoon, and each is in this build. '
    .'The create route no longer reads a photo path. A rejected save that no form displays is answered at '
    .'the one surface every desk already shares: the toast queue reads page-level validation errors as an '
    .'error card, held until dismissed and announced, because twenty-some desk actions post without a form '
    .'object and a person who sees only a button stop spinning has no way to know the record did not save — '
    .'a form that lists its own field errors now shows both, which is deliberate, the inline list is where '
    .'to fix it and the card is the notice that anything happened. The unused branded error dialog that has '
    .'been in the component kit since it was written, mounted by no page and painted in raw red, amber and '
    .'slate instead of the kit’s own tones, is retired rather than wired up: the toast and the boundary '
    .'already answer for that surface, and a dialog that blocks a desk is the wrong shape for a message '
    .'that only has to be read. And uploads are on a named private disk, so the one environment line that '
    .'could have published an applicant’s papers now cannot reach them at all. The ID process table’s four '
    .'method spans were re-read against the file; two of them had been wrong before this batch started, and '
    .'row 3 pointed at lines that are not validate() on any recent build.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: append tail seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$xml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

// ---- assert every new endpoint against the working file ----

$statements = [
    ['app/Http/Controllers/ID/IDController.php', 104, 'public function create('],
    ['app/Http/Controllers/ID/IDController.php', 130, '}'],
    ['app/Http/Controllers/ID/IDController.php', 135, 'public function attachPhoto('],
    ['app/Http/Controllers/ID/IDController.php', 155, '}'],
    ['app/Http/Controllers/ID/IDController.php', 178, 'public function validate('],
    ['app/Http/Controllers/ID/IDController.php', 197, '}'],
    ['app/Http/Controllers/ID/IDController.php', 209, 'public function recordRemark('],
    ['app/Http/Controllers/ID/IDController.php', 220, '}'],
    ['config/filesystems.php', 58, "'documents' => ["],
];

foreach ($statements as [$rel, $lineNo, $needle]) {
    $work = explode("\n", (string) file_get_contents($root.'/'.$rel));
    if (! str_contains($work[$lineNo - 1] ?? '', $needle)) {
        fwrite(STDERR, sprintf(
            "REFUSE: %s:%d holds [%s], not [%s]\n",
            $rel,
            $lineNo,
            trim($work[$lineNo - 1] ?? '<past end>'),
            $needle
        ));
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
    'every stale span is gone' => substr_count($xml, $esc('IDController.php:102-128')) === 0
        && substr_count($xml, $esc(':131-148')) === 0
        && substr_count($xml, $esc(':151-172')) === 0
        && substr_count($xml, $esc('IDController.php:208-219')) === 0
        && substr_count($xml, $esc('IDController.php:113')) === 0,
    'the ruling sentence is in once' => substr_count($xml, $esc('Four findings were then put to the owner')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("\n%s\n", $fails === 0 ? 'all edits, statements and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass83.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
