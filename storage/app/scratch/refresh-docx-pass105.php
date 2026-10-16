<?php

/**
 * Pass 105 — the two routes that need no login, and the four citations into Preflight.php that
 * the new rules moved.
 *
 * ems:preflight gained `Application key` and `Served folder` after lane 24 measured, through the
 * HTTP kernel, that `PUT /storage/{path}` is guarded by an HMAC under APP_KEY and nothing else: an
 * install that never ran `key:generate` accepts a write signed with the empty secret that
 * `.env.example` ships (204, file written), while this install refuses the same request (403).
 * Adding the rules shifted `checks()` and `uploadDiskIsPrivate()`, so all four spans were re-opened
 * and read rather than offset by arithmetic — a cite into a method that moved is a claim about a
 * line that is now somebody else's.
 *
 * The paragraph this edits is the one that lists what preflight answers, so the measured finding and
 * the corrected DEPLOYMENT.md §2 sentences are recorded where a reader of that list will meet them.
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

// ---- every cited line is read out of the file before the document is touched ----
$lines = explode("\n", file_get_contents($root.'/app/Console/Commands/Preflight.php'));
$expect = [
    83 => 'private function checks(): array',
    99 => '}',
    117 => 'private function applicationKeyIsReal(): array',
    132 => '}',
    147 => 'private function servedFolderStaysPrivate(): array',
    174 => '}',
    302 => 'private function uploadDiskIsPrivate(): array',
    325 => '}',
];
$badSourceLines = 0;
foreach ($expect as $n => $text) {
    if (trim($lines[$n - 1]) !== $text) {
        fwrite(STDERR, sprintf("REFUSE: Preflight.php line %d is [%s], expected [%s]\n",
            $n, trim($lines[$n - 1] ?? '<beyond end of file>'), $text));
        $badSourceLines++;

        continue;
    }
    printf("verified  Preflight.php:%d = %s\n", $n, trim($lines[$n - 1]));
}
if ($badSourceLines > 0) {
    fwrite(STDERR, "ABORT: {$badSourceLines} cited line(s) do not say what the document claims — nothing was written\n");
    exit(1);
}

$edits = [
    [
        'the privacy of the documents disk, the folders Laravel must write into, a browser for PDFs and the queue connection, each failure naming the fix and nothing at all being written (Preflight.php:82-96, the disk rule at :224-247).',
        'the privacy of the documents disk, whether the folder Laravel serves at /storage/{path} still refuses a request with no signature on it, the application key, the folders Laravel must write into, a browser for PDFs and the queue connection, each failure naming the fix and nothing at all being written (Preflight.php:83-99, the two new rules at :117-132 and :147-174, the disk rule at :302-325). The last two rows answer a question no login is needed to ask: because config/filesystems.php marks the local disk serve => true, Laravel registers GET and PUT /storage/{path} outside every middleware group, and the only thing standing in front of them is an HMAC of the path and query keyed by APP_KEY — a signature nothing in app/ or resources/ ever mints, so no desk uses either route. Driving the write through the HTTP kernel measured both edges of that gate: a signature computed with the empty secret that .env.example ships writes a file into storage/app/private and answers 204 on a server that never ran key:generate, while this install refuses the identical request with 403. Declaring visibility public on the served disk opens the read the same way — the unsigned GET that is refused today then walks through and only a missing file answers 404 — and that line matters because the served local disk and the documents disk are rooted at the same folder. DEPLOYMENT.md §2 said of that folder that no web path reaches it, and said too that uploads follow the default disk; both sentences are corrected here, and these two checks are what keep the corrected one true.',
        1,
        'the preflight inventory, its two new rows, the measured gate, and the four spans',
    ],
];

$fails = 0;
foreach ($edits as [$from, $to, $expect_count, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect_count) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect_count));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    printf("edit ok  %-56s  %dx\n", $label, $expect_count);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the old spans are gone' => substr_count($xml, $esc('Preflight.php:82-96')) === 0 && substr_count($xml, $esc('disk rule at :224-247')) === 0,
    'the new spans are in once' => substr_count($xml, $esc('(Preflight.php:83-99, the two new rules at :117-132 and :147-174, the disk rule at :302-325)')) === 1,
    'the measured verdict is present once' => substr_count($xml, $esc('never ran key:generate, while this install refuses the identical request with 403')) === 1,
    'the corrected deployment claim is named once' => substr_count($xml, $esc('that no web path reaches it, and said too that uploads follow the default disk')) === 1,
    'the enumeration still ends the same way' => substr_count($xml, $esc('each failure naming the fix and nothing at all being written')) === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

echo $fails === 0 ? "\nall edits and guards ok\n" : "\n{$fails} problem(s)\n";
if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass105.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
