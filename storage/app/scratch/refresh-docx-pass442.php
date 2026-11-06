<?php

/**
 * Pass 442 — the pointers into WorkflowService.php that this batch's insertion aged.
 *
 * `boxOrder()` was added above `stepsFor()` and `createWorkflow()`, so four citation windows
 * moved. Each new number is asserted against the line it names BEFORE the write, because a
 * pointer that lands on live code in the right file is invisible to a bounds sweep: line 82 is
 * `stepsFor()`'s docblock opener, 100 its closing brace, 102 `createWorkflow()`'s docblock,
 * 128 its brace, and 316 the line that writes `workflowStatus = Completed`.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass442.php [--apply]
 */

$root = dirname(__DIR__, 3);
chdir($root);
require $root.'/storage/app/scratch/docx-edit-engine.php';

$apply = in_array('--apply', array_slice($GLOBALS['argv'], 1), true);
$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$service = $root.'/app/Services/WorkflowService.php';
$lines = explode("\n", file_get_contents($service));

$assert = static function (int $line, string $mustContain) use ($lines, $fail): void {
    $text = $lines[$line - 1] ?? '(past the end of the file)';
    if (! str_contains($text, $mustContain)) {
        $fail("WorkflowService.php line {$line} reads [".trim($text)."], which does not contain [{$mustContain}] — the pointer would be a guess.");
    }
};

$assert(82, '/**');
$assert(83, 'Build the ordered workflow steps');
$assert(91, 'private function stepsFor');
$assert(100, '}');
$assert(102, '/**');
$assert(108, 'public function createWorkflow');
$assert(128, '}');
$assert(316, 'workflowStatus = WorkflowStatus::Completed');

$edits = [
    ['WorkflowService.php:52-76', 'WorkflowService.php:82-100', 3],
    ['WorkflowService.php:52-71', 'WorkflowService.php:82-100', 2],
    ['WorkflowService.php:81-108', 'WorkflowService.php:102-128', 1],
    [':292 )', ':316 )', 1],
];

$readPart = static function (string $path): string {
    $zip = new ZipArchive;
    if ($zip->open($path) !== true) {
        fwrite(STDERR, "REFUSED: the docx is not a readable archive.\n");
        exit(1);
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if (! is_string($xml) || $xml === '') {
        fwrite(STDERR, "REFUSED: document.xml could not be read.\n");
        exit(1);
    }

    return $xml;
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$xml = $readPart($docx);

foreach ($edits as $i => $e) {
    $n = substr_count($xml, $escape($e[0]));
    if ($n !== $e[2]) {
        $fail("edit {$i} matches {$n} time(s), expected {$e[2]}: [{$e[0]}]");
    }
}

printf("%s: %d pointer(s) into the file the batch grew.\n", $apply ? 'APPLY' : 'DRY RUN', count($edits));

if (! $apply) {
    echo "DRY RUN — nothing written.\n";
    exit(0);
}

$backup = $root.'/storage/app/scratch/pass442-backup-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    $fail('the backup copy could not be written.');
}

docxApply($docx, $backup, array_map(
    static fn (array $e): array => [$e[0], $e[1], null, $e[2]],
    $edits
), [], true);

$read = $readPart($docx);
foreach ($edits as $e) {
    if (substr_count($read, $escape($e[0])) !== 0) {
        copy($backup, $docx);
        $fail("the retired pointer [{$e[0]}] survives — the backup was restored.");
    }
}

echo 'WROTE '.count($edits)." pointer(s); every window into WorkflowService.php names the lines the batch moved them to.\n";
unlink($backup);
exit(0);
