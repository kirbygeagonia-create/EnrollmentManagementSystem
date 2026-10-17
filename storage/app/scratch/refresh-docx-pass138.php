<?php

/**
 * Pass 138 — §28.3 gains G-12 and G-13, the two findings lane 42 raised against the security headers.
 *
 * Both came out of re-reading the third-party assessments against the build they describe
 * (storage/app/scratch/lane42-assessment-claims.php). Neither is a behaviour defect: one was a permission the
 * policy granted for nothing, the other a header this build cannot send in the deployment the school will use.
 *
 * G-12  img-src carried a bare `https:` — any host on the internet could have served an image into a desk's
 *       page. Measured first: a sweep of resources/js and resources/views found no remote image, and every
 *       print <img> reads PrintAssets::logoDataUri(), a data: URI. Tightened 2026-10-17 by the owner's ruling;
 *       ems:print-fidelity still renders 8/8 and the whole policy string is now pinned by a test.
 * G-13  HSTS is never emitted when TLS terminates at a proxy. bootstrap/app.php never calls trustProxies() and
 *       config('trustedproxy.proxies') is unset, so TrustProxies keeps $proxies null, trusts no address and
 *       discards X-Forwarded-Proto — which makes the middleware's own comment, and DEPLOYMENT.md's one-line
 *       "force HTTPS via X-Forwarded-Proto", both false as instructions. Ruled: leave the code (trusting `*`
 *       would let a client forge its own address, and LoginRequest::throttleKey() and auditlogs.ipAddress read
 *       it), and put the step in front of the operator in DEPLOYMENT.md §2.
 *
 * The rows are CLONED from G-11 so they inherit widths, borders, margins and run properties, and both go in as
 * ONE insert — one byte offset cannot carry two edits (pass 74's lesson). The register's own count sentence is
 * rewritten by string replacement after the insert, so it cannot collide with the insert offset.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$anchor = 'Two students the acceptance script walks as returning no longer hold a completed prior year';

$rows = [
    [
        'G-12',
        'The shipped CSP let an image load from any host on the internet',
        'SecurityHeaders put img-src \'self\' data: blob: https: on every response, and a scheme with no host in a '
        .'directive is a wildcard: any https server could have returned image bytes into a desk’s page, which is the '
        .'channel a stored-image XSS looks for. It survived because nothing tested the header — the Round-3 '
        .'assessment of 2026-09-05 recorded the CSP as still unshipped in the very commit that shipped it, and no '
        .'assertion existed either way, so the over-broad directive and the wrong report coexisted for six weeks. '
        .'Measured before being called a defect: a sweep of resources/js and resources/views names no remote image '
        .'(the only external strings are www.w3.org XML namespaces), and every print view’s single <img> tag reads '
        .'App\\Support\\PrintAssets::logoDataUri(), a data: URI. Closed 2026-10-17 by ruling — img-src now allows '
        .'only \'self\', data: and blob:, leaving no third-party source in any directive except the dev-server '
        .'origins connect-src needs for Vite. Proof rather than argument: php artisan ems:print-fidelity still '
        .'renders 8/8, and tests/Feature/SecurityHeadersTest.php compares the entire header string, so widening '
        .'one directive is now a deliberate edit to a test.',
        'Closed by the owner’s ruling 2026-10-17 — tighten, after measuring that nothing loads a remote image',
        'app/Http/Middleware/SecurityHeaders.php:39; raised by lane 42’s re-read of the Round-3 report',
    ],
    [
        'G-13',
        'Strict-Transport-Security is never sent when TLS ends at a proxy',
        'SecurityHeaders gates the header on $request->isSecure(), and this build trusts no proxy: '
        .'bootstrap/app.php never calls trustProxies(), Illuminate\Http\Middleware\TrustProxies leaves $proxies '
        .'null, and config(\'trustedproxy.proxies\') is unset, so Symfony discards X-Forwarded-Proto and the request '
        .'stays non-secure. Measured through the middleware with X_FORWARDED_PROTO=https from a non-loopback '
        .'address — isSecure() false, and no HSTS on the response. Consequence for the school’s install: if '
        .'something in front of the app terminates TLS, every response over HTTPS goes out with no '
        .'Strict-Transport-Security at all, while DEPLOYMENT.md told the operator that forcing the scheme via '
        .'X-Forwarded-Proto settled HTTPS, and the middleware’s own comment said the forwarded header was enough. '
        .'Both were corrected on 2026-10-17 — the comment now states what the build does, and DEPLOYMENT.md §2 '
        .'names the step. Ruled, not fixed in code: the answer must not be trustProxies(at: \'*\'), because that '
        .'lets any client forge its own scheme and source address, and the login throttle key '
        .'(LoginRequest::throttleKey()) plus every audit row’s ipAddress read exactly that address. What remains is '
        .'the install-time decision of which proxy address to trust, which belongs to whoever hosts the campus '
        .'install — a machine that does not exist yet.',
        'Owner / whoever provisions the server — name the proxy in trustProxies(at: …), never `*`',
        'DEPLOYMENT.md §2; pinned by tests/Feature/SecurityHeadersTest.php; middleware comment corrected',
    ],
];

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

$at = strpos($xml, $anchor);
$guard($at !== false && substr_count($xml, $anchor) === 1, 'the G-11 anchor occurs exactly once');

$rowStart = $at === false ? false : strrpos(substr($xml, 0, $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'the G-11 row is bounded');

$guard($rowStart !== false && substr($xml, (int) $rowStart - 7, 7) === '</w:tr>',
    'the row is preceded by a sibling row’s close');
$nextRow = substr($xml, (int) $rowEnd, 6);
$guard($nextRow === '<w:tr>', 'the row is followed by a sibling row’s open — the clone lands inside the table');
printf("  info the row after G-11 begins %s (D-1, so the new G rows stay inside the G block)\n", var_export($nextRow, true));

$row = $rowStart === false ? '' : substr($xml, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 5, 'the template row has exactly five cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    $start = $m[1][1];

    return substr($cell, 0, $start).$escaped.substr($cell, $start + strlen($m[1][0]));
};

$clones = '';
foreach ($rows as $r) {
    $clone = $row;
    foreach ($cm[0] as $i => $cell) {
        $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} of the template holds exactly one text run");
        $clone = str_replace($cell, $setText($cell, $r[$i]), $clone);
    }
    $guard($clone !== $row, "the {$r[0]} clone carries new text");
    $guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), "the {$r[0]} clone is one row and no more");
    $guard(str_contains($clone, htmlspecialchars($r[0])), "the {$r[0]} clone carries its own id");
    $clones .= $clone;
}

$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$expect = [];
foreach ($needle as $k => $n) {
    $expect[$k] = substr_count($clones, $n);
    $guard($expect[$k] === 2 * substr_count($row, $n), "the two clones contribute exactly twice the template's {$k}");
}

$before = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
}

$new = substr_replace($xml, $clones, (int) $rowEnd, 0);

foreach ($needle as $k => $n) {
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $expect[$k], "container {$k}: {$before[$k]} → {$after} (expected +{$expect[$k]})");
}

// The register's own arithmetic must move with its content, or the document contradicts its table.
$countOld = 'G-10 was followed the same day by G-11, so §28.3 holds 28 data rows';
$countNew = 'G-10 was followed the same day by G-11, and lane 42 added G-12 and G-13 on 2026-10-17, '
    .'so §28.3 holds 30 data rows';
$guard(substr_count($new, $countOld) === 1, 'the count sentence is present and unique before rewriting');
$new = str_replace($countOld, $countNew, $new);
$guard(substr_count($new, 'holds 30 data rows') === 1 && ! str_contains($new, 'holds 28 data rows'),
    'the count sentence now reads 30 and the old reading is gone');

// Re-count the rows in the table from the edited bytes, rather than trusting the insert arithmetic.
$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowEnd) + 7;
preg_match_all('/<w:tr.*?<\/w:tr>/s', substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart), $check);
$guard(count($check[0]) === 31, 'the table now holds 1 header + 30 data rows, recounted from the bytes');
preg_match_all('/\bG-(\d+)\b/', substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart), $gids);
$guard(((int) max($gids[1])) === 13 && count(array_unique($gids[0])) === 13, 'the G block now runs G-1 … G-13');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses after both edits');
libxml_clear_errors();

printf("\ntwo rows: %d bytes | count sentence rewritten\n", strlen($clones));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass138.docx');

$z0 = new ZipArchive();
if ($z0->open($src) !== true) {
    fwrite(STDERR, "cannot open the docx to count its parts — nothing written\n");
    exit(1);
}
$partsBefore = count($z0);
$z0->close();

// ZipArchive::OVERWRITE truncates the whole archive rather than one entry. CREATE alone, and the part count
// below is what makes that mistake impossible to commit.
$zip = new ZipArchive();
if ($zip->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $new);
$zip->close();

$check = new ZipArchive();
$check->open($src);
$onDisk = $check->getFromName('word/document.xml');
$partsAfter = count($check);
$check->close();
if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");
    exit(1);
}
if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — parts were lost, restore the backup\n");
    exit(1);
}

printf("WROTE %s — document.xml byte-verified, all %d parts still present\n", $src, $partsAfter);
exit(0);
