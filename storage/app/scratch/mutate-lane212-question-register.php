<?php

/**
 * Falsifier for `lane212-weigh-question-register.php`, the instrument that weighs §29.4's count of the
 * school-question register against the rows under that heading.
 *
 * Every leg plants one defect the checker exists to notice, and each asserts the checker's OWN exit code through
 * exec() rather than a pipe's — a refusal read through `| head` belongs to `head`. Two directions are covered on
 * purpose: a cell that stopped growing when the table did, and a table that lost a row while its cell stayed. The
 * refusals are told apart by their messages, because several of them exit 1.
 *
 * Copies are built by rewriting `word/document.xml` inside a throwaway docx in the scratch directory, and every copy
 * is deleted at the end and the deletion verified by listing the directory — a harness that leaves mutant documents
 * behind gives a later run a second definition of the register.
 *
 * Usage: php storage/app/scratch/mutate-lane212-question-register.php
 */
$root = dirname(__DIR__, 3);
chdir($root);
$php = PHP_BINARY;

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$tool = 'storage/app/scratch/lane212-weigh-question-register.php';
$dir = $root.'/storage/app/scratch';

if (! is_file($docx) || ! is_file($tool)) {
    fwrite(STDERR, "REFUSED: the docx or the checker is missing from the tree.\n");
    exit(1);
}

$copies = [];
$cleanup = static function () use (&$copies): void {
    foreach ($copies as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
};
register_shutdown_function($cleanup);

$bad = 0;
$legs = 0;

$run = static function (string $args = '') use ($php, $tool): array {
    $out = [];
    exec('"'.$php.'" '.$tool.' '.$args.' 2>&1', $out, $code);

    return [(int) $code, implode("\n", $out)];
};

/** Copy the docx and rewrite one exact substring of document.xml. Refuses a needle that is not unique. */
$patch = static function (string $name, string $from, string $to, int $expect = 1) use ($docx, $dir, &$copies): array {
    $path = $dir.'/'.$name;
    if (! copy($docx, $path)) {
        return [false, 'the copy could not be written.'];
    }
    $copies[] = $path;
    if ($from === '' && $to === '') {
        return [$path, ''];
    }
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        return [false, 'the copy did not open.'];
    }
    $xml = (string) $zip->getFromName('word/document.xml');
    $hits = substr_count($xml, $from);
    if ($hits !== $expect) {
        $zip->close();

        return [false, "plant needle occurs {$hits} time(s), expected {$expect}"];
    }
    $xml = $expect === 1 ? substr_replace($xml, $to, strpos($xml, $from), strlen($from)) : str_replace($from, $to, $xml);
    $zip->addFromString('word/document.xml', $xml);
    $zip->close();

    return [$path, ''];
};

$verdict = static function (string $name, array $res, int $wantCode, string $wantNeedle) use (&$bad, &$legs): void {
    $legs++;
    [$code, $out] = $res;
    if ($code !== $wantCode || ($wantNeedle !== '' && ! str_contains($out, $wantNeedle))) {
        echo str_pad($name, 30)."BAD   exit={$code} wanted {$wantCode}; message lacked [{$wantNeedle}]\n";
        $bad++;

        return;
    }
    echo str_pad($name, 30)."ok    exit={$code} ".($wantNeedle === '' ? 'as expected' : "refused as required: {$wantNeedle}")."\n";
};

// ------------------------------------------------------------------ the control, and a byte-identical copy
$verdict('control-agrees', $run(), 0, 'AGREES');

$clean = $patch('lane212-clean.docx', '<w:body>', '<w:body>');
if ($clean[0] === false) {
    echo str_pad('byte-identical', 30).'BAD   '.$clean[1]."\n";
    $bad++;
    $legs++;
} else {
    $verdict('byte-identical', $run('--docx='.escapeshellarg($clean[0])), 0, 'AGREES');
}

// --- the cell stopped growing while the table did: change the stated number, and the disagreement must be named.
$c1 = $patch('lane212-stale28.docx', '>28<', '>27<');
$verdict('cell-understates', $c1[0] === false ? [1, $c1[1]] : $run('--docx='.escapeshellarg($c1[0])), 1, 'DISAGREE by 1');

// --- the opposite direction: drop a question row so the table shrinks under its own count.
$table = $patch('lane212-row.docx', '', '');
if ($table[0] === false) {
    echo str_pad('row-plant-prep', 30).'BAD   '.$table[1]."\n";
    $bad++;
    $legs++;
} else {
    $zip = new ZipArchive;
    $zip->open($table[0]);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    $headingPos = strpos($xml, 'Consolidated list of decisions to put to the school');
    $tblStart = strpos($xml, '<w:tbl>', $headingPos);
    $tblEnd = strpos($xml, '</w:tbl>', $tblStart);
    $body = substr($xml, $tblStart, $tblEnd - $tblStart);
    preg_match_all('/<w:tr\b.*?<\/w:tr>/s', $body, $rm);
    $lastRow = end($rm[0]);
    $zip = new ZipArchive;
    $zip->open($table[0]);
    $zip->addFromString('word/document.xml', str_replace($lastRow, '', $xml));
    $zip->close();
    $verdict('table-shrank-under-count', $run('--docx='.escapeshellarg($table[0])), 1, 'DISAGREE by 1');
}

// --- a reworded label: the row can no longer be found, which is a refusal, never a clean count.
$lbl = $patch('lane212-label.docx', '>Open decisions<', '>Open items<');
$verdict('label-reworded', $lbl[0] === false ? [1, $lbl[1]] : $run('--docx='.escapeshellarg($lbl[0])), 1, 'as its whole first cell');

// --- the count cell no longer holds a number.
$word = $patch('lane212-notanumber.docx', '>28<', '>twenty-eight<');
$verdict('cell-not-a-number', $word[0] === false ? [1, $word[1]] : $run('--docx='.escapeshellarg($word[0])), 1, 'which is not a number');

// --- the heading the table is located by is gone.
$head = $patch('lane212-head.docx', 'Consolidated list of decisions to put to the school', 'Consolidated list of decisions for the school');
$verdict('heading-missing', $head[0] === false ? [1, $head[1]] : $run('--docx='.escapeshellarg($head[0])), 1, "heading text was not found");

// --- a file that is not a docx, and a path that is not there, and a flag that is not read.
file_put_contents($dir.'/lane212-notadocx.docx', 'this is a text file wearing a .docx name');
$copies[] = $dir.'/lane212-notadocx.docx';
$verdict('not-a-docx', $run('--docx='.escapeshellarg($dir.'/lane212-notadocx.docx')), 1, 'did not open as an archive');
$verdict('missing-path', $run('--docx='.escapeshellarg($dir.'/lane212-no-such-file.docx')), 1, 'is not a file');
$verdict('unknown-flag', $run('--no-such-flag-at-all=1'), 1, 'is not a flag this tool reads');
$verdict('empty-flag-value', $run('--docx='), 1, 'needs a value');

printf("\n%s: %d leg(s), %d bad\n", $bad === 0 ? 'ALL PROVABLE' : 'NOT ALL PROVABLE', $legs, $bad);
$cleanup();
$left = glob($dir.'/lane212-*.docx');
if ($left !== []) {
    echo 'PLANT COPIES LEFT IN THE TREE: '.implode(', ', array_map('basename', $left))."\n";
    exit(1);
}
exit($bad === 0 ? 0 : 1);
