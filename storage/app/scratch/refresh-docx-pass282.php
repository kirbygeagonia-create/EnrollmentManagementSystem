<?php

/**
 * Pass 282: the six questions the prompt sweep raised go into §28.5.
 *
 * §28.3 now holds G-33 … G-43 with an actor named in its "Who must act" column, but the register whose
 * whole purpose is "decisions to put to the school" does not carry the questions themselves. §28.5's own
 * intro says why they must be written twice: the registers in §25, §26 and §27 each end with the questions
 * they cannot answer, and they are gathered here because this is the document's only call on somebody
 * else's time. A school question that lives in one register only is the defect this file already names.
 *
 * Rows are counted by PARTITION, not by pattern: the table's rows are the consecutive three-cell rows
 * that follow its own header, so a question-shaped row in a different table cannot inflate the figure. The
 * pass refuses unless that partition numbers exactly twenty before the insert and twenty-six after — which
 * is also what makes §29.4's count cell safe to move.
 *
 * Moves: §28.5 + 6 rows;  §29.4 row "Open decisions" 20 → 26;  and the living prose that states that figure
 * in words ("counts twenty open decisions"), because a ruling word or a count that only one of the two
 * copies follows is a half-applied register.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass282.php [--apply]
 */

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");

        exit(1);
    }
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx} (ZipArchive answers an error CODE, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$cellsOf = static function (string $row): array {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return $cm[0];
};

$rowText = static function (string $row) use ($flatten, $cellsOf): string {
    return implode(' ', array_map($flatten, $cellsOf($row)));
};

/** Every <w:tr> in document order, as [start, end, text]. */
$allRows = static function (string $xml): array {
    $rows = [];
    $at = 0;
    while (($start = strpos($xml, '<w:tr', $at)) !== false) {
        $end = strpos($xml, '</w:tr>', $start);
        if ($end === false) {
            break;
        }
        $end += 7;
        $rows[] = [$start, $end, substr($xml, $start, $end - $start)];
        $at = $end;
    }

    return $rows;
};

$headerPrefix = 'The decision What the repository could not settle';
$built = $allRows($xml);
$headerAt = null;
foreach ($built as $i => [$s, $e, $r]) {
    if (strncmp($rowText($r), $headerPrefix, strlen($headerPrefix)) === 0) {
        $headerAt = $i;
        break;
    }
}
if ($headerAt === null) {
    fwrite(STDERR, "REFUSED: §28.5's header row [{$headerPrefix}…] was not found; the table moved.\n");

    exit(1);
}

// The partition: consecutive three-cell rows after the header, stopping at the first row that is not one.
$partition = [];
for ($i = $headerAt + 1; $i < count($built); $i++) {
    [$s, $e, $r] = $built[$i];
    if (count($cellsOf($r)) !== 3) {
        break;
    }
    $partition[] = [$s, $e, $r];
}
$before = count($partition);
echo "§28.5 rows by partition: {$before}\n";
if ($before !== 20) {
    fwrite(STDERR, "REFUSED: expected 20 rows in §28.5 and counted {$before}; read the table before writing it.\n");

    exit(1);
}

$lastRow = $partition[count($partition) - 1];
$lastRowAnchor = 'Does the school want installment tracking?';
if (strncmp($rowText($lastRow[2]), $lastRowAnchor, strlen($lastRowAnchor)) !== 0) {
    fwrite(STDERR, 'REFUSED: §28.5\'s last row reads ['.mb_substr($rowText($lastRow[2]), 0, 60)
        .'] — the table grew since this pass was written.\n');

    exit(1);
}
$template = $lastRow[2];
if (count($cellsOf($template)) !== 3) {
    fwrite(STDERR, "REFUSED: the template row is not three cells.\n");

    exit(1);
}

$buildRow = static function (string $row, array $values) use ($flatten, $escape, $cellsOf): string {
    if (count($cellsOf($row)) !== count($values)) {
        fwrite(STDERR, "REFUSED: the template holds ".count($cellsOf($row)).' cells, this row writes '.count($values).".\n");

        exit(1);
    }
    foreach ($cellsOf($row) as $cell) {
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: a template cell holds a second paragraph with text.\n");

                exit(1);
            }
        }
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    $pairs = [];
    foreach ($cm[0] as $j => $x) {
        $pairs[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

    $clone = $row;
    foreach ($pairs as [$j, $at, $len, $src]) {
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);
        foreach (array_slice($pm[0], 1) as $extra) {
            $cell = str_replace($extra, '', $cell);
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($values[$j]).'</w:t></w:r></w:p>', $body);
        $clone = substr_replace($clone, $new, $at, $len);
    }

    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);
    foreach ($check[0] as $j => $cellXml) {
        if ($flatten($cellXml) !== $values[$j]) {
            fwrite(STDERR, "REFUSED: cloned cell {$j} reads [".mb_substr($flatten($cellXml), 0, 80)."].\n");

            exit(1);
        }
    }

    return $clone;
};

$sheet = 'Documentation/School-Decision-Sheet-2026-10-29.md';
$rows = [
    ['Where must an in-app notice take the desk that receives it?',
        'The bell carries a body but no title and no link, so a desk reads a sentence and hunts for the record — prompt §8 asks for both. The mechanism is buildable the moment the destination per notice type is stated, and the answer costs one column on the notice and one route per type, returning off the same screen that shows it. Item 1 on '.$sheet.'; register gap §28.3 G-35.',
        '§28.3'],
    ['How long may a signed-in session live after a desk deactivates the account?',
        'The refusal at the login screen is built and pinned, so the question is only the half that touches people already inside: cutting a live session is a policy cut-off, not a code default, and an answer of "immediately" is one token-version bump while "until they sign out" is nothing at all. Item 2 on '.$sheet.'; the built half is §28.3 G-42.',
        '§28.3'],
    ['Who may read a clinical note, as distinct from the clinic’s outcome?',
        'Screens already restrict the detail to the permitted office, but prompt §10’s privacy clause is a disclosure question the repository cannot answer for a school: the Data Protection Officer owns it, and an answer narrowing or widening the reader list is a policy change on the same table the desk reads. Item 3 on '.$sheet.'; §28.3 G-38.',
        '§28.3'],
    ['Should the audit trail name the act, not just the row change?',
        'The column stores create, update and delete where prompt §9 names seven action families. Measured before it was asked: `auditlogs.action` is a varchar, so naming the act needs NO migration — which is why this is put as a vocabulary question and not a schema one, and the word list costs one seeder-sized edit. Item 4 on '.$sheet.'; §28.3 G-37.',
        '§28.3'],
    ['Is a rejection reason a fixed list, free text, or both?',
        'The mechanism is settled and built in the same batch: a rejected admission and a refused requirement line each now require ten characters, and the text prints wherever the case is read. What the school must say is the vocabulary — a pick-list of institutional reasons, or the officer’s own sentence. Either answer is data on a desk screen, not a migration. Item 5 on '.$sheet.'; §28.3 G-33.',
        '§28.3'],
    ['Does a browser check belong in the pipeline?',
        'Nothing in CI opens a page, so prompt §14’s clause about console errors and empty, loading and responsive states is unverified rather than passing — and the repository’s own gates cannot reach it, because Pest, PHPStan, Pint, ESLint and Vite all judge code that never renders. The cost is runner time and a maintenance owner; the undo is deleting the step. Item 6 on '.$sheet.'; §28.3 G-41.',
        '§28.3'],
];

foreach ($rows as [$q]) {
    foreach ($partition as [$s, $e, $r]) {
        if (strncmp($rowText($r), $q, strlen($q)) === 0) {
            fwrite(STDERR, "REFUSED: §28.5 already carries [".mb_substr($q, 0, 50)."].\n");

            exit(1);
        }
    }
}

$block = '';
foreach ($rows as $values) {
    $block .= $buildRow($template, $values);
}
$xml = substr_replace($xml, $block, $lastRow[1], 0);

// Re-derive the partition after the insert, so the moved figure is a reading rather than arithmetic.
$built2 = $allRows($xml);
$partition2 = [];
foreach ($built2 as $i => [$s, $e, $r]) {
    if (strncmp($rowText($r), $headerPrefix, strlen($headerPrefix)) === 0) {
        for ($j = $i + 1; $j < count($built2); $j++) {
            if (count($cellsOf($built2[$j][2])) !== 3) {
                break;
            }
            $partition2[] = $built2[$j];
        }

        break;
    }
}
$after = count($partition2);
echo "§28.5 rows after the insert: {$after}\n";
if ($after !== 26) {
    fwrite(STDERR, "REFUSED: the table should number 26 and numbers {$after}.\n");

    exit(1);
}

// §29.4's count cell, written by offset with the row re-located in the edited document.
$familyHits = [];
foreach ($allRows($xml) as [$s, $e, $r]) {
    if (strncmp($rowText($r), 'Open decisions ', 15) === 0) {
        $familyHits[] = [$s, $e, $r];
    }
}
if (count($familyHits) !== 1) {
    fwrite(STDERR, 'REFUSED: §29.4\'s "Open decisions" row matched '.count($familyHits)." rows.\n");

    exit(1);
}
[$fStart, $fEnd, $fRow] = $familyHits[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $fRow, $fcm, PREG_OFFSET_CAPTURE);
[$fCell2, $fAt] = [$fcm[0][1][0], (int) $fcm[0][1][1]];
$want = (string) $after;
$newCell = preg_replace('/<w:t(?: [^>]*)?>20<\/w:t>/', '<w:t xml:space="preserve">'.$want.'</w:t>', $fCell2, 1, $cnt);
if ($cnt !== 1) {
    fwrite(STDERR, "REFUSED: the §29.4 count cell does not hold 20 in exactly one text run.\n");

    exit(1);
}
$xml = substr_replace($xml, $newCell, $fStart + $fAt, strlen($fCell2));

// The living prose that states the same figure in words.
$proseFrom = 'counts twenty open decisions';
$proseTo = 'counts twenty-six open decisions';
if (substr_count($xml, $proseFrom) !== 1) {
    fwrite(STDERR, 'REFUSED: the prose that states the decision tally occurs '.substr_count($xml, $proseFrom)
        ." time(s) as [{$proseFrom}]; read it before rewriting.\n");

    exit(1);
}
$xml = str_replace($proseFrom, $proseTo, $xml);

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>'], ['<w:r', '</w:r>']] as [$o, $c]) {
    $a = preg_match_all('/'.preg_quote($o, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $c);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$o} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s: §28.5 takes 6 rows, §29.4's count and its prose move with them (%d bytes of prose)\n",
    $apply ? 'APPLY' : 'DRY RUN', strlen($block));

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass282-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

$w = new ZipArchive;
if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 282 applied: the school's questions are in the register that asks the school.\n";
