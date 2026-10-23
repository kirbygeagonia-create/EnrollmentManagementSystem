<?php

/**
 * Lane 65's mutant harness — prove the peso checker refuses a fee stated at the wrong amount, and prove
 * it is the copy being judged.
 *
 * Each case plants its defect in a COPY of the flat dump (the document is never touched), refuses the run
 * when its needle does not occur exactly once, and requires the checker to exit 1 naming the fee. A case
 * whose mutation leaves the file byte-identical reports BAD rather than passing — an anchor that matches
 * nothing makes a mutant run look like a clean one.
 *
 *   php storage/app/scratch/mutate-lane65-peso-claims.php
 */
$root = dirname(__DIR__, 3);
$cands = array_filter([(string) getenv('EMS_PHP'), 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe'], 'is_file');
$php = $cands === [] ? 'php' : array_shift($cands);
$checker = $root.'/storage/app/scratch/lane65-peso-claims.php';
$real = $root.'/storage/app/scratch/docx-flat.txt';
$tmp = $root.'/storage/app/scratch/lane65-mutant-dump.txt';
if (! is_file($checker) || ! is_file($real)) {
    exit("REFUSED: checker or dump missing\n");
}
$pristine = (string) file_get_contents($real);
$bad = 0;

$run = function (string $file): array {
    global $php, $checker;
    $lines = [];
    $code = 99;
    exec(escapeshellarg($php).' '.escapeshellarg($checker).' --verify --dump='.escapeshellarg($file).' 2>&1', $lines, $code);

    return [(int) $code, implode("\n", $lines)];
};
$say = function (string $label, bool $ok, string $detail) use (&$bad): void {
    printf("  %s %s%s\n", $ok ? 'ok  ' : 'FAIL', $label, $detail === '' ? '' : ' — '.$detail);
    if (! $ok) {
        $bad++;
    }
};

file_put_contents($tmp, $pristine);
[$code, $out] = $run($tmp);
preg_match('/bound (\d+) \+ history/', $out, $m);
$bound = (int) ($m[1] ?? 0);
$say('control: the document as it stands passes --verify', $code === 0, "exit {$code}, {$bound} claims bound");
if ($code !== 0) {
    echo "\nREFUSED: a mutant run on a red control measures nothing.\n";
    exit(1);
}
$say('the control bound a non-zero number of claims', $bound > 0, "bound={$bound}");

$noVerify = (string) shell_exec(escapeshellarg($php).' '.escapeshellarg($checker).' --dump='.escapeshellarg($tmp).' 2>&1');
$say('--dump= without --verify refuses', str_contains($noVerify, 'REFUSE: --dump= is only meaningful'), trim(substr($noVerify, 0, 70)));

$cases = [
    ['Laboratory quoted at the wrong amount', 'Laboratory ₱500/unit', 'Laboratory ₱600/unit', 'Laboratory'],
    ['Library quoted at the wrong amount', 'Library ₱250 flat', 'Library ₱251 flat', 'Library'],
    ['Miscellaneous quoted at the wrong amount', 'Miscellaneous ₱1,500 flat', 'Miscellaneous ₱1,400 flat', 'Miscellaneous'],
    // The triple rule earns its keep here: ₱12,000.00 IS a receipt amount the server holds, so a checker
    // that only asks "does this figure exist?" would pass this sentence. Only the arithmetic refuses it.
    ['an account sentence that no longer adds up', 'sheet leaves ₱10,500.00', 'sheet leaves ₱12,000.00', 'does not add up'],
];

foreach ($cases as [$label, $from, $to, $wantName]) {
    $n = substr_count($pristine, $from);
    if ($n !== 1) {
        $say($label, false, "BAD ANCHOR: needle occurs {$n} time(s), so this case would judge an unmutated copy");

        continue;
    }
    $mutated = str_replace($from, $to, $pristine);
    if ($mutated === $pristine) {
        $say($label, false, 'BAD: the mutation changed no bytes');

        continue;
    }
    file_put_contents($tmp, $mutated);
    [$code, $out] = $run($tmp);
    $hit = str_contains($out, $wantName) && str_contains($out, 'REFUSED');
    $line = '';
    foreach (explode("\n", $out) as $l) {
        if (str_contains($l, '* '.$wantName) || str_contains($l, $wantName.' " with')) {
            $line = trim(mb_substr($l, 0, 140));
            break;
        }
    }
    $say($label, $code === 1 && $hit, 'exit '.$code.'; '.($line !== '' ? $line : 'complaint: '.trim(mb_substr((string) strstr($out, 'VERDICT'), 0, 120))));
}

@unlink($tmp);
echo "\n".($bad === 0
    ? "All lane 65 cases behaved: green on the document, red on a fee stated at the wrong amount, and the copy is what gets judged.\n"
    : "{$bad} case(s) failed — the checker or this harness is the defect.\n");
exit($bad === 0 ? 0 : 1);
