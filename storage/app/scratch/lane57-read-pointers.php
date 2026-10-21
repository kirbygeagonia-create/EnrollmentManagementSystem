<?php
/**
 * Lane 57 — read the four pointers a uniform shift cannot answer for, against the bytes they name.
 *
 * A range that straddles this batch's insert cannot move by one number: its start stays and its end travels. So each
 * is tested directly — does HEAD's last cited line equal the working line the arithmetic proposes? — and the single
 * pointers are re-taken from the construct the sentence names, not from an offset.
 */
$root = dirname(__DIR__, 3);
$path = $root.'/app/Http/Controllers/Accounting/AccountingController.php';
$headPath = $root.'/storage/app/scratch/lane57-head-AccountingController.php';

$head = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($headPath));
$now = array_map(fn (string $l): string => rtrim($l, "\r\n"), (array) file($path));

$spanIn = function (array $lines, string $name): ?string {
    foreach ($lines as $i => $line) {
        if (preg_match('/(public|private|protected)?\s*function\s+'.preg_quote($name, '/').'\(/', $line) === 1) {
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

    return null;
};

printf("record(): HEAD %s -> working %s\n", (string) $spanIn($head, 'record'), (string) $spanIn($now, 'record'));

foreach ([[176, 209], [180, 207]] as [$from, $to]) {
    $sameStart = $head[$from - 1] === ($now[$from - 1] ?? '__gone__');
    $endAt16 = $head[$to - 1] === ($now[$to - 1 + 16] ?? '__gone__');
    printf("  cited :%d-%d  start stays put: %s   HEAD's line %d equals working %d: %s\n",
        $from, $to, $sameStart ? 'yes' : 'NO', $to, $to + 16, $endAt16 ? 'yes' : 'NO');
    printf("     HEAD :%d = %s\n", $to, trim($head[$to - 1]));
    printf("     work :%d = %s\n", $to + 16, trim($now[$to - 1 + 16] ?? '__gone__'));
}

// "The Accounting box is signed … at :227 when a payment settles the account, and again at :274 when …"
foreach (['working' => $now, 'HEAD' => $head] as $label => $lines) {
    $hits = [];
    foreach ($lines as $i => $line) {
        if (str_contains($line, 'signStepByOffice')) {
            $hits[] = (string) ($i + 1);
        }
    }
    printf("  signStepByOffice lines in %s: %s\n", $label, implode(', ', $hits));
}

// The rule-count sentences the pass has to move, and the pointer into the audit's handle().
$auditNow = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/app/Console/Commands/AuditRecordStates.php'));
$auditHead = array_map(fn (string $l): string => rtrim($l, "\r\n"),
    (array) file($root.'/storage/app/scratch/lane57-head-AuditRecordStates.php'));
printf("handle(): HEAD %s -> working %s\n", (string) $spanIn($auditHead, 'handle'), (string) $spanIn($auditNow, 'handle'));

$pairIn = function (array $lines): string {
    $start = null;
    $end = null;
    foreach ($lines as $i => $line) {
        if (preg_match('/function\s+clinicBoxWithoutRecord\(/', $line) === 1) {
            $start = $i + 1;
        }
        if (preg_match('/function\s+idBoxWithoutValidatedRequest\(/', $line) === 1) {
            $depth = 0;
            $opened = false;
            for ($j = $i; $j < count($lines); $j++) {
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth > 0) {
                    $opened = true;
                }
                if ($opened && $depth === 0) {
                    $end = $j + 1;
                    break;
                }
            }
        }
    }

    return $start.'-'.$end;
};
printf("the two box rules: HEAD %s -> working %s\n", $pairIn($auditHead), $pairIn($auditNow));
