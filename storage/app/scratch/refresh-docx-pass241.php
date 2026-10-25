<?php

/**
 * Lane 70, pass 241: two §13 pointers that land on live code of the same file describing something else.
 *
 * Both came out of reading the anchor checker's own UNBOUND queue — the rows no bounds check can see, because the
 * number is live in the file its sentence names. The first is the third sentence of §13's query-builder paragraph:
 * it says "G-8 removed the status write from BOTH examination paths", cites the general path correctly at
 * :325-332, and cites :355-362 for the course-specific one. :355-362 is the head of recordCourseSpecific() — the
 * "This course administers no entrance examination" refusal and the BR9/C-4/C-5 comment — and the course-specific
 * G-8 closure is the comment at :397-402 with the redirect at :403-404, which §13's own effect-of-the-result row
 * already cites with the file named. The document holds the true owner twice; one copy was unattributed (the pass
 * 228 shape again).
 *
 * The second is §28.3 G-15's citation cell, `ExamController.php:424-427 and :441`. The row's closure is "results()
 * honours `search` exactly as index() does AND echoes both keys": :424-427 is the search branch, and the echo is
 * `'filters' => $request->only(['result', 'search'])` at :476. :441 is the departmental `orWhere` inside the
 * ownership split — a live line in the right method for a claim that is not about ownership at all.
 *
 * Neither fix is arithmetic: each new number is asserted to carry the claim and each old number asserted NOT to,
 * against the working file, before the write. Both needles were probed unique before this script was written; the
 * pass re-probes them on the staged bytes and refuses if that has changed. Post-conditions are scoped to the
 * replaced pair, never to a bare number document-wide (pass 229's own failure).
 */
$root = dirname(__DIR__, 3);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, $c);
            $fails++;
        }
    }
};

$ctrl = $root.'/app/Http/Controllers/Exam/ExamController.php';
$fl = (array) file($ctrl);
$line = static fn (int $n): string => rtrim((string) ($fl[$n - 1] ?? ''));
$body = static function (int $from, int $to) use ($line): string {
    $out = [];
    for ($i = $from; $i <= $to; $i++) {
        $out[] = $line($i);
    }

    return implode("\n", $out);
};

echo "=== stage 1: the lines each number lands on, read from the working file\n";
$guard(str_contains($body(325, 332), '(G-8)') && str_contains($body(325, 332), 'Set the status here')
    && str_contains($body(325, 332), 'AdmissionPolicy::approve'), ':325-332 (kept) is the general path’s G-8 comment');
$guard(! str_contains($body(355, 362), 'G-8'), ':355-362 does NOT carry the G-8 closure — the citation is wrong');
$guard(str_contains($body(355, 362), 'administers no entrance examination') && str_contains($body(355, 362), 'BR9'),
    ':355-362 is the course-specific head and the BR9/C-4/C-5 comment — a different subject');
$guard(str_contains($body(397, 404), '(G-8)') && str_contains($body(397, 404), 'admissionStatus = approved')
    && str_contains($body(397, 404), 'redirect()->route') && str_contains($body(397, 404), 'The Admission office decides'),
    ':397-404 IS the course-specific path’s G-8 closure and the redirect that writes no status');
$guard(str_contains($body(424, 427), 'when($request->search') && str_contains($body(424, 427), 'schoolIdNumber'),
    ':424-427 (kept) is results() honouring `search` the way index() does');
$guard(! str_contains($body(441, 441), 'filters') && ! str_contains($body(441, 441), 'search'),
    ':441 carries neither the echo nor the search — the citation is wrong');
$guard(str_contains($body(441, 441), 'orWhere') && str_contains($body(436, 444), 'examType'),
    ':441 is the departmental `orWhere` inside the ownership split');
$guard(trim($line(476)) === "'filters' => \$request->only(['result', 'search']),",
    ':476 is exactly the echo of both keys: '.trim($line(476)));
$guard(substr_count($body(410, 483), 'where(\'examStage\'') === 2 && ! str_contains($body(410, 483), '$request->stage'),
    'results() reads no `stage` key any more — its two examStage filters are the surviving ownership branches');
$guard(str_contains($line(123), '$request->stage'),
    'the candidate list at :123 still takes `stage`, exactly as the row says — so the claim is scoped to results()');
if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

echo "\n=== stage 2: the needles on the staged bytes\n";
$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $z->getFromName('word/document.xml');
$parts = $z->numFiles;
$z->close();
$guard(str_contains($xml, '<w:body>'), 'the part read is document.xml ('.strlen($xml).' bytes)');

$edits = [
    ['and :355-362', 'and :397-404'],
    ['and :441', 'and :476'],
];
foreach ($edits as $i => [$from, $to]) {
    $c = substr_count($xml, $from);
    $guard($c === 1, 'needle '.($i + 1).' is contiguous and unique in the staged bytes (seen '.$c.'): ['.mb_substr($from, 0, 40).']');
    $assertPlain($to, 'needle '.($i + 1).' replacement');
}
$guard(substr_count($xml, ':397-404') === 1, 'before the edit :397-404 occurs once — §13’s own effect row already names it');
$guard(substr_count($xml, ':476') === 0, 'before the edit nothing in the document cites :476');
if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: this pass would move two §13/§28.3 pointers inside ExamController.php —\n"
        ."  G-8’s course-specific half  :355-362 is the method head and the BR9 comment; it moves to :397-404\n"
        ."  G-15’s echo-of-both-keys half  :441 -> :476\n"
        ."Re-run with --apply.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass241.docx');
docxApply($src, $root.'/storage/app/scratch/pass241-backup.docx', $edits, [], $apply);

$z = new ZipArchive;
$z->open($src);
$round = (string) $z->getFromName('word/document.xml');
$roundParts = $z->numFiles;
$z->close();
echo "\n=== stage 3: re-read the written bytes\n";
$guard($roundParts === $parts, 'the archive still holds '.$parts.' members');
$guard(substr_count($round, 'and :355-362') === 0 && substr_count($round, 'and :441') === 0, 'neither old pointer survives');
$guard(substr_count($round, 'and :397-404') === 1 && substr_count($round, 'and :476') === 1, 'both new pointers are on disk exactly once');
$guard(substr_count($round, ':397-404') === 2, 'the course-specific span is now cited twice: §13’s named row and this bare continuation');
if ($fails > 0) {
    printf("\n%d guard(s) failed after the write — restore the docx from storage/app/scratch/ems-docx-pre-pass241.docx.\n", $fails);
    exit(1);
}
echo "pass 241 applied.\n";
