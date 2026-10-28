<?php

/**
 * Prove `lane70-read-unread.php` can fail.
 *
 * The reader is what turns the anchor checker's UNBOUND queue into rows a person can open: for each unread key it
 * prints the document's own sentence, the file the sentence belongs to, the enclosing function, and the numbered
 * span. Its first run this session reported eight keys as NOT FOUND because it resolved only basenames, while the
 * document spells some keys with a path — an unresolved key is the reader's age, not a missing pointer, and a
 * reader sent after a phantom pointer wastes the pass. So every guard it carries now gets a leg here, including the
 * `--reads=` scope that lets a leg change the record set without touching the tracked file.
 *
 *   php storage/app/scratch/mutate-lane70-read-unread.php
 */

$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$php = escapeshellarg(PHP_BINARY);
$tool = $scratch.'/lane70-read-unread.php';
$reads = $scratch.'/lane70-queue-reads.json';
$tmp = $scratch.'/tmp-lane70-read-unread';
@mkdir($tmp);
$fails = 0;

$run = static function (string $args) use ($php, $tool, $root): string {
    // shell_exec gives no exit status to read on cmd.exe, so every leg asserts on the message instead: the refusals
    // are told apart by the path or flag each one names, not by a status code two of them already share.
    return (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($tool).$args.' 2>&1');
};
$check = static function (string $name, bool $ok, string $detail = '') use (&$fails): void {
    printf("  %s %s%s\n", $ok ? 'PASS' : 'BAD ', $name, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $fails++;
    }
};

if (! is_file($tool) || ! is_file($reads)) {
    exit("REFUSED: the reader or its record file is missing, so nothing here can be compared.\n");
}

$tally = static function (string $out): ?array {
    return preg_match('/(\d+) current key\(s\), (\d+) record\(s\) on file, (\d+) to read/u', $out, $m) === 1
        ? [(int) $m[1], (int) $m[2], (int) $m[3]] : null;
};

// 1 — the control: the queue is drained, so nothing is left to read and nothing is unresolved.
$base = $run('');
$b = $tally($base);
if ($b === null) {
    exit("REFUSED: the control run printed no tally line, so the harness has nothing to compare against.\n");
}
$check('control: every current key carries a record', $b[2] === 0 && $b[0] > 0, "keys {$b[0]}, to read {$b[2]}");
$check('control: no key was left unresolved', ! preg_match('/NOT FOUND|UNRESOLVED/u', $base), '');

// 2 — an empty record set must move the queue to its full size, which is what proves `--reads=` is read and not
// merely accepted. An accepted-and-inert flag is the defect this repository has now removed in five tools.
$j = json_decode((string) file_get_contents($reads), true);
$empty = $tmp.'/empty-reads.json';
file_put_contents($empty, json_encode(['reads' => new stdClass]));
$outEmpty = $run(' --reads='.escapeshellarg($empty));
$e = $tally($outEmpty);
$check('an empty record set makes every key unread', $e !== null && $e[2] === $e[0] && $e[0] === $b[0] && $e[2] > 0,
    $e === null ? 'the run printed no tally line' : "keys {$e[0]}, to read {$e[2]}");

// 3 — dropping exactly one record must raise the queue by exactly one, so the diff is per key and not per file.
$one = $tmp.'/minus-one.json';
$cut = $j;
$firstKey = array_key_first($cut['reads']);
unset($cut['reads'][$firstKey]);
file_put_contents($one, json_encode($cut));
$outOne = $run(' --reads='.escapeshellarg($one));
$o = $tally($outOne);
$check('removing one record makes exactly one key unread', $o !== null && $o[2] === 1,
    $o === null ? 'the run printed no tally line' : "to read {$o[2]} (dropped {$firstKey})");
$check('and the queue names the key that was dropped', str_contains($outOne, (string) $firstKey), '');

// 4 — a path that does not exist must refuse. Reading it as "no records" would print the whole queue as unread and
// read as a finding about the document rather than about the harness.
$outMissing = $run(' --reads='.escapeshellarg($tmp.'/definitely-not-here.json'));
$check('a missing --reads= path refuses, naming the path',
    str_contains($outMissing, 'REFUSED') && str_contains($outMissing, 'definitely-not-here.json'),
    trim(explode("\n", $outMissing)[0] ?? ''));

// 5 — a file that parses as JSON but holds no record object must refuse too: "0 record(s) on file" is what a broken
// reader says when it read nothing, and it looks exactly like a clean queue.
$broken = $tmp.'/broken-reads.json';
file_put_contents($broken, '{"note":"no reads key here"}');
$outBroken = $run(' --reads='.escapeshellarg($broken));
$check('a reads file with no "reads" object refuses rather than reporting the queue empty',
    str_contains($outBroken, 'REFUSED'), trim(explode("\n", $outBroken)[0] ?? ''));

// 6 — an argument the reader does not read is refused by name, so a leg cannot silently pass the wrong flag.
$outFlag = $run(' --show-keyset=5');
$check('an unknown flag is refused by name', str_contains($outFlag, 'REFUSED') && str_contains($outFlag, '--show-keyset=5'),
    trim(explode("\n", $outFlag)[0] ?? ''));

// 7 — the locator bug this session actually met: a key spelled with its directory must resolve to that file. Every
// key is printed with its FILE line, so the full-queue run is the population to check against.
$pathKey = null;
foreach (array_keys($j['reads']) as $k) {
    $head = (string) preg_replace('/#[0-9a-f]{8}$/', '', (string) $k);
    if (str_contains($head, '/')) {
        $pathKey = $head;

        break;
    }
}
$check('at least one current key is spelled with a path', $pathKey !== null, (string) $pathKey);
if ($pathKey !== null) {
    // The point of the fix: a key that carries its directory must resolve through that path. The reader prints the
    // file it opened as `  FILE    <path> (<n> lines, …)`, so the positive check is on that line and the negative
    // check is that no key was left unresolved at all.
    $filePart = (string) preg_replace('/:\d.*/su', '', $pathKey);
    $outAll = $run(' --reads='.escapeshellarg($empty));
    $check('the path-spelled key resolves to the file it names', str_contains($outAll, $filePart.' ('), $filePart);
    $check('and no key in the full queue is left unresolved', ! str_contains($outAll, 'NOT FOUND'),
        'a NOT FOUND line would mean the locator guessed');
}

// 8 — a byte-identical copy of the tool must answer like the control, or the harness is measuring where the copy
// sits rather than what the code does.
$copy = $scratch.'/tmp-lane70-read-unread-copy.php';
file_put_contents($copy, (string) file_get_contents($tool));
$outCopy = (string) shell_exec('cd '.escapeshellarg($root).' && '.$php.' '.escapeshellarg($copy).' 2>&1');
@unlink($copy);
$c = $tally($outCopy);
$check('a byte-identical copy answers like the control', $c === $b, $c === null ? 'the copy printed no tally' : implode('/', $c));

array_map('unlink', glob($tmp.'/*'));
@rmdir($tmp);
printf("\n%d leg(s) failed.\n", $fails);
exit($fails > 0 ? 1 : 0);
