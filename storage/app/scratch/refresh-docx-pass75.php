<?php

/**
 * Pass 75 — the two rulings of 2026-10-10 that concern WHO MAY SEE WHAT, and one register row the
 * build overtook three weeks before anyone read it back.
 *
 * 1. §28.6 still says the lost-slip fee "falls back to a code default" with status Partial. It has
 *    not done so since build order step 1 (2026-10-03): Feetypes::clearanceSlipReplacementFee()
 *    returns the Reference Data amount or null, the counter refuses and NAMES the missing fee row,
 *    the slip prints no figure until it exists, and the settings row was dropped by migration. A
 *    "Partial" on a closed item is the kind of row a panel reads as an admission.
 * 2. §22.9 gains the two decisions the owner ruled rather than left implicit: the desk queues are
 *    open to every signed-in staff account BY DESIGN (measured, not assumed), and the shared
 *    print/drop flags are broader than the policies behind them — latent, because every account
 *    that can reach the one page reading them sits in office 1.
 * 3. The revision row gains the rest of the day, including the CI lesson: two of the new tests were
 *    rendering real PDFs through a Chrome install the runner does not have.
 *
 * Dry-run by default. Refuses on any anchor that is not exactly once.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$escRaw = fn (string $s) => str_replace(['<', '>'], ['&lt;', '&gt;'], $s);
$decode = function (string $cellXml): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cellXml, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $m[1] ?? []), ENT_QUOTES | ENT_XML1)));
};

$edits = [];

// ---- 1. §28.6 fee row -----------------------------------------------------------
$feeFragment = 'the fee falls back to a code default';
if (substr_count($xml, $escRaw($feeFragment)) !== 1) {
    fwrite(STDERR, "REFUSE: the fee fragment is not uniquely findable\n");
    exit(1);
}

// Walk out from the fragment to its own <w:tr> … </w:tr>. (The first attempt split the document on
// '<w:tr' and treated each piece as a suffix to compute an offset — pieces are segments, not
// suffixes, so the arithmetic landed in the middle of nowhere and reported a zero-cell row.)
$fragAt = strpos($xml, $escRaw($feeFragment));
$rowStart = strrpos(substr($xml, 0, $fragAt), '<w:tr');
$rowEnd = strpos($xml, '</w:tr>', $fragAt) + 7;
if ($fragAt === false || $rowStart === false || $rowEnd === false) {
    fwrite(STDERR, "REFUSE: the fee row could not be located\n");
    exit(1);
}
$feeRowAt = $rowStart;
$rowXml = substr($xml, $rowStart, $rowEnd - $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rowXml, $cells);
$rowCells = array_map($decode, $cells[0]);
if (count($rowCells) !== 4 || ! str_contains($rowCells[0], 'replacement fee for a lost slip')) {
    fwrite(STDERR, "REFUSE: unexpected fee row (".count($rowCells)." cells): [".implode(' | ', array_map(fn ($c) => mb_substr($c, 0, 30), $rowCells))."]\n");
    exit(1);
}
if ($rowCells[3] !== 'Partial') {
    fwrite(STDERR, "REFUSE: fee row status is not the expected 'Partial' but '{$rowCells[3]}'\n");
    exit(1);
}

$rebuild = function (string $rowXml, array $cellXml, array $newTexts) use ($esc): string {
    $out = $rowXml;
    foreach ($cellXml as $i => $cell) {
        if (! array_key_exists($i, $newTexts) || $newTexts[$i] === null) {
            continue;
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$esc($newTexts[$i]).'</w:t></w:r></w:p>', $body);
        $out = str_replace($cell, $new, $out);
    }

    return $out;
};

// The split first half of the "what actually exists" cell is rewritten whole, so the fragment above
// disappears with it — one cell, one string, no ambiguity about which run said what.
$feeRowNew = $rebuild($rowXml, $cells[0], [
    1 => 'feetypes owns the amount and the desk reads it through Feetypes::clearanceSlipReplacementFee(), '
        .'which returns null when no such fee type exists: the counter then refuses and names the missing '
        .'fee row, and the slip prints no figure until it does. The clearanceReplacementFee settings row was '
        .'dropped by migration and config/settings.php does not exist, so there is no default left to fall back to.',
    2 => '§25.5, P-7, P-12 — closed 2026-10-03 by build order step 1; the fee type is seeded in StarterReferenceDataSeeder',
    3 => 'Closed',
]);
$edits[] = [$feeRowAt, strlen($rowXml), $feeRowNew];

// ---- 2. §22.9 two ruled statements ---------------------------------------------
$anchor = 'For human confirmation (X-4):';
$anchorAt = strpos($xml, $esc($anchor));
if ($anchorAt === false || substr_count($xml, $esc($anchor)) !== 1) {
    fwrite(STDERR, "REFUSE: the X-4 paragraph is not uniquely findable\n");
    exit(1);
}
$paraEnd = strpos($xml, '</w:p>', $anchorAt) + 6;

$newParagraph = function (string $lead, string $body): string {
    return '<w:p><w:pPr><w:pStyle w:val="ListParagraph"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>'
        .'<w:spacing w:after="70"/></w:pPr><w:r><w:rPr><w:b/><w:bCs/></w:rPr><w:t xml:space="preserve">'.$lead.'</w:t></w:r>'
        .'<w:r><w:t xml:space="preserve">'.$body.'</w:t></w:r></w:p>';
};

$breadth = $newParagraph(
    'Ruled by design 2026-10-10 (who may open a desk queue):',
    ' measured on live ems, admission.index, evaluation.index and registrar.index answer for 15 of 15 '
    .'staff accounts — bare Staff holds admission.view and evaluation.view, and the Registrar queue resolves '
    .'through EvaluationPolicy::viewAny — so an applicant list is readable from the Clinic or ID counter as '
    .'well as from the desks that own it. PermissionMatrixTest pins exactly that expectation, so it is a '
    .'decision, not an oversight: these queues carry no record-level authority, and the acts inside them are '
    .'office-scoped. Narrowing the view rights themselves would be a fresh ruling, not a correction.'
);
$flags = $newParagraph(
    'Known and latent 2026-10-10 (shared flags vs policy scope):',
    ' the page-level flags can.printEnrollmentForm and can.enrollmentDrop ask only whether the account holds '
    .'the named permission, while RegistrarPolicy::mayPrint() and ::drop() add the office test on top, so the '
    .'two could disagree. Measured harmless as shipped: the only page reading them is Registrar/Show, which is '
    .'reachable by three accounts on the install (staff8, office1_head, office8_head) and all three sit in '
    .'office 1. It becomes a real divergence the first time a RegistrarApprover is seated in another office — '
    .'recorded here so the next reader measures the accounts rather than assuming either the bug or the fix. '
    .'The flags cannot simply call the gate, because the gate needs an enrollment and the flag is global.'
);
$edits[] = [$paraEnd, 0, $breadth.$flags];

// ---- 3. revision row append ------------------------------------------------------
$tailOld = 'were detector bugs caught that way, not findings.';
$tailNew = $tailOld
    .' The owner then ruled the dead right retired with its module (the catalogue to 93 permissions in 14 '
    .'modules, 298 grants), which was the occasion to rebuild §22 from the installed database — and doing so '
    .'found four stale claims that pre-dated the decision: the exam row still naming exam.verify.general, rows '
    .'summing to 92 under a stated Total of 95, the shift module missing from §22.2 altogether, and §22.5 still '
    .'calling clearance.slip.replace SysAdmin-only three weeks after ruling 8 moved it. Two ruled statements '
    .'joined §22.9, the block delete finally got the button its handler had always guarded (offered only for a '
    .'block the delete would accept), the permission catalog was linked from the admin navigation, and CI caught '
    .'two of the session’s own tests rendering real PDFs through a Chrome install the runner does not have — the '
    .'fake they bound simply did not implement those two methods.';

if (substr_count($xml, $esc($tailOld)) !== 1) {
    fwrite(STDERR, "REFUSE: revision-row tail seen ".substr_count($xml, $esc($tailOld))." times\n");
    exit(1);
}
$edits[] = [strpos($xml, $esc($tailOld)), strlen($esc($tailOld)), $esc($tailNew)];

// ---- apply, descending, refusing any overlap ------------------------------------
usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$nextStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $nextStart) {
        fwrite(STDERR, "REFUSE: overlapping or tied edit ranges\n");
        exit(1);
    }
    $nextStart = $e[0];
}
$newXml = $xml;
foreach ($edits as [$at, $len, $repl]) {
    $newXml = substr_replace($newXml, $repl, $at, $len);
}

$parseOk = (function () use ($newXml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($newXml);
    libxml_clear_errors();

    return $r !== false;
})();
if (! $parseOk) {
    fwrite(STDERR, "REFUSE: document.xml does not parse\n");
    exit(1);
}
if (substr_count($newXml, '<w:p>') - substr_count($xml, '<w:p>') !== 2
    || substr_count($newXml, '</w:p>') - substr_count($xml, '</w:p>') !== 2) {
    fwrite(STDERR, "REFUSE: paragraph count moved by something other than the two new bullets\n");
    exit(1);
}
if (substr_count($newXml, '<w:tr>') !== substr_count($xml, '<w:tr>')
    || substr_count($newXml, '<w:tc>') !== substr_count($xml, '<w:tc>')) {
    fwrite(STDERR, "REFUSE: table containers moved — this pass rewrites cells, never rows\n");
    exit(1);
}
$flat = preg_replace('/\s+/', ' ', html_entity_decode(preg_replace('/<[^>]+>/', ' ', $newXml), ENT_QUOTES | ENT_XML1));
foreach (['there is no default left to fall back to', 'Ruled by design 2026-10-10 (who may open a desk queue)',
          'Known and latent 2026-10-10 (shared flags vs policy scope)', 'the fake they bound simply did not implement'] as $good) {
    if (substr_count($flat, $good) !== 1) {
        fwrite(STDERR, "REFUSE: expected text missing: {$good}\n");
        exit(1);
    }
}
foreach (['the fee falls back to a code default', 'For human confirmation (X-4): whether'] as $keep) {
    // The first must be gone; the second proves the cloned paragraphs went AFTER the original bullet.
    if (substr_count($flat, $keep) !== ($keep === 'the fee falls back to a code default' ? 0 : 1)) {
        fwrite(STDERR, "REFUSE: unexpected count for: {$keep}\n");
        exit(1);
    }
}
// The fee row's new status must be Closed while the vocabulary table's 'Partial' definition survives.
if (substr_count($flat, '| Closed') < 0 || substr_count($xml, 'Partial') === 0) {
    fwrite(STDERR, "REFUSE: status vocabulary check failed\n");
    exit(1);
}

echo "guards  parses; +2 paragraphs and no table container moved; fee row rewritten; X-4 bullet intact\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass75.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
