<?php

/**
 * One-shot diagnosis for the term-sort falsifier: put the retired UNQUALIFIED day filter back, run the case that
 * owns the promise, print what this box's driver really says, and restore the file by the bytes saved at the start.
 *
 * Usage: php storage/app/scratch/lane223-probe-the-ambiguity-face.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$controller = 'app/Http/Controllers/Admin/ReferenceDataController.php';
$original = (string) file_get_contents($controller);
$hash = md5($original);

$qualified = <<<'DF'
            ->when($request->status === 'active', fn ($q) => $q
                ->where('academicterms.startDate', '<=', $today)
                ->where('academicterms.endDate', '>=', $today))
DF;
$unqualified = <<<'DF2'
            ->when($request->status === 'active', fn ($q) => $q
                ->where('startDate', '<=', $today)
                ->where('endDate', '>=', $today))
DF2;

if (substr_count($original, $qualified) !== 1) {
    fwrite(STDERR, 'REFUSED: the qualified filter block occurs '.substr_count($original, $qualified)." time(s), wanted 1.\n");
    exit(1);
}

$restore = static function () use ($controller, $original, $hash): void {
    file_put_contents($controller, $original);
    $still = md5((string) file_get_contents($controller)) === $hash;
    echo 'restored by bytes: '.($still ? 'yes' : 'NO — fix this now')."\n";
};
register_shutdown_function($restore);

file_put_contents($controller, str_replace($qualified, $unqualified, $original));
echo "planted the unqualified filter\n";

$out = [];
$code = 0;
exec('cd '.$root.' && "'.PHP_BINARY.'" artisan test tests/Feature/Admin/TermListSortTest.php '
    .'--filter the_day_filter_names_its_own_table_so_the_join_a_header_adds_cannot_answer_it 2>&1', $out, $code);
$body = preg_replace('/\e\[[0-9;]*m/', '', implode("\n", $out));
echo $body."\n";
echo 'exit='.$code."\n";
