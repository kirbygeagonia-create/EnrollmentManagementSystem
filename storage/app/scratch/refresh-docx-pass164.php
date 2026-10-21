<?php

/**
 * Pass 164 — §28.3 gains G-26 and G-27 (the receipt above the amount owed, and the status that outlived its money),
 * the audit's rule count moves from seventeen to nineteen in every place that states it as a present fact, the
 * pointers into the two files this batch edited are re-taken from their bytes, and §29 carries the lane.
 *
 * Both findings were measured before they were written down, and one of them was produced by this project's own
 * instrument: the probe that planted an over-collection deleted it again but never walked the enrollment's status
 * back, leaving a scratch schema saying `paid` over a full ₱17,500 fee while all eighteen rules read 0. That is the
 * evidence the second row rests on, and the row says so rather than pretending it was found in the wild.
 *
 * The pointers are the delicate half. `record()` grew by sixteen lines, so every citation below its insert travels
 * by exactly sixteen — but four ranges *straddle* the insert, and a range cannot move by one number when its start
 * stays put and its end travels. Those were re-taken by testing that HEAD's last cited line is byte-identical to
 * the working line the new end names (storage/app/scratch/lane57-read-pointers.php), and one of them turned out to
 * have been short by three lines before this batch touched anything: `:172-230` cited a method that spanned
 * 172-233 on HEAD. It is written to 172-249, which is what the file says today.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

// ---------------------------------------------------------------- the evidence this pass is allowed to describe
$suite = (string) @file_get_contents($root.'/storage/app/scratch/lane57-suite.txt');
// Pest colourises its own summary, so the log literally reads `Tests:` ESC[39m `    ` ESC[32;1m `780 passed`.
// Matching without stripping the escapes first fails silently and would report the gate as unmet — the same reason
// `grep 'Tests:\s'` finds nothing in this file.
$suite = preg_replace('/\x1b\[[0-9;]*m/', '', $suite) ?? $suite;
// The summary line is the verdict, read from the log rather than from any completion notice. It is matched where it
// starts, because a Pest run that failed prints `Tests: 1 failed, 779 passed …` — and test *names* in this
// repository contain the word "failed" often enough that a whole-file search for it proves nothing either way.
$green = preg_match('/^\s*Tests:\s*(\d+) passed \((\d+) assertions\)/m', $suite, $m) === 1;
$guard($green, 'the whole Pest suite ran green on this tree before the document was edited');
printf("     suite read: %s passed / %s assertions\n", $m[1] ?? '?', $m[2] ?? '?');
$guard(preg_match('/SUITE_EXIT=(\d+)/', $suite, $e) === 1 && (int) $e[1] === 0,
    'and the run’s own exit code is 0 (read '.($e[1] ?? 'nothing').')');

$harness = (string) @file_get_contents($root.'/storage/app/scratch/lane57-harness.txt');
$guard(preg_match('/^\s*FAIL/m', $harness) !== 1
    && str_contains($harness, 'Both rules refuse the state they name'),
    'lane 57’s harness reports every case behaved and reaches its conclusion');

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

/**
 * Replace the text of the first <w:t> in a cloned cell, the way pass 160 does.
 *
 * The escape is a guard rail, not a translation step: every string this pass writes is checked below for a raw
 * `&`, `<` or `>` first, because a text run carrying `&amp;` from a previous escape renders as the literal
 * `&amp;` in Word — the mistake pass 153 made and pass 154 had to undo. ENT_XML1, not ENT_XML: the latter is not
 * a PHP 8.3 constant, and the dry run found that out by fataling on the first cell.
 */
$setText = function (string $cell, string $text): string {
    if (preg_match('/(<w:t(?:\s[^>]*)?>)(.*?)(<\/w:t>)/s', $cell, $one) !== 1) {
        throw new RuntimeException('the cell carries no text run to replace');
    }

    return preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s',
        fn (array $x): string => $x[0] === $one[0]
            ? str_replace($one[2], htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8'), $x[0])
            : $x[0],
        $cell, 1);
};

/** None of this pass's prose may carry an XML metacharacter, so the escape above can never double-escape. */
$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};

$rows = [
    [
        'G-26',
        'The Accounting desk could write a receipt for more than the account owed, and nothing said so',
        '`amount` reached `record()` validated as `required|numeric|min:0.01` and nothing above it, while '
            .'`outstandingBalance()` answers `max(0, …)`. One POST of ₱19,000 against a ₱17,500 sheet therefore '
            .'created the receipt, marked it `paid` — the honest word, since nothing was left owing — and stored a '
            .'balance of ₱0.00, leaving ₱1,500 in the drawer against nothing. Measured on a schema built from empty '
            .'in CI’s order by driving the real route through the HTTP kernel as `office2_head`: accepted, and the '
            .'standing audit — seventeen rules then — read 0 on every one of them. The screen had been asking for '
            .'it: the payment form’s own notice read “the backend accepts any amount, so a cashier tendering more '
            .'than the balance must see the change due”. Ruled 2026-10-20 on that measurement: an OR records money '
            .'the school keeps, so it may not state more than the account owes, and change is handed back at the '
            .'counter before the receipt is filed. The guard names the figure it refuses above, and the notice now '
            .'caps at the same number instead of promising change. A grant cannot produce the state either — an '
            .'award that would cover an already-settled account is refused before it is filed — so a non-zero count '
            .'of the new rule names money that came in by another door: a restored dump, a hand-edited row, or a '
            .'regressed guard. The excess is measured against what is owed, not against the total assessed, so a '
            .'₱17,500 receipt on a ₱17,500 sheet carrying a ₱5,000 grant is named too.',
        'Closed 2026-10-20 by the ruling given on the measurement, and counted by `ems:audit:record-states` as its '
            .'eighteenth rule.',
        '§17 (rulings 13 and 14, the part payment and the refund), §28.3 G-24; found 2026-10-20 by '
            .'storage/app/scratch/lane57-overcollect-state.php and proved by '
            .'storage/app/scratch/mutate-lane57-overcollect.php',
    ],
    [
        'G-27',
        'An enrollment could be left saying `paid` after the money behind that status had gone',
        'The state machine writes `paid` only from `record()`, and only when the balance reaches zero; the void and '
            .'the refund both walk it back out. But nothing counted the case where the status stayed and the money '
            .'did not, so the claim and the account could part company in the direction the register had never '
            .'asked about. Found by this project’s own instrument rather than in the data: the probe that planted '
            .'the over-collection deleted its receipt and recomputed the balance without walking the status back, '
            .'leaving enrollment 9 on a scratch schema saying `paid` over the full ₱17,500 — and every rule then '
            .'standing read 0 on it. Live `ems` and a schema built from empty in CI’s order both read 0 on the new '
            .'rule today, so no row needed repairing; the row records how a state like this is made, and that an '
            .'audit which counts only one direction is half an audit.',
        'Closed 2026-10-20 by the new rule and the probe fix; the status is now walked back through the state '
            .'machine, with a remark that names the tool, so a reconstruction can never be read as a desk act.',
        '§17, §28.3 G-26, §28.3 G-24; found 2026-10-20 by '
            .'storage/app/scratch/lane57-plant-state.php, proved by '
            .'storage/app/scratch/mutate-lane57-overcollect.php',
    ],
];

$anchor = '>G-25<';
$guard(substr_count($new, $anchor) === 1, 'the last existing row is findable by its own id cell');

foreach ($rows as $row) {
    $at = strpos($new, $anchor);
    $rowStart = $at === false ? false : strrpos(substr($new, 0, (int) $at), '<w:tr');
    $rowEnd = $at === false ? false : strpos($new, '</w:tr>', $at) + 7;
    $template = substr($new, (int) $rowStart, (int) $rowEnd - (int) $rowStart);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cells);
    $guard(count($cells[0]) === 5, 'the template row for '.$row[0].' has five cells');

    $clone = $template;
    foreach ($cells[0] as $i => $cell) {
        $assertPlain($row[$i], $row[0].' cell '.($i + 1));
        $clone = str_replace($cell, $setText($cell, $row[$i]), $clone);
    }
    $guard($clone !== $template, $row[0].' carries new text');
    $guard(substr_count($clone, '<w:tr') === substr_count($template, '<w:tr'), $row[0].' is one row and no more');
    foreach (['</w:tr>', '<w:tc>', '</w:p>', '</w:t>'] as $n) {
        $guard(substr_count($clone, $n) === substr_count($template, $n), $row[0].' container '.$n.': one row’s worth');
    }

    $new = substr_replace($new, $clone, (int) $rowEnd, 0);
    $anchor = '>'.$row[0].'<';
}

// ---------------------------------------------------------------- the count the two rows just moved
$lastRow = strpos($new, '>G-27<');
$tblStart = $lastRow === false ? false : strrpos(substr($new, 0, (int) $lastRow), '<w:tbl>');
$tblEnd = $lastRow === false ? false : strpos($new, '</w:tbl>', (int) $lastRow) + 7;
$tblText = substr($new, (int) $tblStart, (int) $tblEnd - (int) $tblStart);
preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $tblText, $all);
$dataRows = count($all[0]) - 1;
$guard($dataRows === 44, '§28.3 recounted from the edited bytes holds 44 data rows (read '.$dataRows.')');
printf("     §28.3 recounted: 1 header + %d data rows\n", $dataRows);

// ---------------------------------------------------------------- 2. the two places that state the size
$sizes = [
    ['lane 55 added G-25, so §28.3 holds 42 data rows',
        'lane 55 added G-25, lane 56 closed G-24 in place, and lane 57 added G-26 and G-27, so §28.3 holds 44 data rows'],
];
foreach ($sizes as [$old, $repl]) {
    $n = substr_count($new, $old);
    $guard($n === 1, 'the §28.3 size sentence is present once ('.$n.'): '.substr($old, 0, 46).'…');
    if ($n === 1) {
        $new = str_replace($old, $repl, $new);
    }
}
$guard(substr_count($new, 'holds 42 data rows') === 0, 'no sentence still says the register holds 42 rows');

// §29.4's family table counts the same register, and a row that grows never edits its neighbour — the failure pass
// 138 left the document with and pass 142 had to repair. Located by the row's own label cell, then by a count token
// unique *inside that row*: the first attempt searched backwards from the label for `<w:tc>` and landed on the label
// cell's own opening tag, so the `42` it wanted sat outside the slice it was checking.
$familyLabel = 'Enforcement, reference-data, screen, authorization and notification gaps';
$labelAt = strpos($new, $familyLabel);
$guard($labelAt !== false, 'the §29.4 family row is findable by its own label');
$famStart = $labelAt === false ? false : strrpos(substr($new, 0, (int) $labelAt), '<w:tr');
$famEnd = $labelAt === false ? false : strpos($new, '</w:tr>', (int) $labelAt) + 7;
$famText = $famStart === false ? '' : substr($new, (int) $famStart, (int) $famEnd - (int) $famStart);
$guard(substr_count($famText, '>42<') === 1, 'exactly one 42 sits inside that row (read '.substr_count($famText, '>42<').')');
if (substr_count($famText, '>42<') === 1) {
    $new = substr_replace($new, str_replace('>42<', '>44<', $famText), (int) $famStart, strlen($famText));
}
$guard(substr_count($new, '>42<') === 0, 'no count cell still says 42');

// ---------------------------------------------------------------- 3. the rule counts
$countEdits = [
    // The two narrative rows that carry the parenthetical.
    ['which now runs sixteen rules where it had run fourteen (seventeen since 2026-10-20)',
        'which now runs sixteen rules where it had run fourteen (nineteen by 2026-10-20, after lanes 56 and 57)'],
    ['runs sixteen rules where it had run fourteen (seventeen since 2026-10-20)',
        'runs sixteen rules where it had run fourteen (nineteen by 2026-10-20, after lanes 56 and 57)'],
    // §19's inventory sentence, stated as a present fact about the command.
    ['seventeen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notification',
        'nineteen rules over idrequests, workflowsteps, enrollmentworkflow, enrollments, clinicrecords, notification'],
    // Lane 54's narrative claim, which described a day the command ran sixteen.
    ['and `ems:audit:record-states` reads 0 across all seventeen rules',
        'and `ems:audit:record-states` read 0 across all sixteen rules it carried that day'],
];
$seen = [];
foreach ($countEdits as [$old, $repl]) {
    if (isset($seen[$old]) || substr_count($new, $old) === 0) {
        continue;
    }
    $seen[$old] = true;
    $n = substr_count($new, $old);
    $guard($n >= 1, 'count sentence present ('.$n.'×): '.substr($old, 0, 58).'…');
    $new = str_replace($old, $repl, $new);
}

// ---------------------------------------------------------------- 4. the §29 narrative for this lane
$narrativeNeedle = 'Lane 56 then closed the row that had been left to the owner.';
$guard(substr_count($new, $narrativeNeedle) === 1, 'the §29 lane narrative is findable by its own opening');

$lane57 = 'Lane 57 then asked the drawer a question the register had never put: what happens when the school holds '
    .'more than anyone owes it. `amount` reached `record()` bounded only from below, and `outstandingBalance()` '
    .'answers `max(0, …)`, so a single POST of ₱19,000 against a ₱17,500 sheet wrote the receipt, marked it `paid` '
    .'because nothing was left owing, stored a balance of ₱0.00, and left ₱1,500 in the drawer against nothing — '
    .'measured through the HTTP kernel as the Accounting desk, on a schema built from empty in CI’s order, with the '
    .'standing audit reading 0 on all seventeen rules it then carried. The payment screen had been inviting exactly '
    .'that figure: its own notice read that the backend accepts any amount and showed the change due. Ruled '
    .'2026-10-20: an OR records money the school keeps, so it may not state more than the account owes; the guard '
    .'names the ceiling it refuses above, the notice now caps at the same number, and the eighteenth rule counts any '
    .'sheet whose held receipts pass what it is owed — measured against the owed figure, not the assessed total, so '
    .'a full receipt over a covered account is named too. The lane found the second half by turning on its own '
    .'instrument: the probe that un-planted its receipt deleted the row and recomputed the balance but never walked '
    .'the enrollment’s status back, leaving a scratch schema saying `paid` over the whole fee while every rule read '
    .'0. The nineteenth rule is that mirror — a status asserting a settlement its money has left — and it reads 0 '
    .'on live `ems`, on HEAD’s dataset, and on a schema built from empty, so no row needed repairing. Two pointers '
    .'this batch invalidated were re-taken from the files’ own bytes rather than from arithmetic, and one of them '
    .'turned out to have been three lines short before anything here moved: `AccountingController.php:172-230` '
    .'cited a method that spanned 172-233 on HEAD and 172-249 now.';

$guard(substr_count($new, 'Lane 57') === 0, 'the document carries no lane 57 narrative yet');
$assertPlain($lane57, 'the §29 lane 57 paragraph');

// ---------------------------------------------------------------- 5. the pointers
$pointers = [
    // ranges wholly below the insert: the whole range travels by the measured shift
    ['AccountingController.php:221-229', 'AccountingController.php:237-245', 1],
    ['AccountingController.php:198-201', 'AccountingController.php:214-217', 2],
    ['AccountingController.php:197-216', 'AccountingController.php:213-232', 1],
    ['AccountingController.php:244-281', 'AccountingController.php:260-297', 4],
    ['AccountingController.php:201-213', 'AccountingController.php:217-229', 1],
    ['AccountingController.php:207-213', 'AccountingController.php:223-229', 2],
    ['AccountingController.php:292', 'AccountingController.php:308', 1],
    ['AccountingController.php:340', 'AccountingController.php:356', 1],
    // ranges that straddle the insert: the start stays, the end travels (verified byte-for-byte)
    ['AccountingController.php:176-209', 'AccountingController.php:176-225', 1],
    ['AccountingController.php:180-207', 'AccountingController.php:180-223', 1],
    // re-read against the construct, because the old number was wrong before this batch
    ['AccountingController.php:172-230', 'AccountingController.php:172-249', 1],
    // the two signStepByOffice sites named in one sentence, and the audit's own spans
    ['at AccountingController.php:227 when a payment settles the account, and again at :274',
        'at AccountingController.php:243 when a payment settles the account, and again at :290', 1],
    ['AuditRecordStates.php:47-183', 'AuditRecordStates.php:47-193', 1],
    ['AuditRecordStates.php:340-349', 'AuditRecordStates.php:350-359', 1],
    ['AuditRecordStates.php:325-330', 'AuditRecordStates.php:335-340', 1],
    ['the two box rules at :295-317', 'the two box rules at :305-327', 1],
];

foreach ($pointers as [$old, $repl, $expect]) {
    $n = substr_count($new, $old);
    $guard($n === $expect, sprintf('pointer %s found %d× (expected %d)', $old, $n, $expect));
    if ($n === $expect) {
        $new = str_replace($old, $repl, $new);
    }
}

$guard(substr_count($new, 'AccountingController.php:172-230') === 0, 'the short pre-batch record() span is gone');
$guard(substr_count($new, 'AuditRecordStates.php:47-183') === 0, 'the pre-batch handle() span is gone');
$guard(substr_count($new, '>G-26<') === 1 && substr_count($new, '>G-27<') === 1, 'both new id cells are present once');

// The narrative goes in as its own <w:p> after §29's lane 56 paragraph, carrying that paragraph's own properties so
// it matches the block it joins. Built by byte offsets that are each verified before use — the first attempt used a
// regex to fetch the template paragraph, failed to compile ("missing closing parenthesis"), and the pass reported
// the template as not found rather than writing on a guess.
$at = strpos($new, $narrativeNeedle);
$guard($at !== false, 'the lane 56 paragraph that anchors the narrative is present');
$pStart = $at === false ? false : strrpos(substr($new, 0, (int) $at), '<w:p');
$pEnd = $at === false ? false : strpos($new, '</w:p>', (int) $at);
$guard($pStart !== false && $pEnd !== false, 'the anchor paragraph has both ends at known offsets');
if ($pStart !== false && $pEnd !== false) {
    $paraText = substr($new, (int) $pStart, (int) $pEnd + 6 - (int) $pStart);
    $pPr = preg_match('/<w:pPr>.*?<\/w:pPr>/s', $paraText, $pp) === 1 ? $pp[0] : '';
    $rPr = preg_match('/<w:rPr>.*?<\/w:rPr>/s', $paraText, $rr) === 1 ? $rr[0] : '';
    $insert = '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.$lane57.'</w:t></w:r></w:p>';

    $guard(substr_count($insert, '</w:p>') === 1 && substr_count($insert, '<w:p') === 1,
        'the inserted paragraph is exactly one <w:p>');
    $guard(substr_count($insert, '<w:t') === 1, 'and carries exactly one text run');
    $guard(str_contains($insert, $lane57), 'and its text survives the assembly');
    printf("     narrative paragraph cloned from the lane 56 block: pPr %d bytes, rPr %d bytes\n",
        strlen($pPr), strlen($rPr));

    $new = substr_replace($new, $insert, (int) $pEnd + 6, 0);
}

$guard($new !== $xml, 'the bytes were rewritten ('.(strlen($new) - strlen($xml)).' length change)');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass164.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass164.tmp';
$write = new ZipArchive();
if ($write->open($out, ZipArchive::CREATE) !== true) {
    echo "REFUSED: cannot build the replacement archive.\n";
    exit(1);
}
$read = new ZipArchive();
$read->open($src);
for ($i = 0; $i < $read->numFiles; $i++) {
    $name = $read->getNameIndex($i);
    $write->addFromString($name, $name === 'word/document.xml' ? $new : $read->getFromIndex($i));
}
$write->close();
$read->close();
if (! rename($out, $src)) {
    echo "REFUSED: could not move the new archive; backup at {$backup}.\n";
    exit(1);
}
$check = new ZipArchive();
$check->open($src);
$guard($check->numFiles === $partCount, 'the archive still holds '.$partCount.' parts');
$round = (string) $check->getFromName('word/document.xml');
$check->close();
$guard(hash('sha256', $round) === hash('sha256', $new), 'word/document.xml on disk is the bytes this pass built');
if ($fails > 0) {
    printf("\n%d guard(s) failed after writing; backup at %s\n", $fails, $backup);
    exit(1);
}

printf("\nPass 164 written; backup at %s\n", $backup);
exit(0);
