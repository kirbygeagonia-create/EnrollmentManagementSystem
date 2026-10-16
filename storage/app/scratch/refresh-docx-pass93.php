<?php

/**
 * Pass 93 — the ID desk walked in the running application, and one scripted row proved
 * unperformable.
 *
 * Five of the desk's rows were driven in a browser as an officer would drive them, with the
 * evidence read from the server and from decoded pixels rather than from the template. The sixth
 * — opening a request — could not be performed at all on the demo dataset, which is the kind of
 * finding a script review cannot make and a walk makes immediately.
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
    [
        'was deleted rather than made into a new state.',
        'was deleted rather than made into a new state. Walked in the running application on 2026-10-11 as the '
        .'ID office, from a logged-in session against the served build: a request with no face photo shows the '
        .'validate control disabled and prints the reason beneath it (’Capture or attach the face photo before '
        .'validating.’); an attached photo renders on the still-pending request, confirmed by reading the pixels '
        .'of the decoded preview rather than its src; re-capturing replaces the picture and removes the file it '
        .'displaced from the private disk, so the folder does not keep every blurred attempt; a text file named '
        .'.jpg is refused with the card ’That step did not go through — The photo field must be an image.’ and the '
        .'picture on file survives untouched; and a mismatch remark stores on the pending request without '
        .'closing it or signing anything. Everything the walk wrote was then restored to its pre-walk state '
        .'behind a dump. One scripted row could not be performed: opening a request needs an enrollment whose '
        .'next unsigned box is the ID office’s and which holds no request yet, and the demo dataset parks '
        .'exactly one enrollment at that stage — already carrying its request — so §18’s create step needs a '
        .'second record advanced to the ID box before an office can walk it.',
        1,
        'the ID desk walk, recorded at the end of the signature ruling',
    ],
];

$fails = 0;
$statements = [];

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-52s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'the walk is recorded once' => substr_count($xml, $esc('Walked in the running application on 2026-10-11')) === 1,
    'the unperformable row is named' => substr_count($xml, $esc('create step needs a second record advanced to the ID box')) === 1,
    'the following bullet survives' => substr_count($xml, $esc('No retention or purge.')) === 1,
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

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-11-pass93.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
