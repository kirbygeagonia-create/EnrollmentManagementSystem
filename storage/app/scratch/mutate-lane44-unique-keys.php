<?php

/**
 * Lane 44 mutant harness — proves lane44-unique-keys.php can fail.
 *
 * Mutants are planted in a scratch COPY of the regenerated flat dump, never in the docx, and passed in through
 * --dump=. That flag is itself a mutant target: pass 41's harness reported five missed defect classes until it
 * was proven the override is read rather than accepted, so case 0 runs the checker against a copy that is
 * byte-identical to the real dump and requires the same green verdict — an ignored flag would still pass that,
 * which is why case 1 then plants a defect only the copy carries.
 *
 * The two row cases used to anchor on `ROW 1237: uq_documentprintlog_issue` — the dump's own line ordinal. Docx
 * pass 141b inserted one paragraph earlier in §18.3, every ordinal after it moved by one, both str_replace calls
 * matched nothing, and the harness reported two BAD cases whose checker output was simply the clean run: a mutant
 * that cannot apply is a mutant that cannot fail, and the only thing that revealed it was re-running the harness
 * after an unrelated docx edit. So rows are now found BY CONTENT and each case refuses outright when its
 * mutation leaves the text unchanged.
 *
 * Usage: php storage/app/scratch/mutate-lane44-unique-keys.php
 */

$root = dirname(__DIR__, 3);
$php = getenv('EMS_PHP') ?: 'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe';
$checker = $root.'/storage/app/scratch/lane44-unique-keys.php';
$dump = $root.'/storage/app/scratch/docx-flat.txt';
$copy = $root.'/storage/app/scratch/lane44-mutant-dump.txt';

$original = (string) file_get_contents($dump);

$run = function (string $dumpPath) use ($php, $root, $checker): array {
    $out = [];
    $code = 0;
    exec('cd /d '.escapeshellarg($root).' && '.escapeshellarg($php).' '.escapeshellarg($checker)
        .' --dump='.escapeshellarg($dumpPath).' 2>&1', $out, $code);

    return [$code, implode("\n", $out)];
};

/** The one ROW line whose first cell is this key — located by content, never by ordinal. */
$keyRowOf = function (string $cells) use ($original): ?string {
    foreach (explode("\n", $original) as $line) {
        if (str_starts_with($line, 'ROW ') && str_contains($line, $cells)) {
            return $line;
        }
    }

    return null;
};

$failures = 0;
$case = function (string $name, string $mutated, string $needle, bool $expectFail = true) use ($run, $copy, $original, &$failures): void {
    // Case 0 feeds the copy through unchanged on purpose, so only a case that claims to plant a defect is
    // held to this: the no-op guard exists because two of these cases once ran silently against clean text.
    if ($expectFail && $mutated === $original) {
        printf("  BAD %-38s MUTANT DID NOT APPLY — the anchor matched nothing, so this case planted no defect and\n"
            ."       would have been reported as a caught one by any harness that trusts its own str_replace\n", $name);
        $failures++;

        return;
    }
    file_put_contents($copy, $mutated);
    [$code, $out] = $run($copy);
    $verdict = $expectFail ? ($code !== 0 && str_contains($out, $needle)) : ($code === 0);

    printf("  %s %-38s exit=%d  %s\n", $verdict ? 'ok  ' : 'BAD ', $name, $code,
        $expectFail ? (str_contains($out, $needle) ? 'needle found' : 'needle MISSING: '.$needle)
            : ($code === 0 ? 'clean as expected' : 'unexpectedly red'));

    if (! $verdict) {
        printf("       %s\n", trim(preg_replace('/\s+/', ' ', mb_substr($out, 0, 300))));
        $failures++;
    }
};

$keyRow = $keyRowOf('uq_documentprintlog_issue | enrollmentId, documentType, documentNumber');
if ($keyRow === null) {
    fwrite(STDERR, "FATAL: §23.6.1's print-log issuance row was not found in the dump by its own cells.\n"
        ."Either the table moved or its column text did — re-read the section, do not retype this anchor.\n");
    exit(1);
}

echo "=== case 0: the flag must be read — an unmutated copy has to look like the real file ===\n";
$case('clean copy through --dump=', $original, '', false);

echo "\n=== planted defects in the copy ===\n";

printf("(row under test: %s…)\n\n", mb_substr($keyRow, 0, 64));

// A key the server enforces but the table no longer lists: the row stays, its name stops matching.
$case('a listed key row removed',
    str_replace($keyRow, str_replace('uq_documentprintlog_issue', 'uq_not_on_this_server', $keyRow), $original),
    'MISS  uq_documentprintlog_issue');

// A key the table lists that the server does not have: a whole extra row, the real one untouched.
$case('a phantom key added',
    str_replace($keyRow, $keyRow."\nROW 99999: uq_invented_for_the_mutant | someColumn"
        .' | Invented so the checker has to notice a key the server does not hold. | mutant', $original),
    'PHANTOM');

// The count sentence, found by its SHAPE rather than by today's numbers. This harness aged twice for hardcoding
// "Nine keys in seven tables" — docx pass 349 made the list ten keys in eight tables, both count cases then planted
// nothing, and each reported itself as a caught defect until the run said MUTANT DID NOT APPLY. An anchor that has to
// be re-cut every time the document gets truer is an anchor that hides the change it exists to test.
//
// So the two numbers are read as words OR digits and the planted defect is computed from them (one fewer of each)
// instead of looked up in a pair list written on one day: pass 382 took the sentence to twelve keys in ten tables,
// and a `$lower` map that stops at Eleven would have made this harness report its own age as a defect for the third
// time.
$words = ['six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12,
    'thirteen' => 13, 'fourteen' => 14, 'fifteen' => 15, 'sixteen' => 16, 'seventeen' => 17, 'eighteen' => 18,
    'nineteen' => 19, 'twenty' => 20];
$asNumber = static fn (string $w): ?int => is_numeric($w) ? (int) $w
    : ($words[strtolower($w)] ?? null);
$spell = static function (int $n) use ($words): string {
    $flip = array_search($n, $words, true);

    return $flip === false ? (string) $n : ucfirst($flip);
};

$sentence = null;
if (preg_match('/\b(\d+|Six|Seven|Eight|Nine|Ten|Eleven|Twelve|Thirteen|Fourteen|Fifteen|Sixteen|Seventeen|Eighteen|Nineteen|Twenty)\s+keys?\s+in\s+(\d+|six|seven|eight|nine|ten|eleven|twelve|thirteen|fourteen|fifteen|sixteen|seventeen|eighteen|nineteen|twenty)\s+tables?\./iu', $original, $m) === 1) {
    $sentence = $m[0];
    $keys = $asNumber($m[1]);
    $tables = $asNumber($m[2]);
    if ($keys === null || $tables === null || $keys < 7 || $tables < 7) {
        $sentence = null;
        $fewer = null;
        echo "\n  BAD the count sentence was read but one of its numbers is outside the map this harness can spell\n";
    } else {
        // Both numbers are replaced in place so the mutant keeps the document's own spacing and case, and the
        // result is compared: a "mutant" that equals the original is a case that cannot fail, which is the defect
        // this harness was rewritten once already for.
        $fewer = preg_replace('/'.$m[1].'(?=\s+keys?\b)/iu', $spell($keys - 1), $sentence, 1);
        $fewer = preg_replace('/'.$m[2].'(?=\s+tables?\b)/iu', strtolower($spell($tables - 1)), (string) $fewer, 1);
        if ($fewer === $sentence) {
            echo "\n  BAD the planted count equals the original — the mutant would change no byte.\n";
            $failures += 2;
            $sentence = null;
        }
    }
}

if ($sentence === null) {
    echo "\n  BAD the count sentence is unreadable to this harness — no `[number] keys in [word] tables.` sentence is in\n"
        ."       the dump, so both count cases below cannot be planted. Re-cut the anchor; do not skip the cases.\n";
    $failures += 2;
} else {
    // The count sentence moved one key lower than the server's truth.
    $case('the count moved one key lower',
        str_replace($sentence, $fewer, $original),
        'the sentence says');

    // The sentence removed entirely — a claim the checker cannot read must not look checked.
    $case('the count sentence unreadable',
        str_replace($sentence, 'Keys are listed below.', $original),
        'UNBOUND');
}

@unlink($copy);

printf("\n  %d case(s) failed.\n", $failures);

exit($failures === 0 ? 0 : 1);
