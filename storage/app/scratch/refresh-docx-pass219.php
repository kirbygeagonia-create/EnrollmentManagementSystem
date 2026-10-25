<?php

/**
 * Pass 219 — the last two resolvable pointers, and they are the eighth and ninth wrong targets.
 *
 *   `:876` — the row says the Evaluation issue action refuses a `courseId` differing from the student's last
 *            enrollment and names the shift flow in the message. That guard is EvaluationController.php:934-941
 *            ("A program change is filed as a shift request, which the dean or program head endorses and Guidance
 *            decides — not issued as an ordinary enrollment"). Line 876 in that file is blank, and its
 *            neighbourhood is the retention-examination blocker.
 *   `:44`  — "stepOrder is the authoritative field in the payload": the payload key is
 *            SendWorkflowNotification.php:63, and `stepLabel` the row calls derived sits at :64 from the label map
 *            read at :31. Lines :44-45 in that file are the completion message and its `['completed' => true]`
 *            array, which carry neither field.
 *
 * Both live in paragraphs whose phrases are run-split, so each edit is the paragraph, rebuilt from document.xml's
 * own runs and refused unless the substitution changed the text and the anchor is unique.
 *
 *   php storage/app/scratch/refresh-docx-pass219.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass219.php --apply  # write
 */
$root = dirname(__DIR__, 3);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$ev = (array) file($root.'/app/Http/Controllers/Evaluation/EvaluationController.php');
$sw = (array) file($root.'/app/Listeners/SendWorkflowNotification.php');
$fails = 0;
foreach ([
    [$ev, 934, 'A program change is a shift request'],
    [$ev, 940, 'shift request'],
    [$sw, 31, '$labels[$step->officeId]'],
    [$sw, 63, "'stepOrder' =>"],
    [$sw, 64, "'stepLabel' =>"],
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

$zip = new ZipArchive();
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

$plan = [
    ['names the shift flow in the message', '/\(\s*:876\s*\)/', '( EvaluationController.php:934-941 )'],
    ['stale map above', '/payload \(\s*:44\s*\)/', 'payload ( SendWorkflowNotification.php:63 )'],
];

$paraEdits = [];
foreach ($plan as [$anchor, $pattern, $replacement]) {
    $n = substr_count($xml, $anchor);
    printf("\n  anchor [%s] count %d (want 1)\n", $anchor, $n);
    if ($n !== 1) {
        echo "REFUSED: an anchor is not unique.\n";
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
    $new = preg_replace($pattern, $replacement, $current);
    if ($new === null || $new === $current) {
        printf("REFUSED: the pattern %s matched nothing in [%s].\n", $pattern, mb_substr($current, 0, 90));
        exit(1);
    }
    printf("  old: …%s…\n  new: …%s…\n", mb_substr($current, max(0, (int) strpos($current, ':') - 8), 110),
        mb_substr($new, max(0, (int) strpos($new, ':') - 8), 130));
    $paraEdits[] = [$anchor, $new];
}

docxApply($src, $root.'/storage/app/scratch/ems-docx-pre-pass219.docx', [], [], $apply, $paraEdits);
