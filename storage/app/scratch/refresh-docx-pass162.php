<?php

/**
 * Pass 162 — re-point the citations into `AuditRecordStates.php`, which the new rule moved, and date one count that
 * pass 160 inherited.
 *
 * Pass 161 rewrote the sentences around these pointers but not the numbers: the batch added an import above every
 * method in the command, so `handle()` slid from 46 to 47 and the line sweep caught it sitting on a blank line.
 * Each pointer is therefore re-taken from the construct its sentence names — the braces measured in HEAD's copy,
 * then the braces measured in the working file — because the byte-identity shift cannot answer for a range this
 * batch wrote inside of. And G-24's dated sentence still said "sixteen rules", which is what pass 160's global
 * fourteen-to-sixteen rewrite made true *today* and false *on the date that row was measured*.
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

$headPath = $root.'/storage/app/scratch/lane56-head-AuditRecordStates.php';
$head = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) @file($headPath));
$now = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/app/Console/Commands/AuditRecordStates.php'));
if (count($head) === 0) {
    echo "REFUSED: the HEAD export is missing — take it with\n"
        ."  git show HEAD:app/Console/Commands/AuditRecordStates.php > storage/app/scratch/lane56-head-AuditRecordStates.php\n";
    exit(1);
}
if (implode("\n", $head) === implode("\n", $now)) {
    echo "REFUSED: HEAD's copy is byte-identical to the working file, so there is nothing to measure.\n";
    exit(1);
}
printf("AuditRecordStates.php: HEAD %d lines -> working %d lines\n", count($head), count($now));

$shiftOf = function (int $from, int $to) use ($head, $now, $guard): string {
    $ok = [];
    for ($s = -400; $s <= 400; $s++) {
        $good = 0;
        for ($i = $from; $i <= $to; $i++) {
            $j = $i - 1 + $s;
            if (isset($now[$j]) && $now[$j] === ($head[$i - 1] ?? '__missing__')) {
                $good++;
            }
        }
        if ($good === $to - $from + 1) {
            $ok[] = $s;
        }
    }
    if (count($ok) !== 1) {
        $guard(false, "the cited range :{$from}-{$to} resolves to exactly one shift (".count($ok).' found)');

        return $from.'-'.$to;
    }
    printf("  ok   :%d-%d is moved to :%d-%d (%+d)\n", $from, $to, $from + $ok[0], $to + $ok[0], $ok[0]);

    return ($from + $ok[0]).'-'.($to + $ok[0]);
};

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$partCount = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

// Each pointer names a construct, so it is re-taken from that construct's own braces in both copies: the check is
// that the number the document carries is what HEAD's file actually spanned, and the number written in is what the
// working file spans now. A byte-shift cannot answer for `handle()` — this batch wrote five lines inside it — and
// arithmetic across an edited range is what lane 55 refused to trust.
$spanIn = function (array $lines, string $name): string {
    foreach ($lines as $i => $line) {
        if (preg_match('/^\s*(?:public|private)?\s*(?:static\s+)?function\s+'.preg_quote($name, '/').'\(/', $line) === 1) {
            $depth = 0;
            $opened = false;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    return ($i + 1).'-'.($j + 1);
                }
            }
        }
    }

    throw new RuntimeException("{$name} not found");
};
$pair = fn (array $lines): string => (int) explode('-', $spanIn($lines, 'clinicBoxWithoutRecord'))[0]
    .'-'.(int) explode('-', $spanIn($lines, 'idBoxWithoutValidatedRequest'))[1];

$targets = [
    ['AuditRecordStates.php:46-177', $spanIn($now, 'handle'), false, fn (array $l): string => $spanIn($l, 'handle')],
    [':289-311', $pair($now), true, $pair],
    ['AuditRecordStates.php:319-324', $spanIn($now, 'sheetWithoutLines'), false,
        fn (array $l): string => $spanIn($l, 'sheetWithoutLines')],
    ['AuditRecordStates.php:334-343', $spanIn($now, 'receiptWithoutPaidStep'), false,
        fn (array $l): string => $spanIn($l, 'receiptWithoutPaidStep')],
];
foreach ($targets as [$token, $moved, $bare, $measure]) {
    $n = substr_count($new, $token);
    $guard($n === 1, sprintf('pointer present once (read %d): %s', $n, $token));
    $old = explode(':', rtrim($token, '+'))[1] ?? null;
    $inHead = $measure($head);
    $guard($old === $inHead, sprintf('%s :%s is what HEAD spanned (%s)', $token, (string) $old, $inHead));
    printf("  ok   %-32s HEAD :%-9s -> working :%s\n", $token, $inHead, $moved);
    if ($n === 1) {
        $new = str_replace($token, ($bare ? ':' : 'AuditRecordStates.php:').$moved, $new);
    }
}

// One count pass 160 inherited. Its global fourteen -> sixteen rewrite was right about the command and wrong
// inside G-24, whose sentence describes what the audit read on 2026-10-18 — a date on which it ran fourteen.
$g24Old = '`ems:audit:record-states` ran sixteen rules over these tables and read 0 on every one of them,';
$g24New = '`ems:audit:record-states` ran fourteen rules over these tables on that date and read 0 on every one of them,';
$guard(substr_count($new, $g24Old) === 1, 'G-24’s dated count is present once');
$new = substr_count($new, $g24Old) === 1 ? str_replace($g24Old, $g24New, $new) : $new;

$guard(substr_count($new, ':46-177') === 0, 'the pre-batch handle() span is gone');
$guard(substr_count($new, ':289-311') === 0 && substr_count($new, ':319-324') === 0
    && substr_count($new, ':334-343') === 0, 'no pre-batch audit span survives');
$guard($new !== $xml, 'the bytes were rewritten ('.(strlen($new) - strlen($xml)).' length change)');

if ($fails > 0) {
    printf("\n%d guard(s) failed — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN. Re-run with --apply.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/ems-docx-pre-pass162.docx';
copy($src, $backup);
$out = $root.'/Documentation/.ems-docx-pass162.tmp';
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

printf("\nPass 162 written; backup at %s\n", $backup);
exit(0);
