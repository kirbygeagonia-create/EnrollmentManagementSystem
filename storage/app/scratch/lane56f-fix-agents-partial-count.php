<?php

/**
 * Lane 56f — AGENTS.md stated a dataset count that is not what a fresh build produces.
 *
 * The bullet claimed "a dataset built from empty now seeds four `partial` rows". Measured on two schemas built from
 * empty in CI's own order (migrate, db:seed, DevReferenceDataSeeder, the pipeline): HEAD's tool leaves 15 `paid`,
 * zero `partial`, and one receipt — DEMO-OR-0002, ₱8,000 on an ₱18,500 sheet owing ₱10,500 — that says `paid` over
 * a balance, with six `Ledger —` lines in its report and none about the status, so it exits 0. This batch's tool on
 * the same recipe leaves 14 `paid`, 1 `partial`, no lying row. The number was not merely stale, it described the
 * pre-fix shape and argued from it; the sentence now states the pair and names the instrument.
 *
 * AGENTS.md is LF in the worktree (`git ls-files --eol` answers this; `grep -c $'\r'` was misleading once already),
 * so the needle is matched with \n and any stray CRLF introduced by the write is refused.
 */

$root = dirname(__DIR__, 3);
$path = $root.'/AGENTS.md';
$bytes = (string) file_get_contents($path);
$eol = str_contains($bytes, "\r\n") ? "\r\n" : "\n";

$old = "A dataset built from\n"
    ."  empty now seeds four `partial` rows, which is what makes the paid-only reading demonstrably wrong rather than\n"
    ."  theoretically so.";
$new = "Measured on two schemas built from\n"
    ."  empty in CI's own order (`storage/app/scratch/lane56f-probe-claims.php`): HEAD's tool leaves 15 `paid`, zero\n"
    ."  `partial`, and one receipt — `DEMO-OR-0002`, ₱8,000 on an ₱18,500 sheet owing ₱10,500 — that says `paid` over\n"
    ."  a balance, while its report prints six `Ledger —` lines, none about the status, and exits 0. This batch's tool\n"
    ."  on the same recipe leaves 14 `paid` and 1 `partial`, with no lying row. One installment is enough to make the\n"
    ."  paid-only reading demonstrably wrong rather than theoretically so: it is the row the ledger pass and the\n"
    ."  shortfall loop disagree about.";

$needle = str_replace("\n", $eol, $old);
$replacement = str_replace("\n", $eol, $new);
$hits = substr_count($bytes, $needle);
if ($hits !== 1) {
    fwrite(STDERR, "REFUSED: the claim matched {$hits} time(s), expected exactly 1 — AGENTS.md was not touched\n");
    exit(1);
}
if (file_put_contents($path, str_replace($needle, $replacement, $bytes)) === false) {
    fwrite(STDERR, "REFUSED: the write did not land\n");
    exit(1);
}

$after = (string) file_get_contents($path);
$wrong = $eol === "\r\n" ? preg_match_all("/(?<!\r)\n/", $after) : substr_count($after, "\r\n");
printf("ok: replaced once (%s endings), %+d bytes, %d stray-ending line(s), %d CRLF pairs\n",
    $eol === "\r\n" ? 'CRLF' : 'LF', strlen($replacement) - strlen($needle), $wrong, substr_count($after, "\r\n"));
printf("  longest line in that bullet now: %d\n", max(array_map('strlen',
    array_slice(explode("\n", str_replace("\r\n", "\n", $after)), 310, 22))));
if (substr_count($after, 'four `partial` rows') !== 0) {
    fwrite(STDERR, "FAIL: the old count is still in the file\n");
    exit(1);
}
echo "The stale count is gone.\n";
exit(0);
