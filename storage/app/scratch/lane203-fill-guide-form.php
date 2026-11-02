<?php

/**
 * Fill the school's ADS guide form into a separate Word copy: every Applicability box and every §IV concept box
 * ticked, and the answer text written into the analysis area of each problem, the §V presentation sub-items, the
 * §VI defense answer and the §VII fill-ins.
 *
 * The content is PARSED from `Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md` rather than retyped, because two
 * documents answering "does P13 apply" is the defect this repository refuses in every other form — the markdown
 * is the weighed artefact (`lane195` counts it), so the docx is a projection of it.
 *
 * Anchoring is by TEXT, never by paragraph index: two earlier probes disagreed on numbering because one counted
 * only non-empty paragraphs, and a builder that inserts 300 paragraphs invalidates every index after the first.
 *
 * Refusals (exit 1, cause named): the guide missing, the markdown missing, a problem count other than 24 in
 * either file, a concept count other than 12, an answer area whose enclosing cell cannot be found, a tick box
 * that is not one of three, a §V sub-item with no answer in the markdown, a fill-in line whose underscores are
 * absent, an existing output file (so a re-run cannot silently overwrite a delivered copy), and any check that
 * fails after the write — in which case the copy is deleted.
 *
 * Usage: php storage/app/scratch/lane203-fill-guide-form.php [--apply] [--out=<path>] [--force]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$src = 'C:/Users/ADMIN/Downloads/ADS_Final_Project_Guide_EMS_updated.docx';
$out = $root.'/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx';
$mdPath = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';
$apply = false;
$force = false;
$keep = false;

foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if ($a === '--apply') {
        $apply = true;
    } elseif ($a === '--force') {
        $force = true;
    } elseif ($a === '--keep') {
        $keep = true;
    } elseif (preg_match('/^--out=(.*)$/', $a, $m)) {
        $out = $m[1];
    } elseif (preg_match('/^--guide=(.*)$/', $a, $m)) {
        // The harness must be able to plant a defect in a COPY of the form and of the answers; a writer whose
        // inputs are hardcoded forces it to mutate the tracked file, which poisons its own next measurement.
        $src = $m[1];
    } elseif (preg_match('/^--answers=(.*)$/', $a, $m)) {
        $mdPath = $m[1];
    } else {
        fwrite(STDERR, 'REFUSED: unreadable argument '.$a."\n");
        exit(1);
    }
}

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

foreach ([$src, $mdPath] as $need) {
    if (! is_file($need)) {
        $fail("{$need} is not on disk — the guide form and the answer file are both required.");
    }
}
if (is_file($out) && ! $force) {
    $fail("the output {$out} already exists; pass --force to rewrite it, so a delivered copy is never overwritten by accident.");
}

/**
 * Tick boxes are matched to answers BY LABEL, never by position.
 *
 * The guide does not carry one option vocabulary across its rows: the 24 Problem rows read
 * `Applies to our system / Partially applies / N/A (explain below)`, but among the 12 §IV concept rows
 * row 3 (ACID) offers `Explained / Demonstrated in test / N/A`, rows 8 and 9 read
 * `Implemented / Query plan/performance test / N/A`, row 12 reads
 * `Backup/restore or recovery evidence / Explained / N/A`, and the rest read
 * `Implemented / Demonstrated in test / N/A`. The answer file spells each row's whole option set, so its ACID
 * row carries an `Implemented` line the form has no box for. Ticking the k-th `☐` because the k-th answer is ☑
 * would paint `Demonstrated in test` where the school asked whether the concept is `Explained`: a tick that
 * contradicts its own label is worse than a blank, and nothing downstream could see it.
 */
/** `N/A (explain below)` and `N/A` are the same option; the parenthetical is an instruction, not part of the name. */
$stripNote = static function (string $s): string {
    return trim((string) preg_replace('/\s*\((?:explain|explain below|optional)[^)]*\)/iu', '', trim($s)));
};
$optKey = static function (string $s) use ($stripNote): string {
    $s = (string) preg_replace('/\s+/u', ' ', $stripNote($s));

    return mb_strtolower(trim($s, " \t\n\0\x0B.:;,"));
};
/** `[[bool $ticked, string $label], …]` from a line of `☑ Implemented ☐ N/A` tokens, in the line's own order. */
$tokens = static function (string $line): array {
    $out = [];
    if (preg_match_all('/([☐☑])\s*([^☐☑]+)/u', $line, $tm, PREG_SET_ORDER)) {
        foreach ($tm as $t) {
            $out[] = [$t[1] === '☑', trim($t[2])];
        }
    }

    return $out;
};
/** The byte offsets of every `☐` inside one paragraph's own XML, in document order. */
$boxOffsets = static function (array $row, string $xml): array {
    $rowXml = substr($xml, $row['start'], $row['end'] - $row['start']);
    $at = [];
    $off = 0;
    while (($p = strpos($rowXml, '☐', $off)) !== false) {
        $at[] = $row['start'] + $p;
        $off = $p + 3;
    }

    return $at;
};
/** The guide row's OWN option labels, in its own order, read from its own text split on its own `☐`s. */
$guideLabels = static function (array $row): array {
    $parts = explode('☐', $row['text']);
    array_shift($parts); // the run before the first box (`Applicability:  `, or nothing for a concept row)

    return array_values(array_map(static fn (string $p): string => trim($p), $parts));
};

/** --------------------------------------------------------------- 1. read the guide */
$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    $fail('the guide docx did not open.');
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if ($xml === '') {
    $fail('word/document.xml read empty.');
}

$paras = [];
if (preg_match_all('/<w:p[ >].*?<\/w:p>/s', $xml, $pm, PREG_OFFSET_CAPTURE)) {
    foreach ($pm[0] as $o) {
        $body = $o[0];
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $body, $tm);
        $text = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        $paras[] = ['start' => $o[1], 'end' => $o[1] + strlen($body), 'text' => trim($text)];
    }
}

/** Paragraphs whose text matches, in document order. */
$find = static function (string $needle, ?int $after = null) use ($paras): array {
    $hits = [];
    foreach ($paras as $p) {
        if ($after !== null && $p['start'] <= $after) {
            continue;
        }
        if (str_contains($p['text'], $needle)) {
            $hits[] = $p;
        }
    }

    return $hits;
};

$applicRows = $find('Applicability:');
$applicRows = array_values(array_filter($applicRows, static fn (array $p): bool => str_contains($p['text'], '☐')));
// The §IV concept rows are a NAME paragraph followed by its option paragraph, so the name is carried beside the
// row: a positional pairing without it cannot tell whether the answer file's Row 3 is the guide's third concept.
$conceptRows = [];
foreach ($paras as $k => $p) {
    if (str_starts_with($p['text'], '☐ Implemented') || str_starts_with($p['text'], '☐ Explained') || str_starts_with($p['text'], '☐ Backup/restore')) {
        $name = '';
        for ($j = $k - 1; $j >= 0; $j--) {
            if ($paras[$j]['text'] !== '') {
                $name = $paras[$j]['text'];
                break;
            }
        }
        $conceptRows[] = ['name' => $name] + $p;
    }
}
$problemHeads = array_values(array_filter($paras, static fn (array $p): bool => preg_match('/^Problem \d+ —/u', $p['text']) === 1));

printf("guide: %d `Problem N` headings, %d applicability rows, %d concept rows, %d paragraphs\n",
    count($problemHeads), count($applicRows), count($conceptRows), count($paras));
count($problemHeads) === 24 || $fail('the guide holds '.count($problemHeads).' `Problem N —` headings, expected 24.');
count($applicRows) === 24 || $fail('the guide holds '.count($applicRows).' applicability rows, expected 24.');
count($conceptRows) === 12 || $fail('the guide holds '.count($conceptRows).' §IV concept rows, expected 12.');

/** --------------------------------------------------------------- 2. read the answers */
$md = (string) file_get_contents($mdPath);
$plain = static function (string $s): string {
    $s = str_replace(['**', '`'], '', $s);
    // Markdown emphasis survives into a Word cell as literal asterisks and reads as a typographical error on
    // paper: the answer file marks a phrase `*like this*` for a reader of the markdown, and the form must print
    // the phrase. Only a paired marker with no space inside is treated as emphasis, so a stray `*` in prose is
    // left alone rather than eaten.
    $s = (string) preg_replace('/(?<!\*)\*([^*\n]{1,80}?)\*(?!\*)/u', '$1', $s);
    $s = (string) preg_replace('/(?<![\w_])_([^_\n]{1,80}?)_(?![\w_])/u', '$1', $s);
    $s = preg_replace('/\s+/', ' ', $s);

    return trim((string) $s);
};

// 2a. problems. The title is captured on its OWN line: a pattern carrying the `s` modifier lets `(.*?)` cross
// newlines, so the "title" became the whole block — markdown and its `☐` row included — and every paper line
// built from it re-inserted a duplicate form row. The read-back caught that; the heading list must stay clean.
$blocks = [];
if (! preg_match_all('/^### Problem (\d+) — (.*)$/m', $md, $hb, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
    $fail('no `### Problem N —` headings were parsed from the answer file.');
}
$stopAt = strlen($md);
if (preg_match('/^## IV\./m', $md, $h4, PREG_OFFSET_CAPTURE)) {
    $stopAt = (int) $h4[0][1];
}
foreach ($hb as $i => $h) {
    $n = (int) $h[1][0];
    $bodyStart = (int) $h[0][1];
    $bodyEnd = isset($hb[$i + 1]) ? (int) $hb[$i + 1][0][1] : $stopAt;
    $body = substr($md, $bodyStart, $bodyEnd - $bodyStart);
    $title = trim((string) $h[2][0]);
    $field = static function (string $label) use ($body, $plain): ?string {
        // preg_quote with the delimiter named: the label `Workflow difference, or why this is N/A` carries a
        // slash, and an unquoted `/` inside the pattern is read as the closing delimiter, so the real modifier
        // became the `:` after it and preg_match warned and returned false — a field that silently read null.
        if (preg_match('/\*\*'.preg_quote($label, '/').':\*\*\s*(.*?)(?=\n\*\*[A-Z]|\n\*\*Q\d|\z)/s', $body, $m)) {
            return $plain($m[1]);
        }

        return null;
    };
    $applicPick = null;
    $applicOpts = [];
    if (preg_match('/\*\*Applicability:\*\*\s*([^\n]+)/', $body, $m)) {
        $applicOpts = $tokens($m[1]);
        $on = array_values(array_filter($applicOpts, static fn (array $t): bool => $t[0]));
        if (count($applicOpts) !== 3) {
            $fail("problem {$n}: its **Applicability:** line parsed ".count($applicOpts).' option tokens, expected 3.');
        }
        if (count($on) !== 1) {
            $fail("problem {$n}: its **Applicability:** line marks ".count($on).' options ☑, expected exactly 1.');
        }
        $applicPick = $optKey($on[0][1]);
    } else {
        $fail("problem {$n} has no **Applicability:** line.");
    }
    $qa = [];
    if (preg_match_all('/\*\*Q(\d+\.\d+)\*\*\s*(.*?)\n\*\*Answer:\*\*\s*(.*?)(?=\n\*\*Q\d|\n\*\*Workflow|\z)/s', $body, $qm, PREG_SET_ORDER)) {
        foreach ($qm as $q) {
            $qa[] = ['Q'.$q[1], $plain($q[2]), $plain($q[3])];
        }
    }
    // The workflow field is read by PREFIX, because the answer file spells it three ways across the 24 problems
    // (`… or why this is N/A`, `… and N/A justification`, `… adapted`) and two exact labels silently dropped it
    // from Problems 19 and 24 on the paper. Every field the guide asks for is now REQUIRED: a missing one stops
    // the run rather than leaving a blank cell on a form a panelist reads.
    $wf = null;
    if (preg_match('/\*\*Workflow difference[^*]*:\*\*\s*(.*?)(?=\n\*\*[A-Z]|\n\*\*Q\d|\z)/s', $body, $wm)) {
        $wf = $plain($wm[1]);
    }
    $fields = [
        'Concept involved' => $field('Concept involved'),
        'Design' => $field('Design'),
        'Implementation' => $field('Implementation'),
        'Test or demonstration' => $field('Test or demonstration'),
        'Evidence' => $field('Evidence'),
        'Workflow difference' => $wf,
    ];
    foreach ($fields as $label => $value) {
        if ($value === null || $value === '') {
            $fail("problem {$n}: the answer file carries no {$label}, so the form would show a field the guide asks for and the copy leaves blank.");
        }
    }
    if ($qa === []) {
        $fail("problem {$n}: the answer file carries no `**Q{$n}.m**` question and answer for a problem the guide enumerates.");
    }
    $blocks[$n] = [
        'title' => $plain($title),
        'concept' => $fields['Concept involved'],
        'applic' => $applicPick,
        'applicOpts' => $applicOpts,
        'design' => $fields['Design'],
        'implementation' => $fields['Implementation'],
        'test' => $fields['Test or demonstration'],
        'evidence' => $fields['Evidence'],
        'workflow' => $fields['Workflow difference'],
        'qa' => $qa,
    ];
}
count($blocks) === 24 || $fail('the answer file parsed '.count($blocks).' problems, expected 24.');
$qCount = array_sum(array_map(static fn (array $b): int => count($b['qa']), $blocks));
printf("answers: 24 problems, %d question/answer pairs parsed\n", $qCount);

// 2b. concepts — keep each answer's LABEL beside its flag, because the guide's rows do not share one vocabulary
$conceptRowsMd = [];
if (preg_match_all('/\*\*Row (\d+) — (.*?)\.\*\*\s*([^\n]*)\n(.*?)(?=\n\*\*Row |\n\*\*N\/A explanation|\z)/s', $md, $cb, PREG_SET_ORDER)) {
    foreach ($cb as $c) {
        $opts = [];
        foreach ($tokens($c[3]) as [$on, $label]) {
            $opts[$optKey($label)] = $on;
        }
        if ($opts === []) {
            $fail('concept row '.(int) $c[1].' ('.trim($c[2]).') parsed no option tokens.');
        }
        $conceptRowsMd[(int) $c[1]] = ['name' => trim($c[2]), 'opts' => $opts, 'note' => $plain($c[4])];
    }
}
count($conceptRowsMd) === 12 || $fail('the answer file parsed '.count($conceptRowsMd).' concept rows, expected 12.');

// 2c. §V sub-items — the answer file spells each one as `- *Label:* the answer`, so the label ends at a
// SINGLE asterisk (a pattern demanding `:**` matched nothing), and a long label wraps onto the next line in
// markdown, so the label class must cross newlines or the guide's own item goes unanswered.
$vAnswers = [];
if (preg_match('/\n## V\..*?(?=\n## VI\.)/s', $md, $vm)) {
    if (preg_match_all('/^- \*([^*]+?):\*\s*(.*?)(?=\n- \*|\n\*\*|\n## |\z)/sm', $vm[0], $sm, PREG_SET_ORDER)) {
        foreach ($sm as $s) {
            $vAnswers[$plain($s[1])] = $plain($s[2]);
        }
    }
}
printf("§V sub-item answers parsed: %d\n", count($vAnswers));
count($vAnswers) === 25 || $fail('the answer file holds '.count($vAnswers).' §V sub-item answers; the guide lists 25, so a label this parser cannot read is a question that would go unanswered on the paper.');

// 2d. §VI defense answer and §VII fill-ins
$defense = '';
if (preg_match('/\n## VI\..*?\n\*\*The answer:\*\*\s*(.*?)(?=\n\*\*Be prepared|$)/s', $md, $dm)) {
    $defense = $plain($dm[1]);
}
if ($defense === '') {
    if (preg_match('/\n## VI\..*?(?=\n## VII\.)/s', $md, $dm2)) {
        $defense = $plain((string) preg_replace('/^\*\*.*?\*\*\s*/', '', $dm2[0]));
    }
}
$prepared = '';
if (preg_match('/\*\*Be prepared[^\*]*\*\*\s*(.*?)(?=\n\*\*|\n## |\z)/s', $md, $pm2)) {
    $prepared = $plain($pm2[1]);
}
$fill = [];
foreach (['Student 1', 'Student 2', 'Instructor', 'Date of Presentation'] as $label) {
    if (preg_match('/\*\*'.preg_quote($label, '/').':\*\*\s*([^\n]+)/', $md, $fm)) {
        $fill[$label] = $plain($fm[1]);
    }
}
count($fill) === 4 || $fail('only '.count($fill).' of the four §VII fill-ins were found in the answer file.');

/** --------------------------------------------------------------- 3. plan the edits */
$edits = [];          // [offset, length, replacement]
$inserts = [];        // [offset, [text lines]]
$expectedTicks = 0;   // asserted against the read-back; a frozen tally is this file's documented age
$planApplic = [];     // per-row ☑ counts, verified row by row rather than document-wide
$planConcept = [];    // because the inserted answers themselves quote the form's options

/** `[[offset, label], …]` for the ☐s whose OWN label the answer marked ☑; refuses on a label the form cannot show. */
$tickPlan = static function (array $row, array $wanted, string $where) use ($xml, $boxOffsets, $guideLabels, $optKey, $stripNote, $fail): array {
    $boxes = $boxOffsets($row, $xml);
    $labels = $guideLabels($row);
    if (count($boxes) !== 3 || count($labels) !== 3) {
        $fail("{$where}: its row holds ".count($boxes).' ☐ boxes and '.count($labels).' option labels, expected 3 and 3.');
    }
    $chosen = [];
    foreach ($labels as $k => $label) {
        if (! array_key_exists($optKey($label), $wanted)) {
            $fail("{$where}: the form offers `{$label}` but the answer file names no option that resolves to it (its options: "
                .implode(', ', array_map(static fn (string $o): string => '`'.$o.'`', array_keys($wanted))).').');
        }
        if ($wanted[$optKey($label)] === true) {
            $chosen[] = [$boxes[$k], $stripNote($label)];
        }
    }

    return $chosen;
};

foreach ($problemHeads as $i => $head) {
    if ($i >= 24) {
        break;
    }
    $n = $i + 1;
    $row = $applicRows[$i];
    $block = $blocks[$n];
    // The row must sit inside THIS heading's own window, or the positional pairing writes one problem's
    // answer under another problem while every count still reads 24.
    $windowEnd = $problemHeads[$i + 1]['start'] ?? strlen($xml);
    if ($row['start'] < $head['start'] || $row['start'] >= $windowEnd) {
        $fail("problem {$n}: its applicability row @{$row['start']} lies outside this heading's window "
            ."[{$head['start']}, {$windowEnd}), so the two lists are not paired the way the writer assumes.");
    }
    $naLine = $find('If N/A or different from our workflow', $row['start'])[0] ?? null;
    if ($naLine === null) {
        $all = $find('If N/A or different from our workflow');
        $fail("problem {$n}: no `If N/A…` line follows its applicability row (row @{$row['start']}, "
            .count($all).' If-N/A paragraphs document-wide, first @'.($all[0]['start'] ?? -1)
            .', next heading @'.($problemHeads[$i + 1]['start'] ?? -1).', heading: '.mb_substr($head['text'], 0, 60).')');
    }
    if ($naLine['start'] >= $windowEnd) {
        $fail("problem {$n}: its `If N/A…` line @{$naLine['start']} belongs to the next problem's window.");
    }

    $wanted = [];
    foreach ($block['applicOpts'] as [$on, $label]) {
        $wanted[$optKey($label)] = $on;
    }
    $chosen = $tickPlan($row, $wanted, "problem {$n} applicability");
    // A problem states one applicability verdict, so exactly one box may go ☑.
    if (count($chosen) !== 1) {
        $fail("problem {$n}: the label match selected ".count($chosen).' applicability boxes, expected exactly 1.');
    }
    $edits[] = [$chosen[0][0], 3, '☑'];
    $expectedTicks++;
    $planApplic[] = [$n, 1];
    $chosenLabel = $chosen[0][1];   // the guide's own spelling, not the answer file's

    // the answer cell: the enclosing <w:tc> of the `If N/A…` paragraph
    $tcOpen = strrpos($xml, '<w:tc>', $naLine['start'] - strlen($xml));
    $tcClose = strpos($xml, '</w:tc>', $naLine['end']);
    if ($tcOpen === false || $tcClose === false || $tcClose < $naLine['end']) {
        $fail("problem {$n}: its answer area is not inside a table cell (tcOpen=".var_export($tcOpen, true).', tcClose='.var_export($tcClose, true).').');
    }

    $lines = [];
    $lines[] = 'Problem '.$n.' — '.$block['title'].' — answered '.$plain((string) date('Y-m-d')).', registrar-assisted build.';
    $lines[] = 'Concept involved: '.$block['concept'];
    $lines[] = 'Applicability: '.$chosenLabel.'.';
    $lines[] = 'Design: '.$block['design'];
    $lines[] = 'Implementation: '.$block['implementation'];
    $lines[] = 'Test or demonstration: '.$block['test'];
    $lines[] = 'Evidence: '.$block['evidence'];
    // The paper carries one spelling of this field even though the answer file uses three; the guide's own
    // wording is the one a panelist reads, and the answer file's variant stays where it was written.
    $lines[] = 'Workflow difference, or why this is N/A: '.$block['workflow'];
    foreach ($block['qa'] as [$qid, $q, $a]) {
        $lines[] = $qid.' '.$q;
        $lines[] = 'Answer: '.$a;
    }
    $inserts[] = [$tcClose, $lines];
}

$nameKey = static function (string $s): string {
    return mb_strtolower((string) preg_replace('/[^[:alnum:]]/u', '', $s));
};
foreach ($conceptRowsMd as $n => $c) {
    $row = $conceptRows[$n - 1] ?? null;
    if ($row === null) {
        $fail("concept row {$n}: the guide holds only ".count($conceptRows).' option rows, so the pairing ran past the end.');
    }
    // Positional pairing is only safe if the NAME agrees. Guide row 3 is `ACID Properties`; if the answer file's
    // Row 3 were a different concept, its ticks would land inside another concept's boxes and every count (12
    // rows, 3 boxes each) would still read correct.
    if ($nameKey($row['name']) !== $nameKey($c['name'])) {
        $fail("concept row {$n}: the answer file names `{$c['name']}` where the guide's option row number {$n} sits under "
            ."the name `{$row['name']}`, so the two lists are not in the same order and no tick may be placed.");
    }
    $chosen = $tickPlan($row, $c['opts'], "concept row {$n} ({$c['name']})");
    if ($chosen === []) {
        $fail("concept row {$n} ({$c['name']}) marks no box ☑ in the answer file.");
    }
    foreach ($chosen as [$at]) {
        $edits[] = [$at, 3, '☑'];
        $expectedTicks++;
    }
    $planConcept[] = [$n, count($chosen), $row['name']];
}

// §IV's N/A explanation line
$naExpl = $find('N/A explanation / alternative demonstration plan:', $conceptRows[11]['start']);
$naExpl !== [] || $fail('the §IV `N/A explanation` line was not found after the last concept row.');

// §V: answer each sub-item paragraph in place
$secV = $find('V. Final Presentation Requirements')[0] ?? null;
$secVI = $find('VI. Final Defense')[0] ?? null;
$secV !== null || $fail('§V heading not found — the sub-item answers cannot be placed without it.');
$secVI !== null || $fail('§VI heading not found.');
// §V's own item list starts at its first group heading; the sentence under the section title is the
// section's instruction to the pair, not a sub-item, so it is not offered an answer.
$firstGroup = null;
foreach ($paras as $p) {
    if ($p['start'] > $secV['start'] && $p['start'] < $secVI['start'] && preg_match('/^1\. [A-Z]/u', $p['text']) === 1) {
        $firstGroup = $p['start'];
        break;
    }
}
$firstGroup !== null || $fail('§V\'s first group heading (`1. Database Design`) was not found, so the sub-item list cannot be bounded.');
$subItems = [];
foreach ($paras as $p) {
    if ($p['start'] > $firstGroup && $p['start'] < $secVI['start'] && $p['text'] !== '' && preg_match('/^\d+\. [A-Z]/u', $p['text']) !== 1) {
        $subItems[] = $p;
    }
}
// One answer may be placed once: a label that matches two paragraphs would repeat itself on the paper while a
// third item stayed blank, and the tally would still read 25.
$used = [];
$answered = 0;
$unmatched = [];
foreach ($subItems as $p) {
    $key = mb_strtolower($plain($p['text']));
    $best = null;
    $bestLen = -1;
    foreach ($vAnswers as $label => $ans) {
        if (isset($used[$label])) {
            continue;
        }
        $l = mb_strtolower($label);
        if (($key === $l || str_contains($key, $l)) && strlen($l) > $bestLen) {
            $best = $label;
            $bestLen = strlen($l);
        }
    }
    if ($best === null) {
        $unmatched[] = $p['text'];

        continue;
    }
    $used[$best] = true;
    $inserts[] = [$p['end'], ['Answer: '.$vAnswers[$best]]];
    $answered++;
}
printf("§V sub-items answered: %d of %d sub-item paragraphs (%d answers available, %d paragraph(s) left unmatched)\n",
    $answered, count($subItems), count($vAnswers), count($unmatched));
foreach ($unmatched as $u) {
    printf("  UNMATCHED: %s\n", mb_substr($u, 0, 90));
}
foreach (array_keys($vAnswers) as $label) {
    if (! isset($used[$label])) {
        printf("  UNUSED ANSWER: %s\n", mb_substr($label, 0, 90));
    }
}
if ($answered !== count($vAnswers) || $unmatched !== []) {
    $fail("§V matched {$answered} of ".count($vAnswers).' answers and left '.count($unmatched)
        .' sub-item paragraph(s) unmatched — the guide lists 25, so this is the label match, not the document.');
}

// §VI and §VII
$inserts[] = [$find('Be prepared to support your answer')[0]['end'], ['Defense answer: '.$defense, 'Supporting evidence for that answer: '.$prepared]];
foreach (['Student 1', 'Student 2', 'Instructor', 'Date of Presentation'] as $label) {
    $hits = $find($label.':');
    count($hits) === 1 || $fail('the §VII label '.$label.' appears in '.count($hits).' paragraphs; a fill-in must be addressed by a line only it owns.');
    $p = $hits[0];
    // The label and the underscores are TWO runs (`Student 1: ` then `____`), so the writer replaces only the
    // underscore span inside its own run. A pattern demanding `Label: ____` in one `<w:t>` matched nothing and
    // the section refused — which is the right outcome for a writer, and the wrong one for the paper.
    $para = substr($xml, $p['start'], $p['end'] - $p['start']);
    if (! preg_match('/<w:t(?: [^>]*)?>([^<]*_{2,}[^<]*)<\/w:t>/s', $para, $rm, PREG_OFFSET_CAPTURE)) {
        $fail("the §VII line for {$label} holds no run of two or more underscores, so nothing was filled.");
    }
    if (! preg_match('/_{2,}/', $rm[1][0], $um, PREG_OFFSET_CAPTURE)) {
        $fail("the §VII run for {$label} matched but carries no underscore span: ".var_export($rm[1][0], true));
    }
    // PREG_OFFSET_CAPTURE wraps each match as [text, offset], so the span is $um[0][0] at $um[0][1] — reading
    // $um[0] as a string is a TypeError, not a wrong byte count.
    $edits[] = [$p['start'] + $rm[1][1] + $um[0][1], strlen($um[0][0]), htmlspecialchars($fill[$label], ENT_XML1 | ENT_QUOTES, 'UTF-8')];
}

// §IV N/A explanation
$inserts[] = [$naExpl[0]['end'], [$vAnswers['no row is N/A'] ?? 'No row is N/A. Two rows carry an owed demonstration print rather than a missing mechanism, and the designs and the school figures they wait on are stated above.']];

/** --------------------------------------------------------------- 4. apply */
// Ticks, run fills and answer blocks share ONE absolute offset space. Applying the two families in two passes
// is wrong: a §VII fill shortens 52 underscores to a name, so every insert offset below it moves, and an
// insertion of 300 paragraphs moves every tick offset after it. One merged descending pass is the only
// ordering where each absolute offset is still true when its own operation runs.
$ops = [];
foreach ($edits as [$at, $len, $to]) {
    $ops[] = [$at, $len, $to];
}
foreach ($inserts as [$at, $lines]) {
    $xmlNew = '';
    foreach ($lines as $line) {
        $xmlNew .= '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
    }
    $ops[] = [$at, 0, $xmlNew];
}
usort($ops, static fn (array $a, array $b): int => $b[0] <=> $a[0]);
$seen = [];
foreach ($ops as [$at, $len]) {
    if (isset($seen[$at])) {
        $fail("two operations share offset {$at} (one writes {$len} bytes there); a single offset cannot carry both, so nothing was written.");
    }
    $seen[$at] = true;
}
$built = $xml;
foreach ($ops as [$at, $len, $to]) {
    $built = substr_replace($built, $to, $at, $len);
}

printf("\nplan: %d tick edits, %d run fills, %d insertions (%d text lines)\n", $expectedTicks, count($edits) - $expectedTicks,
    count($inserts), array_sum(array_map(static fn (array $i): int => count($i[1]), $inserts)));
printf("document.xml %d -> %d bytes\n", strlen($xml), strlen($built));
if (! $apply) {
    echo "DRY RUN — nothing written. Pass --apply to build the copy.\n";
    exit(0);
}

if (! copy($src, $out)) {
    $fail("the copy could not be created at {$out}.");
}
$w = new ZipArchive;
if ($w->open($out) !== true) {
    $fail('the copy did not open for writing.');
}
$w->addFromString('word/document.xml', $built);
$w->close();

// read back and assert
$v = new ZipArchive;
if ($v->open($out) !== true) {
    $fail('the written copy did not reopen.');
}
$check = (string) $v->getFromName('word/document.xml');
$v->close();
$boxes = substr_count($check, '☑');
$remaining = substr_count($check, '☐');
printf("read back: %d ☑ and %d ☐ document-wide (the answer prose itself quotes the form's options), expected %d ticks placed\n", $boxes, $remaining, $expectedTicks);

// The invariant is PER ROW and PER REGION, not document-wide. A document-wide tally reads 108 only while no
// inserted sentence carries a ballot character — and the §V answer that lists each problem's verdict does quote
// them, which is information on the paper, not a defect. So the reader walks the form's own §III and §IV regions
// (bounded by the part headings) and reports how many look-alike paragraphs it declined to judge elsewhere:
// a checker that counts its own output as the thing it measures reads its planting as a population.
$readRows = static function (string $doc): array {
    preg_match_all('/<w:p[ >].*?<\/w:p>/s', $doc, $pm4);
    $texts = [];
    foreach ($pm4[0] as $b) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $b, $tm4);
        $texts[] = html_entity_decode(implode('', $tm4[1]), ENT_QUOTES | ENT_XML1);
    }
    $at = static function (string $heading) use ($texts): int {
        foreach ($texts as $i => $t) {
            if (trim($t) === $heading) {
                return $i;
            }
        }
        $fail("the copy no longer holds the part heading `{$heading}`, so the form's rows cannot be bounded.");
    };
    [$i3, $i4, $i5] = [$at('III. Database Problems to Solve'), $at('IV. Required Database Concepts'), $at('V. Final Presentation Requirements')];
    $out = ['applic' => [], 'concept' => [], 'applicText' => [], 'lookalike' => 0];
    foreach ($texts as $i => $t) {
        $isApplic = str_contains($t, 'Applicability:') && str_contains($t, 'Applies to our system') && (str_contains($t, '☐') || str_contains($t, '☑'));
        $isConcept = preg_match('/^\s*[☐☑]\s*(Implemented|Explained|Backup\/restore)/u', $t) === 1 && (str_contains($t, '☐') || str_contains($t, '☑'));
        if (! $isApplic && ! $isConcept) {
            continue;
        }
        $inside = ($isApplic && $i > $i3 && $i < $i4) || ($isConcept && $i > $i4 && $i < $i5);
        if (! $inside) {
            $out['lookalike']++;

            continue;
        }
        $pair = [substr_count($t, '☑'), substr_count($t, '☐')];
        if ($isApplic) {
            $out['applic'][] = $pair;
            $out['applicText'][] = trim($t);
        } else {
            $out['concept'][] = $pair;
        }
    }

    return $out;
};
$got = $readRows($check);
printf("read back inside the form's own §III/§IV regions: %d applicability rows, %d concept rows (%d look-alike paragraph(s) elsewhere, in the answer prose, were not judged)\n",
    count($got['applic']), count($got['concept']), $got['lookalike']);
$complaints = [];
if (count($got['applic']) !== 24 || count($got['concept']) !== 12) {
    $complaints[] = 'the copy answers '.count($got['applic']).' applicability rows and '.count($got['concept'])
        .' concept rows; the guide holds 24 and 12';
    // Print the offenders rather than the count: a reader told "48 where 24" gains nothing without the text
    // that made the reader call a paragraph a row.
    foreach ($got['applicText'] as $i => $t) {
        if ($i >= 24) {
            printf("  EXTRA ROW #%d: %s\n", $i + 1, mb_substr($t, 0, 120));
        }
    }
}
foreach ($planApplic as $i => [$n, $want]) {
    if (! isset($got['applic'][$i])) {
        break;
    }
    [$on, $off] = $got['applic'][$i];
    if ($on !== $want || $on + $off !== 3) {
        $complaints[] = "problem {$n} applicability row shows {$on} ☑ and {$off} ☐, expected {$want} ticked of 3";
    }
}
foreach ($planConcept as $i => [$n, $want, $name]) {
    if (! isset($got['concept'][$i])) {
        break;
    }
    [$on, $off] = $got['concept'][$i];
    if ($on !== $want || $on + $off !== 3) {
        $complaints[] = "concept row {$n} ({$name}) shows {$on} ☑ and {$off} ☐, expected {$want} ticked of 3";
    }
}
$placed = array_sum(array_column($got['applic'], 0)) + array_sum(array_column($got['concept'], 0));
foreach ($got['applic'] as $i => [$on, $off]) {
    if ($on === 0) {
        $complaints[] = 'problem '.($i + 1).' applicability row arrived with no box ticked';
    }
}
if ($placed !== $expectedTicks) {
    $complaints[] = "the rows carry {$placed} ticks in total while the plan selected {$expectedTicks}";
}
$ok = $complaints === [] && str_contains($check, 'Concept involved:') && str_contains($check, 'Defense answer:') && str_contains($check, 'Kirby A. Geagonia');
foreach ($complaints as $c) {
    echo '  '.$c."\n";
}
if (! $ok) {
    if (! $keep) {
        @unlink($out);
    }
    $fail('the copy did not read back row by row ('.count($complaints).' complaint(s), plus any missing sentinel text), so it was '
        .($keep ? 'KEPT for reading, as asked.' : 'removed rather than left half-written.').')');
}
printf("WROTE %s\n", $out);
