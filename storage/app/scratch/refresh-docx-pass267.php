<?php

/**
 * §7.1 gains the edge the resume act takes: `returnedToAdmission → pending`.
 *
 * The transition table already carries `paid → returnedToEvaluation` and `returnedToEvaluation → evaluated`,
 * but the Admission half of the return had no row, because until this batch nothing took that edge. The
 * call site is measured from the file rather than written from memory: the pass locates the branch inside
 * `approve()` and uses the lines it actually finds, refusing when they are not where the claim says.
 *
 * Cloned from the `returnedToEvaluation → evaluated` row so the table's own formatting travels, with every
 * run of every cell emptied first — a cloned multi-run cell otherwise keeps the template's tail.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass267.php [--apply]
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

$controller = explode("\n", (string) file_get_contents($root.'/app/Http/Controllers/Admission/AdmissionController.php'));

$open = null;
$branch = [];

foreach ($controller as $i => $text) {
    $n = $i + 1;

    if (str_contains($text, 'public function approve(')) {
        $open = $n;
    }

    // First occurrence of each, not the last: the create array further down also writes
    // `EnrollmentStatus::Pending,` and `max()` over a last-wins scan would stretch the call site from the
    // resume branch all the way into the create.
    if ($open !== null && str_contains($text, 'EnrollmentStatus::ReturnedToAdmission)') && ! isset($branch['if'])) {
        $branch['if'] = $n;
    }

    if ($open !== null && str_contains($text, 'EnrollmentStatus::Pending,') && ! isset($branch['to'])) {
        $branch['to'] = $n;
    }

    if ($open !== null && str_contains($text, "'Admission approved again after the record was returned") && ! isset($branch['remark'])) {
        $branch['remark'] = $n;
    }
}

if ($open === null || count($branch) !== 3) {
    fwrite(STDERR, 'REFUSED: the resume branch was not found as described — approve() at '.var_export($open, true).', parts found: '.count($branch).".\n");

    exit(1);
}

if ($branch['to'] > $open + 40) {
    fwrite(STDERR, "REFUSED: the resume branch's Pending line sits at {$branch['to']}, too far below approve() at {$open} to be the branch — the first-occurrence scan picked up the create array.\n");

    exit(1);
}

$from = (int) $branch['if'];
$to = max(array_values($branch));

foreach ([
    $from => 'EnrollmentStatus::ReturnedToAdmission)',
    $branch['to'] => 'EnrollmentStatus::Pending,',
    $branch['remark'] => 'Admission approved again after the record was returned',
] as $number => $mustContain) {
    if (! str_contains(trim($controller[$number - 1] ?? ''), $mustContain)) {
        fwrite(STDERR, "REFUSED: line {$number} does not contain {$mustContain}.\n");

        exit(1);
    }
}

printf("measured approve() opens at %d; the resume branch runs %d-%d\n", $open, $from, $to);

$zip = new ZipArchive;

if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}

$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' ', array_map($flatten, $cm[0]));
};

preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $xml, $rows, PREG_OFFSET_CAPTURE);

$template = null;

foreach ($rows[0] as [$row, $offset]) {
    $text = $rowText($row);

    if (strncmp($text, 'returnedToEvaluation evaluated', 27) === 0) {
        if ($template !== null) {
            fwrite(STDERR, "REFUSED: two §7.1 rows open with the resumption prefix.\n");

            exit(1);
        }

        $template = ['row' => $row, 'offset' => (int) $offset, 'text' => $text];
    }

    if (strncmp($text, 'returnedToAdmission pending', 25) === 0) {
        fwrite(STDERR, "REFUSED: §7.1 already carries the returnedToAdmission → pending row.\n");

        exit(1);
    }
}

if ($template === null) {
    fwrite(STDERR, "REFUSED: no §7.1 row opens with `returnedToEvaluation evaluated`.\n");

    exit(1);
}

printf("template §7.1 row: %d bytes, prefix \"%s\"\n", strlen($template['row']), substr($template['text'], 0, 60));

$values = [
    'returnedToAdmission',
    'pending',
    'Admissions Office',
    'AdmissionController.php:'.$from.'-'.$to,
    'The record a return left at Admission comes back the way it went out: the desk approves the reopened application, and the approval resumes the seat-holder it finds rather than '
        .'leaving it parked. This is the only act that takes the edge, and the row it writes is the state trail §7.1 promises for every transition.',
];

preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template['row'], $cm, PREG_OFFSET_CAPTURE);

if (count($cm[0]) !== count($values)) {
    fwrite(STDERR, 'REFUSED: the template row has '.count($cm[0]).' cells, this row writes '.count($values).".\n");

    exit(1);
}

$clone = $template['row'];
$pairs = [];

foreach ($cm[0] as $j => $cell) {
    $pairs[] = [$j, (int) $cell[1], strlen($cell[0]), $cell[0]];
}

usort($pairs, static fn (array $a, array $b): int => $b[1] <=> $a[1]);

foreach ($pairs as [$j, $at, $len, $src]) {
    $cell = $src;
    preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm);

    foreach (array_slice($pm[0], 1) as $extra) {
        if ($flatten($extra) !== '') {
            fwrite(STDERR, "REFUSED: cell {$j} of the template row holds a second paragraph with text.\n");

            exit(1);
        }

        $cell = str_replace($extra, '', $cell);
    }

    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$escape($values[$j]).'</w:t></w:r></w:p>', $body);

    if (substr_count($new, '<w:t xml:space="preserve">') !== 1) {
        fwrite(STDERR, "REFUSED: cell {$j} of the clone holds more than one text run.\n");

        exit(1);
    }

    $clone = substr_replace($clone, $new, $at, $len);
}

$written = [];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $clone, $check);

foreach ($check[0] as $j => $cell) {
    $written[] = $flatten($cell);
}

if ($written !== $values) {
    fwrite(STDERR, "REFUSED: a cloned cell is not the text supplied: ".$written[3].' vs '.$values[3].".\n");

    exit(1);
}

printf("clone  %d bytes, %d cells verified by flattened equality, call site %s\n", strlen($clone), count($written), $values[3]);

$updated = substr_replace($xml, $clone, $template['offset'] + strlen($template['row']), 0);

foreach ([['<w:tc>', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl>', '</w:tbl>']] as [$open2, $close]) {
    if (substr_count($updated, $open2) !== substr_count($updated, $close)) {
        fwrite(STDERR, "REFUSED: the edited document is unbalanced in {$open2}.\n");

        exit(1);
    }
}

if (substr_count($updated, '<w:tr') !== substr_count($xml, '<w:tr') + 1) {
    fwrite(STDERR, "REFUSED: the document did not gain exactly one row.\n");

    exit(1);
}

if (simplexml_load_string($updated) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

printf("%s§7.1 would gain the returnedToAdmission → pending edge\n", $apply ? '' : 'DRY RUN — ');

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass267-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup.\n");

    exit(1);
}

$zip = new ZipArchive;

if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing.\n");

    exit(1);
}

$zip->addFromString('word/document.xml', $updated);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $updated) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 267 applied: §7.1 now shows both halves of the return, out to Admission and back through approval.\n";
