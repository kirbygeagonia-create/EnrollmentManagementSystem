<?php
/**
 * Pass 70 — §21.5 records that the catalog screens were clicked, and what the clicking found.
 *
 * Written as an INSERTED paragraph rather than a rewrite of the "Confirmed: scholarshiptypes…"
 * sentence, because that sentence sets each table name in its own code-styled run (13 runs in the
 * paragraph) and a paragraph rewrite would flatten all of them into one voice.
 */

$src = 'Documentation/EMS Complete Documentation.docx';
$anchor = 'no rows from the seeder';

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$p = strpos($xml, $anchor);
if ($p === false || substr_count($xml, $anchor) !== 1) {
    fwrite(STDERR, "REFUSE: the §21.5 anchor is missing or not unique\n");
    exit(1);
}
$start = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
$end = strpos($xml, '</w:p>', $p) + 6;
$para = substr($xml, $start, $end - $start);
if (! preg_match('/<w:pPr>.*?<\/w:pPr>/s', $para, $pm)) {
    fwrite(STDERR, "REFUSE: no paragraph properties to copy — the inserted paragraph would not match its neighbours\n");
    exit(1);
}

$text = 'Also verified 2026-10-08, by clicking the running system rather than reading it: every '
    .'catalog screen’s Edit dialog now opens holding the row it was opened from. It did not until '
    .'that day — each dialog opened empty, because the components called useForm()->reset() with an '
    .'object, and reset treats every argument as a FIELD NAME, so the call matched no field, did '
    .'nothing, and offended no linter. Thirty-three sites across twenty components now hand that '
    .'object to setData, the method that accepts one, so Reference Data can actually be edited: the '
    .'departmental flag, the transferee waiver and the fee amounts this chapter says the pipeline '
    .'reads are desk-side data only if a desk can change them. The whole form surface was then '
    .'audited mechanically against each hook’s own declared keys — no-op resets, setData objects '
    .'omitting a declared key, string keys no hook declares, and submit buttons neither bound to a '
    .'form by id nor nested inside one (39 files, 44 hooks, 35 object and 179 string setData calls, '
    .'47 submit buttons): clean. The audit is itself distrusted by construction — its first version '
    .'reported every site clean while storing the word "Array" as each key, so all six of its checks '
    .'were proved against deliberately mutated copies before its verdict was taken.';

$escaped = str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
$block = '<w:p>'.$pm[0].'<w:r><w:t xml:space="preserve">'.$escaped.'</w:t></w:r></w:p>';

require __DIR__.'/docx-edit-engine.php';
docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-08-pass70.docx',
    [], [], in_array('--apply', $argv, true), [],
    [['anchor' => $anchor, 'xml' => $block, 'pos' => 'after']]);
