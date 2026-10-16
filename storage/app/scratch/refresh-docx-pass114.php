<?php

/**
 * Pass 114 — the bell paragraph, which the 2026-10-13 client-side sweep rewrote the behaviour of.
 *
 * P 687 of the flattened document is the one passage that describes the bell: what it polls, what it renders
 * when there is nothing to render, and what marking read does. Four of this batch's changes land inside that
 * sentence, so the sentence would otherwise describe a build that no longer exists — the exact failure the
 * authority rule is written against.
 *
 * The clause is split across runs (prose runs and Consolas code runs alternate), so each edit replaces the
 * text of exactly one whole run rather than a substring: `) and renders `, `notifications.length === 0`,
 * ` as an empty state; ` and the citation run each occur once, measured before this pass was written. A
 * substring replace would have had to cross run boundaries, and the run-count guard would then be the only
 * thing noticing a malformed document.
 *
 *   A  the citation: the poll block moved to 60-82 when the state lines went in above it, and the new
 *      markAllRead block at 84-112 is cited in the same mono run, in the document's existing `File.php:51 ,
 *      :65-68` style.
 *   B  the clause "renders notifications.length === 0 as an empty state" — no longer what the screen does.
 *   C  the code run itself, now the label the loading state prints.
 *   D  the trailing prose, which carries the new states and the mark-all-read ruling.
 *   E  appended at the paragraph end: the three other loads the sweep found and fixed.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

/** Replace the text of one whole <w:t> run, so the run count cannot move. */
$runSwap = function (string $open, string $from, string $to, string $label) use ($xml, &$edits, &$refuse): void {
    $anchor = $open.$from.'</w:t>';
    $n = substr_count($xml, $anchor);
    if ($n !== 1) {
        $refuse("{$label}: run anchor appears {$n} time(s), expected 1");

        return;
    }
    $edits[] = [strpos($xml, $anchor), strlen($anchor), $open.$to.'</w:t>', $label];
};

/** Append to one prose run, keeping its formatting. */
$append = function (string $anchorText, string $after, string $label) use ($xml, &$edits, &$refuse): void {
    $n = substr_count($xml, $anchorText);
    if ($n !== 1) {
        $refuse("{$label}: append anchor appears {$n} time(s), expected 1");

        return;
    }
    $edits[] = [strpos($xml, $anchorText), strlen($anchorText), $anchorText.$after, $label];
};

$mono = '<w:r><w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas" w:cs="Consolas"/><w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">';
$body = '<w:t xml:space="preserve">';

// A — the citation. The ranges are parsed FROM the citation string and verified in the order they appear, so
//     a pass that cites a span and verifies a different one cannot pass this file.
$oldCitation = 'AuthenticatedLayout.jsx:57-69';
$citation = 'AuthenticatedLayout.jsx:60-82, :84-112';
$required = [
    ['setInterval(fetchNotifications, 30000)', "route('notifications.index')"],
    ['const handleMarkAllRead = ()', "route('notifications.read-all')"],
];
$runSwap($mono, $oldCitation, $citation, 'the bell citation retimed');

$L = file($root.'/resources/js/Layouts/AuthenticatedLayout.jsx');
preg_match_all('/(\d+)-(\d+)/', $citation, $rm, PREG_SET_ORDER);
if (count($rm) !== count($required)) {
    $refuse('the citation carries '.count($rm).' ranges but this pass verified '.count($required));
}
foreach ($rm as $i => $r) {
    $a = (int) $r[1];
    $b = (int) $r[2];
    $label = "the bell citation {$a}-{$b}";
    if (! isset($required[$i])) {
        $refuse("{$label}: more ranges cited than this pass has checks for");

        continue;
    }
    if (count($L) < $b) {
        $refuse("{$label}: runs past the end of the file (".count($L).' lines)');

        continue;
    }
    $span = implode(' ', array_map('trim', array_slice($L, $a - 1, $b - $a + 1)));
    foreach ($required[$i] as $must) {
        if (! str_contains($span, $must)) {
            $refuse("{$label}: the range does not hold 「{$must}」");
        }
    }
}

// B, C, D — the clause rewritten in place, one run each.
$runSwap($body, ') and renders ',
    ') and, after the 2026-10-13 sweep of every client-side load, names which of three states it is in: ',
    'the clause, part 1');

$runSwap($mono, 'notifications.length === 0', 'Checking the bell…', 'the clause, part 2 (the code run)');

$runSwap($body, ' as an empty state; ',
    ', the list itself while a poll keeps answering, and a “These stopped updating” line the moment one fails; '
    .'markAllRead likewise repaints only on an accepted response, where it used to repaint for any body that '
    .'parsed — and the 419 that a caller asking for JSON receives for an expired session is such a body, so the '
    .'bell could fall to zero on a write that never happened; ',
    'the clause, part 3');

// E — the rest of the sweep, recorded where the reader of the bell paragraph will meet it.
$append('so a Student-addressed row cannot even be marked read.',
    ' The same sweep found three more loads answering for the server, and each now asks the response what it '
    .'was before it paints: the dashboard’s Live Office Queues showed 0 with a green “Queue clear” for every '
    .'queue whenever its thirty-second poll had not answered, because the row fell back to zero for a count '
    .'that was never sent, and it is from that card a desk decides whether to go looking for work; the global '
    .'search kept the previous query’s rows while the new one failed and then reported “No students found '
    .'matching” for a search that never reached the records; and the bell’s list gave no sign it had stopped '
    .'refreshing. A refusal at each of the four prints the server’s own words, which is what the 403 page and '
    .'its JSON answer began to carry the same day.',
    'the three other loads');

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-32s byte %8d  → %s\n", $label, $off, strlen($to) > 78 ? substr(strip_tags($to), 0, 74).'…' : strip_tags($to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'no text element gained or lost' => substr_count($original, '<w:t ') === substr_count($xml, '<w:t '),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the old code phrase is gone from the document' => substr_count($xml, 'notifications.length === 0') === 0,
    'the old citation is gone' => substr_count($xml, $oldCitation) === 0,
    'the retimed citation is present once' => substr_count($xml, $citation) === 1,
    'the three states are named once' => substr_count($xml, 'Checking the bell…') === 1
        && substr_count($xml, 'These stopped updating') === 1,
    'the sweep of the other loads landed once' => substr_count($xml, 'Live Office Queues') === 1
        && substr_count($xml, 'No students found ') === 1,
    'the untouched half of the sentence survives' => substr_count($xml, 'only ever match Staffusers rows') === 1
        && substr_count($xml, 'NotificationController.php:51') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass114.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
