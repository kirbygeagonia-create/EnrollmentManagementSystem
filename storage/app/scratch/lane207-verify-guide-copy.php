<?php

/**
 * Read-only: verify the ANSWERED guide copy, window by window, rather than by document-wide totals.
 *
 * The writer proves what it planned; this proves what the paper now says. For each of the 24 problems it locates
 * the `Problem N —` heading in the built document and asks whether that problem's OWN answer block sits between
 * its heading and the next one — title, Applicability line with exactly one ☑, and each of the six analysis
 * fields the guide asks for, plus the question/answer pairs the guide enumerates. A copy in which every answer
 * exists somewhere but three of them sit under the wrong problem would read perfect on a substring census and
 * wrong on every page a panelist opens, which is the wrong-target defect this repository keeps meeting in
 * citations, arriving in a table cell.
 *
 * It also re-reads the §IV concept rows and §V sub-items the same way, and the four §VII fill-ins.
 *
 * It refuses (exit 1, cause named) when the copy is missing, when a part heading cannot be found, when the
 * problem count is not 24, when any window lacks its own answer, or when the fill-ins are still underscores.
 *
 * Usage: php storage/app/scratch/lane207-verify-guide-copy.php [--copy=<path>]
 */
$root = dirname(__DIR__, 3);
chdir($root);
$copy = $root.'/Documentation/ADS_Final_Project_Guide_EMS_ANSWERED.docx';
$answers = $root.'/Documentation/GUIDE-PROBLEM-ANSWERS-ADS.md';

foreach (array_slice($GLOBALS['argv'], 1) as $a) {
    if (preg_match('#^--copy=(.*)$#s', $a, $m)) {
        $copy = $m[1];
    } elseif (preg_match('#^--answers=(.*)$#s', $a, $m)) {
        $answers = $m[1];
    } else {
        fwrite(STDERR, "REFUSED: this tool does not read the flag {$a}\n");
        exit(1);
    }
}
foreach ([$copy, $answers] as $need) {
    if (! is_file($need)) {
        fwrite(STDERR, "REFUSED: {$need} is not on disk — the answered copy and the answer file are both required.\n");
        exit(1);
    }
}

$z = new ZipArchive;
if ($z->open($copy) !== true) {
    fwrite(STDERR, "REFUSED: {$copy} did not open as an archive.\n");
    exit(1);
}
$xml = (string) $z->getFromName('word/document.xml');
$z->close();
if ($xml === '') {
    fwrite(STDERR, "REFUSED: word/document.xml read empty.\n");
    exit(1);
}

$paras = [];
preg_match_all('#<w:p[ >].*?</w:p>#s', $xml, $pm, PREG_OFFSET_CAPTURE);
foreach ($pm[0] as $o) {
    preg_match_all('#<w:t(?: [^>]*)?>(.*?)</w:t>#s', $o[0], $tm);
    $paras[] = ['start' => $o[1], 'text' => trim(html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1))];
}
$copyText = (string) preg_replace('/\s+/u', ' ', implode(' ', array_column($paras, 'text')));

$md = (string) file_get_contents($answers);
if (! preg_match_all('/^### Problem (\d+) — (.*)$/m', $md, $hb, PREG_SET_ORDER)) {
    fwrite(STDERR, "REFUSED: no `### Problem N —` headings in {$answers}.\n");
    exit(1);
}
$wantByNumber = [];
foreach ($hb as $h) {
    $n = (int) $h[1];
    $qs = preg_match_all('/\*\*Q'.$n.'\.\d+\*\*/', $md);
    $wantByNumber[$n] = ['title' => trim((string) preg_replace('/\s+/', ' ', str_replace(['**', '`'], '', $h[2]))), 'q' => $qs];
}
count($wantByNumber) === 24 || fwrite(STDERR, 'REFUSED: the answer file holds '.count($wantByNumber).' problems, expected 24.') && exit(1);

// Locate the form's own problem headings in the copy. Their inserted answer line begins with the same words, so
// the heading list is the ORIGINAL form headings only: those are the paragraphs that are exactly `Problem N — X`
// with no `answered` clause.
$heads = [];
foreach ($paras as $p) {
    // The form's own heading is exactly `Problem N — Title`; the inserted answer header carries an
    // `— answered <date>` clause, so the two are told apart by their own words, not by an index.
    if (preg_match('/^Problem (\d+) — /u', $p['text'], $hm) === 1 && ! str_contains($p['text'], '— answered')) {
        $heads[(int) $hm[1]] = $p['start'];
    }
}
ksort($heads);
if (count($heads) !== 24) {
    fwrite(STDERR, 'REFUSED: the copy shows '.count($heads).' form headings for the 24 problems, so a window cannot be bounded.\n');
    exit(1);
}
$bounds = [];
$keys = array_keys($heads);
// The last problem's window must stop at the next PART, not at the end of the document, or every §IV to §VII
// paragraph is charged to Problem 24 and a defect in the defense answer reads as a defect in a problem.
$partFour = null;
foreach ($paras as $p) {
    if (preg_match('/^IV\.\s+Required Database Concepts/u', $p['text']) === 1) {
        $partFour = $p['start'];
        break;
    }
}
if ($partFour === null) {
    fwrite(STDERR, "REFUSED: the copy holds no `IV. Required Database Concepts` heading, so the last problem's window cannot be bounded.\n");
    exit(1);
}
foreach ($keys as $i => $n) {
    $bounds[$n] = [$heads[$n], $i + 1 < count($keys) ? $heads[$keys[$i + 1]] : $partFour];
}

$problems = [];
foreach ($bounds as $n => [$from, $to]) {
    $bodyText = '';
    foreach ($paras as $p) {
        if ($p['start'] >= $from && $p['start'] < $to) {
            $bodyText .= $p['text']."\n";
        }
    }
    $missing = [];
    if (! str_contains($bodyText, $wantByNumber[$n]['title'])) {
        $missing[] = 'its own title';
    }

    // PLACEMENT, not mere presence. Each problem has two writing boxes: the ruled table that follows its
    // `Applicability` row (the space under `Your Analysis / Solution`) and the ruled table under `If N/A or
    // different from our workflow`. The first delivered copy put everything in the second box and left the analysis
    // lines blank on paper, so this reader asks WHERE each field sits. A copy carrying every label in the wrong box
    // used to pass here. ($lineText, never $copyText: the document-wide text is what the §IV and §VI sentinels read
    // below, and a loop variable that shadows it makes a present paragraph read as absent.)
    $inWindow = [];
    foreach ($paras as $p) {
        if ($p['start'] >= $from && $p['start'] < $to) {
            $inWindow[] = $p['text'];
        }
    }
    $indexOf = static function (string $prefix) use ($inWindow): int {
        foreach ($inWindow as $i => $lineText) {
            if (str_starts_with($lineText, $prefix)) {
                return $i;
            }
        }

        return -1;
    };
    $formIdx = -1;
    foreach ($inWindow as $i => $lineText) {
        // `Applicability: ☐ …` on the blank form, `Applicability: ☑ …` once ticked: a locator that demands ☐ reads
        // every answered window as having no applicability row at all.
        if (preg_match('/^Applicability:\s+[☐☑]/u', $lineText) === 1) {
            $formIdx = $i;
            break;
        }
    }
    $naIdx = $indexOf('If N/A or different from our workflow');
    if ($formIdx < 0 || $naIdx < 0 || $naIdx <= $formIdx) {
        $problems[] = 'P'.$n.': its two boxes cannot be located (applicability row at '.$formIdx.', If-N/A line at '.$naIdx.')';
    } else {
        foreach (['Concept involved:', 'Design:', 'Implementation:', 'Test or demonstration:', 'Evidence:', 'Answered '] as $field) {
            $at = $indexOf($field);
            if ($at < 0) {
                $missing[] = $field;
            } elseif ($at < $formIdx || $at > $naIdx) {
                $missing[] = $field.' sits outside the analysis box (paragraph '.$at.', the box is '.$formIdx.' to '.$naIdx.')';
            }
        }
        for ($q = 1; $q <= $wantByNumber[$n]['q']; $q++) {
            $at = $indexOf('Q'.$n.'.'.$q.' ');
            if ($at < 0) {
                $missing[] = 'Q'.$n.'.'.$q;
            } elseif ($at < $formIdx || $at > $naIdx) {
                $missing[] = 'Q'.$n.'.'.$q.' sits outside the analysis box';
            }
        }
        $wfIdx = $indexOf('Workflow difference');
        if ($wfIdx < 0) {
            $missing[] = 'Workflow difference';
        } elseif ($wfIdx <= $naIdx) {
            $missing[] = 'the N/A sentence sits in the analysis box instead of under its own label (paragraph '.$wfIdx.', label at '.$naIdx.')';
        }
    }

    // The submission carries the owner's plain typography: no em dash, no en dash, no arrow and no markdown marker
    // in any inserted paragraph. Paragraphs at or before the form's own applicability row are the school's text.
    foreach ($inWindow as $i => $lineText) {
        if ($i <= $formIdx) {
            continue;
        }
        if (preg_match('/[—–→]|\\*\\*|`/u', $lineText) === 1) {
            $missing[] = 'paragraph '.($i + 1).' of this window carries a dash, an arrow or a markdown marker on paper';
            break;
        }
    }

    foreach (['Concept involved:', 'Applicability:', 'Design:', 'Implementation:', 'Test or demonstration:', 'Evidence:', 'Workflow difference'] as $field) {
        if (! str_contains($bodyText, $field)) {
            $missing[] = $field;
        }
    }
    $qFound = preg_match_all('/Q'.$n.'\.\d+ /', $bodyText);
    if ($qFound !== $wantByNumber[$n]['q']) {
        $missing[] = 'only '.$qFound.' of '.$wantByNumber[$n]['q'].' guide questions';
    }
    // One problem states one verdict: the form row plus the answer's own restatement is two `Applicability:`
    // lines in a window, and a third means an answer was pasted into the wrong cell.
    $applicLines = substr_count($bodyText, 'Applicability:');
    if ($applicLines < 1 || $applicLines > 3) {
        $missing[] = $applicLines.' applicability lines in the window';
    }
    if (substr_count($bodyText, '☑') < 1) {
        $missing[] = 'no ticked box in the window';
    }
    if ($missing !== []) {
        $problems[] = 'P'.str_pad((string) $n, 2, '0', STR_PAD_LEFT).': missing '.implode(', ', $missing);
    }
}

// §IV concept rows. The shape check alone is not a verdict: a row can hold exactly three boxes and one ☑ while the
// ☑ sits on `Explained` and the school asked whether the concept is `Demonstrated in test`. The owner's complaint was
// about WHICH box, so each row is now paired BY CONCEPT NAME with the answer file's own row and the ticked labels are
// compared in both directions. A label the form does not offer on that row is not demanded of it — ACID has no
// `Implemented` box, and Recovery's evidence box is spelled `Backup/restore or recovery evidence`.
$normalizeLabel = static function (string $s): string {
    $s = trim(preg_replace('/\s+/', ' ', $s));
    // The paper writes `N/A (explain)` where the answer file writes `N/A`; the gloss is the form's own prompt.
    $s = (string) preg_replace('/\s*\([^)]*\)\s*$/', '', $s);

    return mb_strtolower($s);
};
$tickedIn = static function (string $line) use ($normalizeLabel): array {
    $out = [];
    if (preg_match_all('/☑\s*([^☐☑]+)/u', $line, $bm) >= 1) {
        foreach ($bm[1] as $lab) {
            $n = $normalizeLabel($lab);
            if ($n !== '') {
                $out[] = $n;
            }
        }
    }

    return $out;
};
$offeredIn = static function (string $line) use ($normalizeLabel): array {
    $out = [];
    if (preg_match_all('/[☐☑]\s*([^☐☑]+)/u', $line, $bm) >= 1) {
        foreach ($bm[1] as $lab) {
            $n = $normalizeLabel($lab);
            if ($n !== '') {
                $out[] = $n;
            }
        }
    }

    return $out;
};

$ivFrom = null;
$ivTo = null;
foreach ($paras as $i => $p) {
    if ($ivFrom === null && str_contains($p['text'], 'IV. Required Database Concepts')) {
        $ivFrom = $i;
    } elseif ($ivFrom !== null && $ivTo === null && str_contains($p['text'], 'V. Final Presentation')) {
        $ivTo = $i;
        break;
    }
}
if ($ivFrom === null || $ivTo === null) {
    fwrite(STDERR, "REFUSED: the copy's §IV window cannot be bounded, so no concept row can be paired by name.\n");
    exit(1);
}

// The answer file's rows: `**Row 3 — ACID Properties.** ☐ Implemented ☑ Explained …`
preg_match('/^\#\#\s*IV\..*?(?=\n\#\#\s*V\.)/ms', $md, $ivBlock);
$wantRows = [];
if (preg_match_all('/^\*\*Row\s+(\d+)\s+—\s+(.+?)\.?\*\*\s*(.*)$/m', $ivBlock[0] ?? '', $rm, PREG_SET_ORDER) >= 1) {
    foreach ($rm as $r) {
        $wantRows[$normalizeLabel($r[2])] = ['ticked' => $tickedIn($r[3]), 'line' => $r[3], 'n' => (int) $r[1]];
    }
}
if (count($wantRows) !== 12) {
    fwrite(STDERR, 'REFUSED: the answer file holds '.count($wantRows).' §IV rows, expected 12 — the population is not measured.\n');
    exit(1);
}

$conceptFail = [];
$paired = 0;
$agree = 0;
// `$optLine` and `$rowName`, never `$copyText`: line 61's `$copyText` is the DOCUMENT-WIDE string that the §IV and §VI
// sentinel checks read at the bottom of this file. Reusing that name for one row's option line silently redefined
// the sentinel subject to the last concept row, and the tool reported its own sentinels ABSENT while exiting on a
// refusal it had invented — the leak-a-loop-variable class this file has met in `$rel` and `$rows`.
for ($i = $ivFrom; $i < $ivTo; $i++) {
    $optLine = $paras[$i]['text'];
    if (substr_count($optLine, '☐') + substr_count($optLine, '☑') === 0) {
        continue;
    }
    // The row's own name is the paragraph before its option line: the table states `Transactions`, then the boxes.
    $rowName = $i > $ivFrom ? $normalizeLabel($paras[$i - 1]['text']) : '';
    $boxes = substr_count($optLine, '☐') + substr_count($optLine, '☑');
    $ticked = $tickedIn($optLine);
    if ($boxes !== 3 || $ticked === []) {
        $conceptFail[] = mb_substr($optLine, 0, 60)." (boxes {$boxes}, ticked ".count($ticked).')';

        continue;
    }
    if (! isset($wantRows[$rowName])) {
        $conceptFail[] = 'an option row whose name ['.mb_substr($rowName, 0, 40)."] is not one of the answer file's twelve";

        continue;
    }
    $paired++;
    $offered = $offeredIn($optLine);
    $missing = array_values(array_filter($wantRows[$rowName]['ticked'],
        static fn (string $t): bool => in_array($t, $offered, true) && ! in_array($t, $ticked, true)));
    $extra = array_values(array_diff($ticked, $wantRows[$rowName]['ticked']));
    $unofferable = array_values(array_filter($wantRows[$rowName]['ticked'],
        static fn (string $t): bool => ! in_array($t, $offered, true)));
    if ($missing !== [] || $extra !== []) {
        $conceptFail[] = $rowName.': the paper ticks ['.implode(', ', $ticked).'] while the answer file says ['.
            implode(', ', $wantRows[$rowName]['ticked']).']'.
            ($missing === [] ? '' : ' — MISSING on the paper: '.implode(', ', $missing)).
            ($extra === [] ? '' : ' — TICKED on the paper only: '.implode(', ', $extra));
    } else {
        $agree++;
        if ($unofferable !== []) {
            // Not a defect: the school's row holds no such box. Say which, so the agreement is read as bounded.
            echo '  note  '.$rowName.' — the answer file marks ['.implode(', ', $unofferable).
                '] but this row offers ['.implode(', ', $offered).']; nothing to tick there.'."\n";
        }
    }
}
if ($paired !== 12) {
    $conceptFail[] = 'only '.$paired.' of 12 concept rows were paired by name in the §IV window';
}
printf("§IV: %d rows paired by NAME against the answer file, ticked labels agree on %d\n", $paired, $agree);

// §V sub-items and §VII fill-ins. The §V answer count is taken INSIDE the part's own window: `Answer: ` also
// opens every guide-question answer in §III, and a document-wide count would compare 131 against 25 and call a
// correct copy a defect.
$vFrom = null;
$vTo = null;
foreach ($paras as $p) {
    if ($vFrom === null && str_starts_with($p['text'], 'V. Final Presentation Requirements')) {
        $vFrom = $p['start'];
    } elseif ($vFrom !== null && $vTo === null && str_starts_with($p['text'], 'VI. Final Defense')) {
        $vTo = $p['start'];
        break;
    }
}
if ($vFrom === null || $vTo === null) {
    fwrite(STDERR, "REFUSED: the copy's §V window cannot be bounded (V found: ".var_export($vFrom, true).', VI: '.var_export($vTo, true).').\n');
    exit(1);
}
$vBody = '';
foreach ($paras as $p) {
    if ($p['start'] > $vFrom && $p['start'] < $vTo) {
        $vBody .= $p['text']."\n";
    }
}
$vAnswerLines = substr_count($vBody, 'Answer: ');
$fillFail = [];
foreach (['Student 1', 'Student 2', 'Instructor', 'Date of Presentation'] as $label) {
    $line = null;
    foreach ($paras as $p) {
        if (str_starts_with($p['text'], $label.':')) {
            $line = $p['text'];
            break;
        }
    }
    if ($line === null || str_contains($line, '__')) {
        $fillFail[] = $label.($line === null ? ' (line not found)' : ' (still underscores)');
    }
}
$vSlice = preg_match('/\n## V\..*?(?=\n## VI\.)/s', $md, $v3) ? (string) $v3[0] : '';
$vLabels = preg_match_all('/^- \*[^*]+?:\*/sm', $vSlice, $lm);
if ($vSlice === '') {
    fwrite(STDERR, "REFUSED: {$answers} holds no `## V.` section to weigh the copy against.\n");
    exit(1);
}

printf("copy: %d paragraphs, %d problems bounded by their own form headings\n", count($paras), count($heads));
printf("§V: %d inserted `Answer:` lines in the copy against %d sub-item answers in the answer file\n", $vAnswerLines, $vLabels);
printf("concept rows failing the 3-box / at-least-one-☑ shape: %d\n", count($conceptFail));
foreach ($conceptFail as $c) {
    echo '  '.$c."\n";
}
printf("§VII fill-ins not carrying a value: %d%s\n", count($fillFail), $fillFail === [] ? '' : ' ('.implode(', ', $fillFail).')');
foreach (['Defense answer:', 'No row is N/A'] as $sentinel) {
    printf("sentinel %-16s: %s\n", $sentinel, str_contains($copyText, $sentinel) ? 'present' : 'ABSENT');
}
if ($problems !== []) {
    echo "\nwindow defects:\n";
    foreach ($problems as $p) {
        echo '  '.$p."\n";
    }
}
// Markdown emphasis must not reach the paper. The blank form holds no `*…*` and no backtick in any run (measured),
// so any marker found here came from the answer file's own markup — the writer strips it, and this is where a
// regression in that stripping becomes a refusal instead of a typographical error a panelist reads aloud.
$markdown = [];
foreach ($paras as $p) {
    if (preg_match('/\*[^*<]{1,80}\*/u', $p['text']) === 1 || str_contains($p['text'], '`')) {
        $markdown[] = mb_substr($p['text'], 0, 80);
    }
}
printf("paragraphs still carrying markdown emphasis or code spans: %d\n", count($markdown));
foreach (array_slice($markdown, 0, 5) as $m) {
    echo '  MARKDOWN: '.$m."\n";
}

$fail = $problems !== [] || $conceptFail !== [] || $fillFail !== [] || $markdown !== []
    || $vAnswerLines !== $vLabels
    || ! str_contains($copyText, 'Defense answer:') || ! str_contains($copyText, 'No row is N/A');
printf("\n%s: %d window defect(s) over 24 problems\n", $fail ? 'REFUSED' : 'VERIFIED', count($problems));
exit($fail ? 1 : 0);
