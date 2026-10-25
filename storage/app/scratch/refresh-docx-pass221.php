<?php

/**
 * Pass 221 — the two rows the shared-rule anchor checker surfaced that are NOT its own attribution.
 *
 * After `lane70-anchor-cites.php` began attributing through `docx-cite-attribution.php` (the bounds sweep's
 * nearest-preceding-file rule and lane 32's reaches-forward-plus-encloses rule) its MOVED queue fell from 47 to 13,
 * and 11 of those 13 read as the tool's remaining limits — a prose-order gloss (`:30 (PrintAssets::logoDataUri)
 * and :31 (config(…)))`, a register row whose column label is a camelCase word. Two did not:
 *
 *   `stepLabel (:45)` — pass 219 moved the field beside it (`stepOrder`) onto SendWorkflowNotification.php:63 and
 *            left this one behind. Line 45 in that file is `['completed' => true]`, which is the completion
 *            branch's payload, not the step label; the label is written into the payload at **:64** from the map
 *            read at :31. The row is stale by the same measure :44 was.
 *   `( :52-63 )` — the WorkflowStepSigned row claims TWO things of one range: "one row per signature
 *            (type workflow_step_signed)" AND "plus one workflow_completed row when the last box closes". The
 *            signature notify is :54-70; the completion notify is **:38-48** — `if ($progress['remaining'] === 0…)`
 *            through its `return`. And the number names no file, so no instrument had ever opened it: the nearest
 *            name on the row is `WorkflowService.php:277-280`, whose :52-63 is `stepsFor()`'s office-box list,
 *            which writes no notification at all.
 *
 * Both needles are contiguous in document.xml (`:52-63` occurs once, `is derived from the stale map` occurs once),
 * so they travel as a text edit and a paragraph edit rather than the cell kind — a cell rewrite would flatten the
 * styled runs inside it.
 *
 *   php storage/app/scratch/refresh-docx-pass221.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass221.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$sw = (array) file($root.'/app/Listeners/SendWorkflowNotification.php');
$fails = 0;
foreach ([
    [$sw, 31, '$labels[$step->officeId]'],
    [$sw, 38, "\$progress['remaining'] === 0"],
    [$sw, 40, "'workflow_completed'"],
    [$sw, 45, "['completed' => true]"],
    [$sw, 48, 'return;'],
    [$sw, 54, '$this->notify('],
    [$sw, 55, "'workflow_step_signed'"],
    [$sw, 63, "'stepOrder' =>"],
    [$sw, 64, "'stepLabel' =>"],
    [$sw, 70, "'lastDesk' =>"],
] as [$l, $n, $needle]) {
    $t = trim($l[$n - 1] ?? '');
    $ok = str_contains($t, $needle);
    printf("  %s :%-4d [%s]\n", $ok ? 'ok  ' : 'BAD ', $n, mb_substr($t, 0, 62));
    $fails += $ok ? 0 : 1;
}
if ($fails > 0) {
    echo "REFUSED: a claimed line does not carry the claim.\n";
    exit(1);
}

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $block): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $block, $tm);

    return html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
};

// The paragraph holding `stepLabel (:45)`: rebuilt from document.xml's own runs, never from the flat dump, which
// joins runs with a space the file does not contain.
$anchor = 'is derived from the stale map';
$n = substr_count($xml, $anchor);
printf("\n  anchor [%s] count %d (want 1)\n", $anchor, $n);
if ($n !== 1) {
    echo "REFUSED: the paragraph anchor is not unique.\n";
    exit(1);
}
$p = strpos($xml, $anchor);
$start = max(strrpos(substr($xml, 0, $p), '<w:p>'), strrpos(substr($xml, 0, $p), '<w:p '));
$end = strpos($xml, '</w:p>', $p) + 6;
if ($start === false || strpos(substr($xml, $start, $p - $start), '</w:p>') !== false) {
    echo "REFUSED: the anchor is not inside one paragraph.\n";
    exit(1);
}
$current = preg_replace('/\s+/', ' ', $flatten(substr($xml, $start, $end - $start)));
$new = preg_replace('/stepLabel \(\s*:45\s*\)/', 'stepLabel (:64)', $current);
if ($new === null || $new === $current) {
    printf("REFUSED: the stepLabel pattern matched nothing in [%s].\n", mb_substr($current, 0, 120));
    exit(1);
}
printf("  old: …%s…\n  new: …%s…\n", mb_substr($current, 0, 150), mb_substr($new, 0, 150));

// The register cell's range: contiguous, so a text edit. `expect = 1` because the same digits occur in the
// WorkflowService citation this pass is NOT touching.
$textEdits = [[':52-63', 'SendWorkflowNotification.php:38-48 and :54-70', null, 1]];
printf("\n  text needle [:52-63] occurs %d time(s) in document.xml (want 1)\n", substr_count($xml, ':52-63'));
if (substr_count($xml, ':52-63') !== 1) {
    echo "REFUSED: the range to replace is not unique.\n";
    exit(1);
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass221.docx', $textEdits, [], $apply, [[$anchor, $new]]);
