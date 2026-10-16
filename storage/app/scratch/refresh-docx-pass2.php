<?php

/**
 * Second pass on Documentation/EMS Complete Documentation.docx, from the citation sweep:
 * the ID controller's real filename, the gate table's abbreviated citation, and the
 * payment gate's wording now that a missing fee sheet no longer reads as settled.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$tmp = sys_get_temp_dir().'/ems-docx2-'.uniqid().'.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$failures = 0;

function rawEdit(string &$xml, string $needle, string $replacement, int $expected, string $label): void
{
    $count = substr_count($xml, $needle);
    if ($count !== $expected) {
        $GLOBALS['failures']++;
        echo "raw  FAIL {$label}: found {$count}x, expected {$expected}x\n";
        return;
    }
    $xml = str_replace($needle, $replacement, $xml);
    echo "raw  ok   {$label} ({$count}x)\n";
}

function paraEdits(string &$xml, string $anchor, array $edits, string $label): void
{
    $pos = strpos($xml, $anchor);
    $start = $pos === false ? false : strrpos(substr($xml, 0, $pos), '<w:p>');
    $end = $pos === false ? false : strpos($xml, '</w:p>', $pos);
    if ($start === false || $end === false || strpos(substr($xml, $start, $pos - $start), '</w:p>') !== false) {
        $GLOBALS['failures']++;
        echo "para FAIL {$label}: anchor or paragraph not found\n";
        return;
    }
    $end += 6;
    $region = substr($xml, $start, $end - $start);
    foreach ($edits as [$expect, $to]) {
        preg_match_all('/(<w:t(?: [^>]*)?>)(.*?)(<\/w:t>)/s', $region, $m, PREG_OFFSET_CAPTURE);
        $hits = [];
        foreach ($m[2] as $i => $inner) {
            if ($inner[0] === $expect) {
                $hits[] = $i;
            }
        }
        if (count($hits) !== 1) {
            $GLOBALS['failures']++;
            echo "para FAIL {$label}: run matches ".count($hits)." for [".substr(str_replace("\n", '\\n', $expect), 0, 50)."]\n";
            return;
        }
        $i = $hits[0];
        $region = substr($region, 0, $m[2][$i][1])
            .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $to)
            .substr($region, $m[2][$i][1] + strlen($expect));
    }
    $xml = substr($xml, 0, $start).$region.substr($xml, $end);
    echo "para ok   {$label}\n";
}

rawEdit($xml, 'IdController', 'IDController', 2, 'ID controller filename as the repository spells it');
rawEdit($xml, '<w:t xml:space="preserve">:110-128</w:t>', '<w:t xml:space="preserve">:170-190</w:t>', 1,
    'gate 4 cites checkClearance at its new lines');

paraEdits($xml, ' the remaining balance is ≤ 0, ', [
    [' the remaining balance is ≤ 0, ', ' a fee sheet exists and still owes ≤ 0, '],
    ['.', ' A missing fee sheet used to satisfy this gate because null compares below zero; since 2026-10-01 the assessment has to exist, so an enrollment that has never been priced is held at Accounting rather than approved as paid.'],
], 'gate 3: an unpriced enrollment is no longer treated as settled');

if ($failures > 0) {
    fwrite(STDERR, "not writing\n");
    exit(1);
}
if (! $apply) {
    echo "dry run — pass --apply to write\n";
    exit(0);
}
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);
echo "APPLIED\n";
