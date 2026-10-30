<?php

/**
 * Pass 313: the day's §1.1 row gains the third wired table, which asked something the two queues did not.
 *
 * Appended to the row that already stands, because a second row for the same day double-counts the register
 * (pass 306's reason). No case count or assertion total is written here: `lane58-suite-claims.php --ci` binds a
 * figure beside a class name to the runner's own junit, and a revision row that must outlive the run states one
 * surface or none.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass313.php [--apply]
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

require __DIR__.'/docx-edit-engine.php';

$from = 'one of them only after its fixture was rebuilt.';
$to = $from.' A third desk table joined the sort mechanism the same day — the student directory — and it carried a'
    .' promise the two queues never needed: the answer the helper gives when a request names no direction is'
    .' descending, which suits a queue of newest rows and is the last thing a list of surnames wants. The helper was'
    .' not widened; the handler asks for its own ascending default beside its own fallback column, and the directory’s'
    .' first case goes red if the two are swapped. The screen side of the toggle had been spelled once per wired page,'
    .' so all three now call one module, and refactoring two working screens that have no JavaScript suite behind them'
    .' was proven where a desk would notice: the header sent the query, the rows reordered, a second click reversed'
    .' the same column, and a filter submit kept both the search term and the order. Two pointers into that page were'
    .' re-taken while doing it, and one of those had been a wrong target before this batch touched the file — which is'
    .' what a bounds sweep cannot see and only reading can.';

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open the docx (ZipArchive answers an error CODE for a bad file, which is truthy).\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

if (($hits = substr_count($xml, $from)) !== 1) {
    fwrite(STDERR, "REFUSED: the anchor occurs {$hits} time(s), expected exactly 1: [{$from}]\n");

    exit(1);
}
if (str_contains($xml, 'A third desk table joined the sort mechanism')) {
    fwrite(STDERR, "REFUSED: pass 313's own sentence is already in the document.\n");

    exit(1);
}

printf("%s: the day's row gains the directory\n", $apply ? 'APPLY' : 'DRY RUN');

docxApply($docx, $apply ? $root.'/storage/app/scratch/pass313-backup-'.date('Ymd-His').'.docx'
    : $root.'/storage/app/scratch/pass313-dry.docx', [[$from, $to]], [], $apply);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$again = new ZipArchive;
if ($again->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot re-open the written archive.\n");

    exit(1);
}
$after = (string) $again->getFromName('word/document.xml');
$again->close();

foreach ([['A third desk table joined the sort mechanism', 1], ['one of them only after its fixture was rebuilt.', 1]] as [$needle, $want]) {
    if (($h = substr_count($after, $needle)) !== $want) {
        fwrite(STDERR, "REFUSED after the write: [{$needle}] occurs {$h}, want {$want}.\n");

        exit(1);
    }
}

echo "APPLIED: the revision record names the third wired table and the default it taught.\n";
