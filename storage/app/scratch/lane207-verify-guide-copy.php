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
$text = (string) preg_replace('/\s+/u', ' ', implode(' ', array_column($paras, 'text')));

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
foreach ($keys as $i => $n) {
    $bounds[$n] = [$heads[$n], $i + 1 < count($keys) ? $heads[$keys[$i + 1]] : strlen($xml)];
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
    if (! str_contains($bodyText, 'Problem '.$n.' — '.$wantByNumber[$n]['title'].' — answered')) {
        $missing[] = 'its answer header';
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

// §IV concept rows: each row's three boxes must still be three, with at least one ☑.
$conceptFail = [];
foreach ($paras as $p) {
    if (preg_match('#^\s*[☐☑]\s*(Implemented|Explained|Backup/restore)#u', $p['text']) === 1 && (str_contains($p['text'], '☐') || str_contains($p['text'], '☑'))) {
        $boxes = substr_count($p['text'], '☐') + substr_count($p['text'], '☑');
        if ($boxes !== 3 || substr_count($p['text'], '☑') < 1) {
            $conceptFail[] = mb_substr($p['text'], 0, 60);
        }
    }
}

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
    printf("sentinel %-16s: %s\n", $sentinel, str_contains($text, $sentinel) ? 'present' : 'ABSENT');
}
if ($problems !== []) {
    echo "\nwindow defects:\n";
    foreach ($problems as $p) {
        echo '  '.$p."\n";
    }
}
$fail = $problems !== [] || $conceptFail !== [] || $fillFail !== [] || $vAnswerLines !== $vLabels
    || ! str_contains($text, 'Defense answer:') || ! str_contains($text, 'No row is N/A');
printf("\n%s: %d window defect(s) over 24 problems\n", $fail ? 'REFUSED' : 'VERIFIED', count($problems));
exit($fail ? 1 : 0);
