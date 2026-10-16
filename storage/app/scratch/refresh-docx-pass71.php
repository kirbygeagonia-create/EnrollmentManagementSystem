<?php
/**
 * Pass 71 — §1.1 gains the 2026-10-08 entry, and the previous row stops claiming to be current.
 *
 * Row cloned from the one above (the engine's container guard insists <w:tr> does not move), then
 * the older row's "(this issue)" label edited in the same write so exactly one entry is current.
 * The book's convention is one dated row per issue; today's work is six changes across two days and
 * has no entry at all, which is how a reader tells that the document stopped being re-read.
 */

$src = 'Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$heading = '1.1 Revision Record';
$template = 'The three institutional questions the owner answered on 2026-10-07';
$oldLabel = '2026-10-07 (this issue)';
$newLabel = '2026-10-07';
$values = [
    '2026-10-08 (this issue)',
    'The rulings of 2026-10-07 were walked through the running system the next day, and the walk '
    .'found what 588 passing tests could not. Three of the five programs the departmental-examination '
    .'ruling had just flagged — BSA, BSCE and BSEE — administer no general paper at all, so every '
    .'applicant to them met a blocker no desk was permitted to clear; and the transferee waiver signed '
    .'the same day would have stranded exactly the applicants it named, because the department’s roster '
    .'is built from general passers and its prerequisite demanded the very pass the waiver excuses. '
    .'Both closed by making one predicate answer all three readers (Courses::waivesGeneralEntranceExam), '
    .'by demanding Stage 1 only of programs that run Stage 1, and by letting a board-only program’s '
    .'roster be its own unenrolled applicants; the five-program set stays provisional until the owning '
    .'departments replace it. Three further defects: an examination result was matched to the student '
    .'rather than to the program and term on its row (now scoped, measured first — no applicant’s '
    .'outcome changed); the retention badge read the student’s latest result for the program while the '
    .'signature refused for want of one this term (both readers now scope to the term, and the fix was '
    .'kept line-neutral because twenty-one citations live in that controller); and exam.verify.general '
    .'was retired with the policy method that was its only reader, moving the catalogue to 94 '
    .'permissions and 315 grants. Sweeping the document by CLAIM rather than by file name — the sweep '
    .'the first one missed — turned up five cells still asserting the pre-G-8 behaviour, §9’s B-2 row '
    .'among them, and all six groups of drifted citations were re-measured. Finally the walk found that '
    .'every catalog Edit dialog opened empty: the components called reset() with an object, a call that '
    .'is legal, lint-clean and inert; thirty-three sites in twenty components now use setData, and the '
    .'whole form surface was audited against each hook’s declared keys — the audit itself proved against '
    .'mutants before its clean verdict was believed.',
];

foreach ([$heading, $template, $oldLabel] as $n) {
    if (substr_count($xml, $n) !== 1) {
        fwrite(STDERR, "REFUSE: '".mb_substr($n, 0, 40)."' seen ".substr_count($xml, $n)." times, expected 1\n");
        exit(1);
    }
}

// The label edit is applied FIRST and every offset derived afterwards. It was the other way round
// in the first draft, and the guard caught it: shortening a label that sits 15 KB BEFORE the
// insertion point moves the insertion point, so the cloned row landed twelve bytes off target.
$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$xml = str_replace($esc($oldLabel), $esc($newLabel), $xml);

$hPos = strpos($xml, $heading);
if (substr_count(substr($xml, 0, $hPos), '<w:tbl>') !== substr_count(substr($xml, 0, $hPos), '</w:tbl>')) {
    fwrite(STDERR, "REFUSE: the heading sits inside an open table\n");
    exit(1);
}
$tblStart = strpos($xml, '<w:tbl>', $hPos);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart) + 8;
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $tblStart, $tblEnd - $tblStart), $rows, PREG_OFFSET_CAPTURE);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rows[0][0][0], $hcm);
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $hcm[0][0], $htm);
$hdr = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $htm[1]), ENT_QUOTES | ENT_XML1)));
if (count($hcm[0]) !== 2 || $hdr !== 'Revision') {
    fwrite(STDERR, "REFUSE: not the revision table (header cell [{$hdr}], ".count($hcm[0])." cells)\n");
    exit(1);
}
$tAt = null;
foreach ($rows[0] as $k => $r) {
    if (str_contains($r[0], $template)) {
        $tAt = $k;
    }
}
if ($tAt === null || $tAt !== count($rows[0]) - 1) {
    fwrite(STDERR, "REFUSE: template row not the last (found ".var_export($tAt, true).', rows='.count($rows[0]).")\n");
    exit(1);
}
$tpl = $rows[0][$tAt][0];
printf("revision table @%d..%d rows=%d, cloning row %d\n", $tblStart, $tblEnd, count($rows[0]), $tAt);

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $tpl, $cells);
if (count($cells[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSE: template has '.count($cells[0])." cells, pass writes ".count($values)."\n");
    exit(1);
}
foreach ($cells[0] as $k => $cell) {
    if (substr_count($cell, '<w:p>') !== 1) {
        fwrite(STDERR, "REFUSE: template cell {$k} holds ".substr_count($cell, '<w:p>')." paragraphs\n");
        exit(1);
    }
}

$escaped = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$clone = $tpl;
foreach ($cells[0] as $k => $cell) {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escaped($values[$k]).'</w:t></w:r></w:p>', $body);
    $clone = str_replace($cell, $new, $clone);
}

$at = $tblStart + $rows[0][$tAt][1] + strlen($tpl);
$newXml = substr_replace($xml, $clone, $at, 0);
if (substr_count($newXml, $esc('(this issue)')) !== 1) {
    fwrite(STDERR, "REFUSE: more than one row now says it is the current issue\n");
    exit(1);
}
foreach (['<w:tr>' => 1, '</w:tr>' => 1, '<w:tc>' => 2, '</w:tc>' => 2,
          '<w:p>' => substr_count($tpl, '<w:p>'), '</w:p>' => substr_count($tpl, '<w:p>')] as $tag => $want) {
    $d = substr_count($newXml, $tag) - substr_count($original, $tag);
    if ($d !== $want) {
        fwrite(STDERR, "REFUSE: {$tag} moved by {$d}, expected {$want}\n");
        exit(1);
    }
}
if (substr($newXml, $at, strlen($clone)) !== $clone || simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSE: clone not byte-identical, or document.xml does not parse\n");
    exit(1);
}
echo "guard  containers moved by the template's own counts, clone at its offset, document parses\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-08-pass71.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: the file on disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
