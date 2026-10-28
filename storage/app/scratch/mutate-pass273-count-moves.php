<?php

/**
 * Prove `pass273-count-moves.php` can fail.
 *
 * That tool exists because two totals written into AGENTS.md from memory were both wrong, and it answers "how many
 * citation numbers moved between these two copies of the document" by diffing the citation-token multiset. A counter
 * like that is only worth quoting if it can be shown to move: so each leg plants exactly one change in a throwaway
 * copy of the docx and requires exactly one token to fall and one to rise, and one leg proves the pair that changes
 * nothing reports nothing.
 *
 *   php storage/app/scratch/mutate-pass273-count-moves.php
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = escapeshellarg(PHP_BINARY);
$tool = $scratch.'/pass273-count-moves.php';
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$tmp = $scratch.'/tmp-pass273-count';
@mkdir($tmp);
$fails = 0;

$check = static function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

/** Read document.xml, change one citation, write a throwaway copy, and return its path. */
$copyWithChange = static function (string $from, string $to) use ($docx, $tmp): string {
    $z = new ZipArchive;
    if (! $z->open($docx)) {
        fwrite(STDERR, "REFUSED: cannot open the live docx.\n");

        exit(1);
    }
    $xml = (string) $z->getFromName('word/document.xml');
    $z->close();
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        // A mutant anchored on a token that occurs twice would move two and be read as a two-change defect; anchored
        // on one that occurs zero times it would change nothing and report the control's answer as a failure.
        fwrite(STDERR, "REFUSED: the mutant needle [$from] occurs {$n} time(s); this leg needs exactly 1.\n");

        exit(1);
    }
    if (substr_count($xml, $to) !== 0) {
        fwrite(STDERR, "REFUSED: the replacement [$to] already occurs, so the rose-count would be ambiguous.\n");

        exit(1);
    }
    $path = $tmp.'/changed-'.substr(md5($from.$to), 0, 8).'.docx';
    if (! copy($docx, $path)) {
        fwrite(STDERR, "REFUSED: could not copy the docx to {$path}.\n");

        exit(1);
    }
    $w = new ZipArchive;
    if (! $w->open($path, ZipArchive::CREATE)) {
        fwrite(STDERR, "REFUSED: could not reopen {$path} for writing.\n");

        exit(1);
    }
    $w->addFromString('word/document.xml', str_replace($from, $to, $xml));
    $w->close();

    return $path;
};

$run = static function (string $args) use ($php, $tool, $root): string {
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).$args.' 2>&1');
};
$read = static function (string $out): ?array {
    if (preg_match('/fell: (\d+) occurrence\(s\) over (\d+) distinct/u', $out, $a) !== 1
        || preg_match('/rose: (\d+) occurrence\(s\) over (\d+) distinct/u', $out, $b) !== 1) {
        return null;
    }

    return [(int) $a[1], (int) $a[2], (int) $b[1], (int) $b[2]];
};

if (! is_file($tool) || ! is_file($docx)) {
    exit("REFUSED: the counter or the document is missing, so nothing can be compared.\n");
}

// 1 — a pair of identical copies must report nothing. This is the control that tells "the document did not move" from
// "the tool parsed nothing", so it also asserts the population it read was non-zero.
$dup = $tmp.'/dup.docx';
if (! copy($docx, $dup)) {
    exit("REFUSED: could not build the identical-copy control.\n");
}
$out = $run(' '.escapeshellarg($docx).' '.escapeshellarg($dup));
$c = $read($out);
$check('a byte-identical pair moves nothing', $c !== null && $c[0] === 0 && $c[2] === 0,
    $c === null ? 'the run printed no fell/rose tally' : "fell {$c[0]}, rose {$c[2]}");
$check('and the control read a real population, not an empty one',
    preg_match('/BEFORE .*: \d+ occurrences over \d+ distinct tokens/u', $out) === 1
    && (int) preg_replace('/\D+/u', '', (string) preg_split('/\n/', $out)[0]) > 100,
    trim(explode("\n", $out)[0] ?? ''));

// 2 — re-point one citation: exactly one token falls and exactly one rises, and they are the two the leg planted.
$one = $copyWithChange('EnrollmentStateMachine.php:47', 'EnrollmentStateMachine.php:480');
$out = $run(' '.escapeshellarg($docx).' '.escapeshellarg($one));
$c = $read($out);
$check('one re-pointed citation moves exactly one token out and one in',
    $c !== null && $c[0] === 1 && $c[1] === 1 && $c[2] === 1 && $c[3] === 1,
    $c === null ? 'the run printed no tally' : "fell {$c[0]}/{$c[1]} distinct, rose {$c[2]}/{$c[3]} distinct");
$check('the fallen token is the old pointer', str_contains($out, '- EnrollmentStateMachine.php:47'), '');
$check('the risen token is the new one', str_contains($out, '+ EnrollmentStateMachine.php:480'), '');

// 3 — a citation that appears nowhere before must show as a rise with no fall.
$added = $copyWithChange('>EnrollmentStateMachine.php:47<', '>EnrollmentStateMachine.php:47, EnrollmentStateMachine.php:481<');
$out = $run(' '.escapeshellarg($docx).' '.escapeshellarg($added));
$c = $read($out);
$check('an added citation is only a rise',
    $c !== null && $c[0] === 0 && $c[2] === 1 && str_contains($out, '+ EnrollmentStateMachine.php:481'),
    $c === null ? 'the run printed no tally' : "fell {$c[0]}, rose {$c[2]}");

// 4 — one argument cannot be measured against: the tool needs two copies, and a refusal is its honest answer.
$out = $run(' '.escapeshellarg($docx));
$check('one path refuses rather than comparing the document to nothing', str_contains($out, 'REFUSED'),
    trim(explode("\n", $out)[0] ?? ''));

// 5 — a file that is not a docx must refuse, and must say what it read.
$fake = $tmp.'/not-a-docx.docx';
file_put_contents($fake, 'this is not a zip archive at all');
$out = $run(' '.escapeshellarg($docx).' '.escapeshellarg($fake));
$check('a file that is not a document refuses with its cause', str_contains($out, 'REFUSED'),
    trim(explode("\n", $out)[0] ?? ''));

// 6 — the population guard: a well-formed zip that is NOT this document answers a short document.xml, and a counter
// that reported "0 fell / 0 rose" over it would look exactly like a clean pair. It must refuse and say the byte count.
$tiny = $tmp.'/tiny.docx';
$w = new ZipArchive;
$w->open($tiny, ZipArchive::CREATE);
$w->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
$w->addFromString('word/document.xml', '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body/></w:document>');
$w->close();
$out = $run(' '.escapeshellarg($docx).' '.escapeshellarg($tiny));
$check('a zip that is not this document refuses, quoting the bytes it read',
    str_contains($out, 'REFUSED') && preg_match('/\d+ bytes/u', $out) === 1,
    trim(explode("\n", $out)[0] ?? ''));

array_map('unlink', glob($tmp.'/*'));
@rmdir($tmp);
printf("\n%d leg(s) failed.\n", $fails);
exit($fails > 0 ? 1 : 0);
