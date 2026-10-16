<?php
/**
 * Pass 62 — §1.1's revision record gains the 2026-10-07 row.
 *
 * The row is CLONED from the row above rather than written by hand, so its shading, widths and
 * paragraph styles match the four revision entries a reader compares it against. The shared
 * engine cannot do this: its container guard insists <w:tr> moves by zero, and a new row moves it
 * by one. Every guard runs before the write, and the file is re-read from disk afterwards.
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
$template = 'The owner’s 2026-10-02 rulings become desk actions';
$values = [
    '2026-10-07 (this issue)',
    'The three institutional questions the owner answered on 2026-10-07 become build and text. '
    .'C-4: a program may now waive the school-wide General Entrance Examination for the transferees '
    .'it admits on credit — courses.entranceExamExemptsTransferee, default off, read through one '
    .'method the approval blockers, the department’s own candidate roster and the Stage 1 prerequisite '
    .'all consult, because the first walk of the ruling showed that a waiver honoured by the blockers '
    .'alone would strand the applicant it was written for: still blocked by the departmental paper, '
    .'and unable to sit it. C-5: the departmental examination is required of the board programs the '
    .'owner named — BSCrim, BSCE, BSEE, BSA and BSSW — seeded by name so each owning department can '
    .'replace the set from Reference Data → Courses; the gate had been built and tested while 0 of 16 '
    .'programs set the flag, so no applicant could ever have met or missed it. C-8: retention is '
    .'recorded as measured rather than as a rule — this install purges nothing and held 2,868 audit-log '
    .'rows on the day it was counted — with the period itself left for SEAIT’s records officer to '
    .'state. The document was then re-read against the code instead of the other way round: every PHP '
    .'and React citation inside the reconciliation’s scope was opened and counted, nine front-end cites '
    .'that pointed at lines holding unrelated code were re-pointed, the examination chapter’s ranges '
    .'were re-measured after these very edits moved them, and two sentences written from assumption '
    .'were corrected against the files — one claiming the Evaluation screen no longer contradicted '
    .'itself about where the clearance gate lives, one claiming the general-pass prerequisite was '
    .'enforced twice when its second enforcer is a policy method with no caller. §24’s dictionary '
    .'regenerated against information_schema for the new column, the per-office UAT script gained the '
    .'refusal rows the new gate produces, and the three native alert() calls that still interrupted '
    .'the Blocking and Evaluation desks became the application’s own inline error band.',
];

if (substr_count($xml, $heading) !== 1) {
    fwrite(STDERR, "REFUSE: the heading is not unique\n");
    exit(1);
}
$hPos = strpos($xml, $heading);
if (substr_count(substr($xml, 0, $hPos), '<w:tbl>') !== substr_count(substr($xml, 0, $hPos), '</w:tbl>')) {
    fwrite(STDERR, "REFUSE: the heading sits inside an open table\n");
    exit(1);
}
$tblStart = strpos($xml, '<w:tbl>', $hPos);
$tblEnd = strpos($xml, '</w:tbl>', $tblStart) + 8;
$tbl = substr($xml, $tblStart, $tblEnd - $tblStart);
echo 'heading @'.$hPos.'  table @'.$tblStart.'..'.$tblEnd."\n";

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $tbl, $rows, PREG_OFFSET_CAPTURE);
echo 'rows: '.count($rows[0])."\n";
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $rows[0][0][0], $hcm);
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $hcm[0][0], $htm);
$hdr = trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $htm[1]), ENT_QUOTES | ENT_XML1)));
if (count($hcm[0]) !== 2 || $hdr !== 'Revision') {
    fwrite(STDERR, "REFUSE: first row is [{$hdr}] with ".count($hcm[0])." cells, not the Revision header\n");
    exit(1);
}
echo "guard  that table is the revision record (header cell: {$hdr})\n";

$tAt = null;
foreach ($rows[0] as $k => $r) {
    if (str_contains($r[0], $template)) {
        $tAt = $k;
    }
}
if ($tAt === null || $tAt !== count($rows[0]) - 1) {
    fwrite(STDERR, "REFUSE: template row not found or not last (found: ".var_export($tAt, true).')'
        ."\n");
    exit(1);
}
$tpl = $rows[0][$tAt][0];
$at = $tblStart + $rows[0][$tAt][1] + strlen($tpl);

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $tpl, $cells);
if (count($cells[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSE: template has '.count($cells[0])." cells, this pass writes ".count($values)."\n");
    exit(1);
}
foreach ($cells[0] as $k => $cell) {
    if (substr_count($cell, '<w:p>') !== 1) {
        fwrite(STDERR, "REFUSE: template cell {$k} holds ".substr_count($cell, '<w:p>')." paragraphs\n");
        exit(1);
    }
}
echo 'template cells: '.count($cells[0])."\n";

$escaped = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$clone = $tpl;
foreach ($cells[0] as $k => $cell) {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escaped($values[$k]).'</w:t></w:r></w:p>', $body);
    if (! str_contains($clone, $cell)) {
        fwrite(STDERR, "REFUSE: cell {$k} does not occur in the template row\n");
        exit(1);
    }
    $clone = str_replace($cell, $new, $clone);
}
if (substr_count($clone, '<w:tc>') !== count($values)) {
    fwrite(STDERR, "REFUSE: the clone lost or gained a cell\n");
    exit(1);
}
echo 'clone: '.strlen($clone)." bytes at @{$at}\n";

$newXml = substr_replace($xml, $clone, $at, 0);

foreach ([
    '<w:tr>' => 1, '</w:tr>' => 1,
    '<w:tc>' => count($values), '</w:tc>' => count($values),
    '<w:p>' => substr_count($tpl, '<w:p>'), '</w:p>' => substr_count($tpl, '<w:p>'),
] as $tag => $want) {
    $d = substr_count($newXml, $tag) - substr_count($original, $tag);
    printf("guard %-8s moved by %d, expected %d  %s\n", $tag, $d, $want, $d === $want ? 'ok' : 'REFUSED');
    if ($d !== $want) {
        exit(1);
    }
}
if (substr($newXml, $at, strlen($clone)) !== $clone) {
    fwrite(STDERR, "REFUSE: the cloned row is not byte-identical at its offset\n");
    exit(1);
}
if (simplexml_load_string($newXml) === false) {
    fwrite(STDERR, "REFUSE: document.xml no longer parses\n");
    exit(1);
}
echo "guard  the clone sits exactly at its offset, and the document parses\n";

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-07-pass62.docx');
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
