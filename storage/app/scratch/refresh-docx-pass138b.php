<?php

/**
 * Pass 138b — §1.1's revision register gains the 2026-10-16–17 issue, and "(this issue)" moves with it.
 *
 * The register's last row read "2026-10-11–15 (this issue)". It is the table's final row (its `</w:tr>` is
 * followed directly by `</w:tbl>`), so the new row is inserted before that close — a legitimate last-child
 * position, and the guard below expects `</w:tbl>` rather than a sibling `<w:tr>` for exactly that reason.
 *
 * ONE edit carries both changes: the previous row's date loses "(this issue)" and the clone is appended after
 * it, inside one substr_replace over the previous row's own span. Two edits at neighbouring offsets is how
 * pass 74 cut a cloned row in half and made Word refuse the file.
 *
 * Every figure in the new row was measured in this batch, not inherited: the counts come from live `ems`,
 * the citation and claim counts from the three new checkers and their mutant harnesses, the header facts from
 * SecurityHeaders.php and the probe through TrustProxies, and the suite total from the whole-run output.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$oldCell = "2026-10-11\xE2\x80\x9315 (this issue)";
$oldDate = "2026-10-11\xE2\x80\x9315";
$newDate = "2026-10-16\xE2\x80\x9317 (this issue)";

$newProse = 'The two demo-day guides and the per-office walkthrough were re-read against the live database '
    .'rather than remembered, and the re-reading became three machine-checkers instead of notes. '
    .'storage/app/scratch/lane40-walkthrough-claims.php resolves every record id plus status the desk script '
    .'names against the table and the student it claims — it found seven citations to rows retired on '
    .'2026-10-15, five of which had since been re-seated under new ids and were re-pointed, while two are '
    .'genuinely gone and became G-11. lane41-doc-claims.php resolves every peso figure and aggregate the guides '
    .'say out loud, which corrected a count of "the 12 catalogs" where the router answers 13, and a slip fee '
    .'addressed by a counter value that is 5 on this box and 19 on a schema the test suite seeded first — fee '
    .'types are keyed by name, so the guides now name it. '
    .'lane42-assessment-claims.php re-reads the two third-party assessments and separates history from '
    .'present-tense state: the Round-3 report’s counts had drifted (measured again: 93 permissions, 63 tables, '
    .'23 controllers, 17 roles — and its role sentence undercounted by one even on the day it was written), and '
    .'its one open item was false as it was committed, because d29653e added the CSP header in the same commit '
    .'that stored the report. Nothing pinned any security header, which is how that stayed unnoticed for six '
    .'weeks; tests/Feature/SecurityHeadersTest.php now asserts all six plus the entire Content-Security-Policy '
    .'string, proved by two mutants. That test is also what made one follow-up safe: img-src had carried a bare '
    .'https: source — any host on the internet could have returned image bytes into a desk’s page — and a sweep '
    .'found no remote image anywhere, so it is now \'self\' data: blob: (G-12), with ems:print-fidelity still '
    .'rendering 8/8. The second finding is ruled rather than fixed: this build trusts no proxy, so '
    .'X-Forwarded-Proto is discarded and Strict-Transport-Security is never sent when TLS ends in front of the '
    .'app; DEPLOYMENT.md §2 now names the step and why answering it with `at: \'*\'` would be wrong, since the '
    .'login throttle key and every audit row’s ipAddress read the client address (G-13). The restore story was '
    .'measured end to end instead of asserted: the demo pipeline alone, on a database it has only ever touched '
    .'itself, supplies 6 of the 19 records steps 4–14 need; the committed September dump restored raw supplies '
    .'10 of 19, because 10 of its 11 enrollments sit on the retired Summer term; and the dump followed by the '
    .'five-command order the guide now prints reaches 18 of 19, the same as this machine. The one gap on both is '
    .'step 13’s Juan Dela Cruz, which is G-10. Its base rows were also '
    .'created before the term relocation and so seated a second active enrollment on one student; creation now '
    .'follows the move. CI gained a step that runs the schema-independent half of the claim checkers, and every '
    .'checker was proved by planting defects and watching it fail — including one harness that first reported '
    .'five missed classes because the --dir= flag it passed was never read, and one that slept because an '
    .'unreadable claim moved the same counter as a checked one. Two things came back from that work still open '
    .'and both are the owner’s: G-10, the ID hub cannot show a request it has already validated, and a host '
    .'clock the school will feel on demo day — this machine reports its zone as UTC-11 while its wall clock is '
    .'kept on Manila time, so an evening run rolled the application’s day forward and the standing test that '
    .'requires a receipt dated today failed once for no other reason. ems:preflight compares PHP to MySQL, so it '
    .'structurally cannot see that gap. The suite stands at 725 tests and 4570 assertions, all five gates green, '
    .'and the document itself was swept by line, by meaning and by §-reference after every pass in this batch.';

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

$at = strpos($xml, $oldCell);
$guard($at !== false && substr_count($xml, $oldCell) === 1, 'the "(this issue)" cell occurs exactly once');

$rowStart = $at === false ? false : strrpos(substr($xml, 0, (int) $at), '<w:tr');
$rowEnd = $at === false ? false : strpos($xml, '</w:tr>', (int) $at) + 7;
$guard($rowStart !== false && $rowEnd !== false, 'its row is bounded');
$guard(substr($xml, (int) $rowStart - 7, 7) === '</w:tr>', 'the row is preceded by a sibling row’s close');
$guard(substr($xml, (int) $rowEnd, 8) === '</w:tbl>', 'the row is the table’s last row — the clone lands before the close');

$row = substr($xml, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);
$guard(count($cm[0]) === 2, 'the row has exactly two cells');

$setText = function (string $cell, string $text): string {
    if (! preg_match('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $m, PREG_OFFSET_CAPTURE)) {
        throw new RuntimeException('the cell carries no text run to replace');
    }
    $escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    $start = $m[1][1];

    return substr($cell, 0, $start).$escaped.substr($cell, $start + strlen($m[1][0]));
};

// 1. the previous row keeps its prose but loses the marker
$prevRow = $row;
foreach ($cm[0] as $i => $cell) {
    $guard(substr_count($cell, '</w:t>') === 1, "cell {$i} holds exactly one text run");
    if ($i === 0) {
        $prevRow = str_replace($cell, $setText($cell, $oldDate), $prevRow);
    }
}
$guard(! str_contains($prevRow, '(this issue)'), 'the previous row no longer claims to be this issue');
$guard($prevRow !== $row, 'the previous row was rewritten');

// 2. the clone carries the new date and the new prose
$clone = $row;
$clone = str_replace($cm[0][0], $setText($cm[0][0], $newDate), $clone);
$clone = str_replace($cm[0][1], $setText($cm[0][1], $newProse), $clone);
$guard(str_contains($clone, htmlspecialchars($newDate, ENT_NOQUOTES)) || str_contains($clone, $newDate),
    'the clone carries its own date');
$guard(substr_count($clone, '<w:tr') === substr_count($row, '<w:tr'), 'the clone is one row and no more');
$guard(strlen($clone) > strlen($row), 'the clone is longer than the template it came from');

$replacement = $prevRow.$clone;
$needle = ['tr' => '</w:tr>', 'tc' => '<w:tc>', 'p' => '</w:p>', 't' => '</w:t>'];
$before = [];
foreach ($needle as $k => $n) {
    $before[$k] = substr_count($xml, $n);
}
$new = substr_replace($xml, $replacement, (int) $rowStart, (int) ($rowEnd - (int) $rowStart));
foreach ($needle as $k => $n) {
    // the replacement holds the row twice, so the document total must rise by exactly one row's worth
    $inside = substr_count($xml, $n, (int) $rowStart, (int) ($rowEnd - (int) $rowStart));
    $after = substr_count($new, $n);
    $guard($after === $before[$k] + $inside, "container {$k}: {$before[$k]} → {$after} (expected +{$inside}, one new row)");
}

$guard(substr_count($new, '(this issue)') === 1, 'exactly one row still claims to be this issue');
$guard(substr_count($new, $newDate) === 1, 'the new date occurs once');

$tblStart = strrpos(substr($new, 0, (int) $rowStart), '<w:tbl>');
$tblEnd = strpos($new, '</w:tbl>', (int) $rowStart) + 7;
preg_match_all('/<w:tr.*?<\/w:tr>/s', substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart), $check);
$guard(count($check[0]) === 10, 'the revision table now holds 1 header + 9 issue rows, recounted from the bytes');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml parses');
libxml_clear_errors();

printf("\nreplacement: %d bytes for %d (new prose %d chars)\n", strlen($replacement), strlen($row), strlen($newProse));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");
    exit(1);
}
if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-17-pass138b.docx');

$z0 = new ZipArchive();
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

// ZipArchive::CREATE only — OVERWRITE would truncate the whole archive.
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
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");
    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
exit(0);
