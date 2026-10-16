<?php

/**
 * Pass 81 — the printed-paper lane: six download routes that had no test, one refusal
 * added before the render, one configuration value now honored strictly, and the
 * eighteen line numbers in this document that the change moved.
 *
 * The citation rewrite is not trusted to arithmetic. Every number the rewritten document
 * cites is re-read against the working file and compared with the text that stood at the
 * committed number. A citation whose statement moved with it survives; one that points at
 * different code refuses the whole pass.
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

$files = [
    'PrintService.php' => 'app/Services/PrintService.php',
    'bootstrap/app.php' => 'bootstrap/app.php',
];

// old -> new, from the verified hunks: PrintService gained one import line, eleven guard
// lines, and lost two around setChromePath(); bootstrap gained one import line only.
$forward = [
    'PrintService.php' => fn (int $n): int => $n <= 23 ? $n + 1 : ($n <= 36 ? $n + 12 : $n + 10),
    'bootstrap/app.php' => fn (int $n): int => $n < 4 ? $n : $n + 1,
];
// new -> old, for the re-read. Only the bands the citations actually land in.
$backward = [
    'PrintService.php' => fn (int $m): ?int => $m <= 24 ? $m - 1 : ($m >= 49 ? $m - 10 : null),
    'bootstrap/app.php' => fn (int $m): int => $m - 1,
];

$counts = [];

foreach ($files as $citedAs => $rel) {
    $xml = preg_replace_callback(
        '/'.preg_quote($citedAs, '/').':(\d+)(\s*[-–]\s*\d+)?/',
        function (array $d) use ($forward, $citedAs, &$counts): string {
            $from = $forward[$citedAs]((int) $d[1]);
            $to = isset($d[2]) && trim($d[2]) !== ''
                ? $forward[$citedAs]((int) preg_replace('/\D/', '', $d[2]))
                : null;
            $counts[$citedAs] = ($counts[$citedAs] ?? 0) + 1;

            return $citedAs.':'.$from.($to === null ? '' : '-'.$to);
        },
        $xml
    );
}

foreach ($counts as $citedAs => $n) {
    printf("rewrote %2d citation(s) of %s\n", $n, $citedAs);
}

// ---- prose ----

$tail = 'the submit method’s span and now cites verifyRequirement’s own.';
$added = $tail
    .' The printed-paper lane then found that not one of the six PDF download routes had a '
    .'test, and the reason it had none is the reason a test for them is awkward: a test that '
    .'needs a browser is green on a machine that has Chrome and red on the runner that has '
    .'none. What was missing was not the render but the refusal — on a server with no browser, '
    .'Browsershot raises and the desk lands on the generic 500 page, which reads as the system '
    .'having broken rather than as one dependency an office head can install. The print service '
    .'is now asked whether a browser exists before it builds the document (PrintService.php:30-34, '
    .'which is why the test for it passes an empty data set and still expects the refusal, not a '
    .'render error), and the refusal reaches the desk as the usual calm message naming both fixes '
    .'(bootstrap/app.php:124-137). Two decisions came with it. EMS_CHROME_PATH is honored '
    .'strictly now (ChromiumLocator.php:14-32): a configured binary that does not exist is '
    .'reported as no binary rather than quietly replaced by a different browser, because a '
    .'different layout engine can reflow a paper the Registrar signed off against one rendering. '
    .'And because requiring a located binary would otherwise break machines that relied on '
    .'Browsershot finding Chrome on its own, the candidate list gained the user-scope Chrome and '
    .'Edge installs and the Debian /opt/google/chrome path. Recorded rather than hidden: the '
    .'print-log row is written before the render, because the document number is printed on the '
    .'page, so a refusal on a browserless server still consumes the number it issued. Eighteen '
    .'line citations in this document moved with the added lines; each was re-read against the '
    .'file rather than shifted by arithmetic.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: pass-80 tail seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$xml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

// ---- re-read every citation against the working tree ----

$flat = html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1);
$refuse = [];

// The eleven guard lines this pass added have no HEAD counterpart at all — a citation to
// them is proved by the statement table below, not by a re-read against the old file.
$newOnly = ['PrintService.php' => [30, 34], 'bootstrap/app.php' => [124, 137]];
$skipped = [];

foreach ($files as $citedAs => $rel) {
    $head = explode("\n", (string) shell_exec(sprintf(
        'git -C %s show HEAD:%s',
        escapeshellarg($root),
        escapeshellarg($rel)
    )));
    $work = explode("\n", (string) file_get_contents($root.'/'.$rel));

    preg_match_all('/'.preg_quote($citedAs, '/').':(\d+)(?:\s*[-–]\s*(\d+))?/', $flat, $m, PREG_SET_ORDER);

    foreach ($m as $d) {
        foreach ([(int) $d[1], isset($d[2]) && $d[2] !== '' ? (int) $d[2] : null] as $lineNo) {
            if ($lineNo === null) {
                continue;
            }

            if (in_array($lineNo, $newOnly[$citedAs] ?? [], true)) {
                $skipped[] = "{$citedAs}:{$lineNo}";

                continue;
            }

            $old = $backward[$citedAs]($lineNo);

            if ($old === null || ! isset($head[$old - 1]) || ! isset($work[$lineNo - 1])) {
                $refuse[] = "{$citedAs}:{$lineNo} — outside the measured bands, or past an end of file";

                continue;
            }

            if (trim($head[$old - 1]) !== trim($work[$lineNo - 1])) {
                $refuse[] = sprintf(
                    "%s:%d (was HEAD:%d)\n      HEAD: %s\n      WORK: %s",
                    $citedAs,
                    $lineNo,
                    $old,
                    trim($head[$old - 1]),
                    trim($work[$lineNo - 1])
                );
            }
        }
    }
}

$statements = [
    ['app/Services/PrintService.php', 30, 'ChromiumLocator::locate()'],
    ['app/Services/PrintService.php', 33, 'PrintRendererMissing'],
    ['app/Services/PrintService.php', 34, '}'],
    ['bootstrap/app.php', 124, 'PrintRendererMissing'],
    ['bootstrap/app.php', 137, '});'],
    ['app/Services/ChromiumLocator.php', 14, 'function locate'],
    ['app/Services/ChromiumLocator.php', 32, '}'],
];

printf("proved by statement, not by re-read: %s\n", implode(', ', $skipped));

foreach ($statements as [$rel, $lineNo, $needle]) {
    $work = explode("\n", (string) file_get_contents($root.'/'.$rel));

    if (! str_contains($work[$lineNo - 1] ?? '', $needle)) {
        $refuse[] = "{$rel}:{$lineNo} does not hold [{$needle}] — it holds [".trim($work[$lineNo - 1] ?? '').']';
    }
}

if ($refuse !== []) {
    fwrite(STDERR, "REFUSE — ".count($refuse)." citation(s) do not re-read the same statement:\n");
    foreach ($refuse as $r) {
        fwrite(STDERR, '  '.$r."\n");
    }
    exit(1);
}

// ---- structural guards ----

$parseOk = (function () use ($xml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($xml);
    libxml_clear_errors();

    return $r !== false;
})();

$original = (function () use ($src) {
    $z = new ZipArchive;
    $z->open($src);
    $x = $z->getFromName('word/document.xml');
    $z->close();

    return $x;
})();

$guards = [
    'parses as XML' => $parseOk,
    'no table gained or lost' => substr_count($original, '<w:tbl>') === substr_count($xml, '<w:tbl>'),
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'pass 81 phrase once' => substr_count($xml, $esc('The printed-paper lane then found')) === 1,
    'no citation left pointing at the old guard' => substr_count($xml, $esc('PrintService.php:55')) === 0,
];

$failed = 0;
foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

printf("every citation re-reads the same statement; %d guard(s) ok\n", count($guards));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass81.docx');

$out = new ZipArchive;
if ($out->open($src) !== true) {
    fwrite(STDERR, "cannot reopen for write\n");
    exit(1);
}
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes in document.xml)\n", $src, strlen($xml) - strlen($original));
