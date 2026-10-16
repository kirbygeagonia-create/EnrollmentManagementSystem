<?php

/**
 * Pass 85 — withdraw a claim pass 84 made, and replace it with what was measured.
 *
 * Pass 84 asserted that a successful photo attach never confirms itself and that the flash
 * reached the desk for a JSON post but not a multipart one. Both halves are false. The whole
 * sentence is one contiguous run, so it is rewritten in place rather than annotated.
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

$from = 'One defect is open and stated as open: a successful attach shows no confirmation card, '
    .'while a plain action on the very same screen does show “Completed / Mismatch recorded on the '
    .'ID request.” The flash reaches the desk for a JSON post and not for the multipart one; the '
    .'photo itself stores and displays correctly, so this is a missing confirmation rather than a '
    .'failed save, and it reproduced before the preview existed, so it did not arrive with this batch.';

$to = 'One defect pass 84 reported here was wrong in both of its halves and is withdrawn rather '
    .'than left standing, because the document is the authority and the sentence below it describes '
    .'a build that does not exist. What was written: a successful attach never confirms itself, and '
    .'the flash reaches the desk for a JSON post but not a multipart one. Measured instead: the '
    .'follow-up request carries the message in its page props for either kind of post, and the card '
    .'does render. The real defect was narrower, and it was written the session before — the toast '
    .'queue remembered every message it had shown by its wording, so the first attach on a page was '
    .'confirmed and every later attach on that same page was silent. Retaking a blurred photo is '
    .'exactly that case, so it was the desk’s own repeat action losing its answer (Toast.jsx:134-143 '
    .'deduplicates by visit identity now, and :164-168 does the same for page errors). The fix was '
    .'proved by watching the interval rather than sampling it: a MutationObserver armed before the '
    .'third attach on one page instance recorded the card, role=status, reading “Completed / Face '
    .'photo attached to the ID request.” Why the first probes said never is worth keeping beside the '
    .'correction — each browser round trip costs around twenty seconds of page time, so a card that '
    .'lives 4.5 seconds is easy to sample entirely outside, and a transient thing needs an instrument '
    .'that watches it, not a question asked afterwards.';

$count = substr_count($xml, $esc($from));
if ($count !== 1) {
    fwrite(STDERR, "REFUSE: the claim to withdraw was found {$count}x, expected 1x\n");
    exit(1);
}

$xml = str_replace($esc($from), $esc($to), $xml);
echo "edit ok  the false claim replaced in place\n";

$statements = [
    ['resources/js/Components/ui/Toast.jsx', 134, 'const lastFlash = useRef(null);'],
    ['resources/js/Components/ui/Toast.jsx', 143, 'lastFlash.current = flash;'],
    ['resources/js/Components/ui/Toast.jsx', 164, 'errors === lastErrors.current'],
    ['resources/js/Components/ui/Toast.jsx', 168, 'lastErrors.current = errors;'],
];

$fails = 0;
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
    'the withdrawn claim is gone' => substr_count($xml, $esc('The flash reaches the desk for a JSON post')) === 0,
    'the correction is in once' => substr_count($xml, $esc('was wrong in both of its halves and is withdrawn')) === 1,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    }
}

printf("%d citation statement(s) verified; %s\n", count($statements), $fails === 0 ? 'all guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass85.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
