<?php

/**
 * Pass 84 — what driving the real ID desk in a browser found that no test could see:
 * two defects in the photo panel, one defect still open, one folder of test litter, and
 * one demo row that claims a status its own rule cannot produce.
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

$tail = 'two of them had been wrong before this batch started, and row 3 pointed at lines '
    .'that are not validate() on any recent build.';

$added = $tail
    .' The ID photo flow was then driven in a real browser against the live database, which is '
    .'where the rest came from, and it found two things no test could have shown: the desk could '
    .'not see the photo it had just taken. The panel displayed the stored face only once the '
    .'request was no longer pending — so during the one moment the officer needs it, comparing the '
    .'person at the counter against the record before signing a terminal step, the screen offered '
    .'a camera and two buttons and no face at all. It now shows the photo on file above the '
    .'capture controls while the request is pending, and the controls stay, so a retake is still '
    .'one click (ID/Show.jsx:296-309). And replacing a photo left the previous face on the screen: '
    .'the view route’s URL never changes, so React kept the image it had already decoded and the '
    .'desk went on looking at the picture it had just replaced while the record pointed at a new '
    .'one — a counter in the URL (ID/Show.jsx:35-39, bumped at :108) is what makes the screen show '
    .'what the record carries. Both were measured rather than read: the panel held a 200×260 photo, '
    .'a 120×160 file was attached, and the same screen then reported 120×160 without a reload. The '
    .'probe also confirmed the new error surface on a real desk — a rejected file answered with an '
    .'alert card reading “That step did not go through / The photo field must be an image”, where '
    .'this screen used to answer with silence. One defect is open and stated as open: a successful '
    .'attach shows no confirmation card, while a plain action on the very same screen does show '
    .'“Completed / Mismatch recorded on the ID request.” The flash reaches the desk for a JSON post '
    .'and not for the multipart one; the photo itself stores and displays correctly, so this is a '
    .'missing confirmation rather than a failed save, and it reproduced before the preview existed, '
    .'so it did not arrive with this batch. Two things the probe surfaced in the data rather than '
    .'the code: IDControllerTest had been posting real images without faking the disk, so 131 of the '
    .'133 files in storage/app/private/id-photos were unreferenced test residue sitting in the same '
    .'folder as the school’s real student photographs — the suite fakes that disk now, and the old '
    .'files are left for the owner to clear rather than deleted here; and idrequests row 53 reads '
    .'status validated with no face photo and no validator recorded, a state IDPolicy::validate '
    .'would refuse for any desk action, so the demo dataset carries one record its own rule cannot '
    .'produce.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: tail needle seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$xml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

$statements = [
    ['resources/js/Pages/ID/Show.jsx', 296, '{photoAttached && ('],
    ['resources/js/Pages/ID/Show.jsx', 309, ')}'],
    ['resources/js/Pages/ID/Show.jsx', 35, 'const photoSrc = () => {'],
    ['resources/js/Pages/ID/Show.jsx', 39, '};'],
    ['resources/js/Pages/ID/Show.jsx', 108, 'setPhotoKey((k) => k + 1);'],
];

$fails = 0;
foreach ($statements as [$rel, $lineNo, $needle]) {
    $work = explode("\n", (string) file_get_contents($root.'/'.$rel));
    if (! str_contains($work[$lineNo - 1] ?? '', $needle)) {
        fwrite(STDERR, sprintf("REFUSE: %s:%d holds [%s], not [%s]\n", $rel, $lineNo, trim($work[$lineNo - 1] ?? '<past end>'), $needle));
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
    'pass 84 phrase once' => substr_count($xml, $esc('The ID photo flow was then driven in a real browser')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("%d statement(s) verified; %s\n", count($statements), $fails === 0 ? 'all guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass84.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
