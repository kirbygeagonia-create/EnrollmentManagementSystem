<?php

/**
 * Lane 47 follow-up: does the PINNED-SELECT predicate actually separate the two shapes?
 *
 * The harness leg that plants the pre-fix bytes cannot run while the whole-suite process is reading the tree, so
 * this asks the same question without touching a file: read the real AdmissionController, take the aggregate's own
 * chain, and evaluate lane47's three conditions against (a) the bytes on disk and (b) the same bytes with the
 * `select()` removed — in memory. If (a) is clean and (b) fires, the verdict is read; if both answer the same way,
 * the predicate is decoration and the harness leg would report a kill it did not earn.
 *
 * The condition copies here are deliberately the same three expressions as `lane47-tile-scope.php`. That is a
 * second spelling of them — which this repository has refused elsewhere — so the probe prints the source lines it
 * claims to mirror and compares them to the checker's text before judging anything. If the checker's expressions
 * have drifted from these, the probe refuses rather than validating a rule that no longer exists.
 */
$root = dirname(__DIR__, 3);
$checker = $root.'/storage/app/scratch/lane47-tile-scope.php';
$target = $root.'/app/Http/Controllers/Admission/AdmissionController.php';

$src = (string) file_get_contents($checker);
$needed = [
    "\$inheritsOrder = \$orderAt !== false && \$orderAt < \$cloneAt;",
    "\$appends = preg_match('/selectRaw\\(/', \$chain) === 1;",
    "\$replacesFirst = preg_match('/->select\\([^)]*\\)\\s*(->selectRaw|\\->groupBy|\\->pluck|\\->first|\\->count|\\->sum)/', \$chain) === 1;",
];
$missing = [];
foreach ($needed as $line) {
    if (! str_contains($src, $line)) {
        $missing[] = trim($line);
    }
}
if ($missing !== []) {
    fwrite(STDERR, "REFUSED: the checker no longer contains the expressions this probe mirrors, so it would be"
        ." validating a rule that does not exist:\n  ".implode("\n  ", $missing)."\n");

    exit(1);
}
echo 'the three expressions are present in the checker as written here.'."\n";

$body = (string) file_get_contents($target);

// The aggregate's chain: everything from `(clone $query)` up to the statement's end.
if (preg_match('/\$statusCounts = \(clone \$query\)(.{0,400}?)\;/s', $body, $m) !== 1) {
    fwrite(STDERR, "REFUSED: no status-tile chain was found in AdmissionController, so nothing could be judged.\n");

    exit(1);
}
$chain = $m[1];

$orderAt = strpos($body, 'ListSort::apply(');
$cloneAt = (int) strpos($body, '$statusCounts = (clone $query)');
$paginateAt = (int) strpos($body, '$query->paginate(');

$judge = static function (string $label, string $chainUse) use ($orderAt, $cloneAt, $paginateAt): void {
    $appends = preg_match('/selectRaw\(/', $chainUse) === 1;
    $replaces = preg_match('/->select\([^)]*\)\s*(->selectRaw|\->groupBy|\->pluck|\->first|\->count|\->sum)/', $chainUse) === 1;
    $inherits = $orderAt !== false && $orderAt < $cloneAt;
    printf("  %-26s inherits-order=%s appends=%-5s replaces-first=%-5s  => %s\n",
        $label, $inherits ? 'yes' : 'NO', $appends ? 'yes' : 'no', $replaces ? 'yes' : 'no',
        ($inherits && $appends && ! $replaces) ? 'PINNED-SELECT' : 'clean');
    if ($cloneAt > $paginateAt) {
        echo "                             (and SILENT-ZERO, because the clone follows paginate)\n";
    }
};

echo "clone at {$cloneAt}, order applied at {$orderAt}, paginate at {$paginateAt}\n";
$judge('on disk (guarded)', $chain);

$preFix = str_replace("->select('admissionStatus')->selectRaw(", '->selectRaw(', $chain);
if ($preFix === $chain) {
    fwrite(STDERR, "REFUSED: the in-memory mutation changed nothing — the pre-fix shape cannot be produced from these"
        ." bytes, so this probe would report a clean result it never tested.\n");

    exit(1);
}
$judge('pre-fix bytes', $preFix);

// The whole point of the leg is that the ONLY difference is the missing select(): the clone order must be identical
// in both, or the two verdicts would be explained by SILENT-ZERO rather than by the new rule.
if ($cloneAt > $paginateAt) {
    fwrite(STDERR, "REFUSED: the admission clone follows paginate(), so a SILENT-ZERO would fire alongside and the"
        ." leg would not isolate the new verdict.\n");

    exit(1);
}
echo "VERDICT: the guarded bytes read clean and the pre-fix bytes fire PINNED-SELECT, with the clone before paginate"
    ." in both — the harness leg isolates the new rule.\n";
