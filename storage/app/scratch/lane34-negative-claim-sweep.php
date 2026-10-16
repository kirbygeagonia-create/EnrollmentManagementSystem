<?php

/**
 * Lane 34, second form: test the document's EXISTENTIAL NEGATIVES against three ground-truth inventories.
 *
 * The first version asked "is there a code identifier near the negation?" and tested 3 of 288 claims — the
 * document does not write its negatives as identifiers, it writes "there is no cancel action on the desk". So
 * the subjects are nouns, and nouns have inventories: 191 routes, 452 columns, 93 permissions, read from the
 * running application and the live database rather than guessed from the repository.
 *
 * For each unit that reads as an existential negative, extract the noun in the SAME clause ("no cancel
 * action" → cancel), look it up in the three inventories, and report a hit when the thing the sentence says is
 * absent is present in the inventory. A hit is still a question — "no drop action" and a route named
 * `enrollment.drop-fee` are different things — but now the queue is short enough to read and each entry is
 * checkable against a named route, column or permission.
 *
 * usage: php storage/app/scratch/lane34-negative-claim-sweep.php [dump] [--verbose]
 */

$root = dirname(__DIR__, 3);
$verbose = in_array('--verbose', $argv, true);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
foreach (array_slice($argv, 1) as $a) {
    if (! str_starts_with($a, '-')) {
        $dump = $a;
    }
}
is_file($dump) || exit("no dump at {$dump} — run dump-docx-flat.php first\n");

// --- inventories -------------------------------------------------------------
$routes = json_decode(file_get_contents($root.'/storage/app/scratch/lane34-routes.json'), true) ?: [];
$routeIndex = [];
foreach ($routes as $r) {
    foreach (['uri' => $r['uri'] ?? '', 'name' => $r['name'] ?? '', 'action' => $r['action'] ?? ''] as $k => $v) {
        if ($v === '') {
            continue;
        }
        foreach (preg_split('/[^a-zA-Z]+/', (string) $v) ?: [] as $word) {
            if (strlen($word) >= 5) {
                $routeIndex[strtolower($word)][] = $k.': '.$v;
            }
        }
    }
}
$columns = array_values(array_filter(array_map('trim', file($root.'/storage/app/scratch/lane34-columns.txt') ?: [])));
$colIndex = [];
foreach ($columns as $c) {
    foreach (preg_split('/[^a-zA-Z]+/', $c) ?: [] as $word) {
        if (strlen($word) >= 5) {
            $colIndex[strtolower($word)][] = $c;
        }
    }
}
$permissions = array_values(array_filter(array_map('trim', file($root.'/storage/app/scratch/lane34-permissions.txt') ?: [])));
$permIndex = [];
foreach ($permissions as $p) {
    foreach (preg_split('/[^a-zA-Z]+/', $p) ?: [] as $word) {
        if (strlen($word) >= 5) {
            $permIndex[strtolower($word)][] = $p;
        }
    }
}

// --- units -------------------------------------------------------------------
$units = [];
foreach (preg_split('/\r?\n/', file_get_contents($dump)) ?: [] as $line) {
    foreach (preg_split('/(?<=[.。;]) (?=[A-Z(])| \| /u', $line) ?: [] as $u) {
        if (trim($u) !== '') {
            $units[] = trim($u);
        }
    }
}

// --- the negative, and the noun it negates -----------------------------------
$patterns = [
    // "no cancel action", "there is no drop route", "no reissue endpoint"
    '/\bno\s+([a-z]+(?:\s+[a-z]+){0,2})\s+(action|route|screen|endpoint|column|permission|field|form|button|table|verb)\b/i',
    // "there is no clearance window", "there was no role"
    '/\bthere (?:is|are|was|were) no\s+([a-z]+(?:\s+[a-z]+){0,2})\b/i',
    // "nothing sets the status", "nothing writes a row"
    '/\bnothing\s+([a-z]+)\b/i',
    // "never called", "never invoked"
    '/\bnever\s+(called|invoked|read|written|set|used)\b/i',
];

$stopwords = ['desk', 'code', 'system', 'application', 'screen', 'the', 'a', 'an', 'any', 'such', 'long',
    'other', 'second', 'same', 'further', 'known', 'listed', 'action', 'route', 'column', 'thing'];

$flags = [];
$tested = 0;

// Read the hits this produced and every one was a noun collision: "there is no student login" matched the
// STAFF login route, "no shift action writes one" matched the shift routes it is not talking about, "there is
// no student guard" matched a column named guard_name. Twenty hits, zero defects — an inventory search by
// common noun cannot tell a domain from a claim. So this form tests only EXACT identifiers: a dotted permission
// or route name, or a `table.column`, quoted verbatim in the sentence. That is decidable, and anything it
// misses is missed knowingly.
$exact = array_merge($permissions, $columns);
foreach ($routes as $r) {
    if (! empty($r['name'])) {
        $exact[] = $r['name'];
    }
}
$exactLower = [];
foreach ($exact as $e) {
    $exactLower[strtolower($e)] = $e;
}

foreach ($units as $unit) {
    // "No reference table has a student-type column except admissionrequirements.appliesTo" is an EXCEPTION
    // construction: the named identifier is present precisely because the sentence allows it.
    if (preg_match('/\bexcept\b|\bbesides\b|\bother than\b|\bapart from\b/i', $unit)) {
        continue;
    }

    // The identifier has to be the thing the negation governs, which in English means it follows the cue within
    // the same short span: "has no clinic.record permission", "there is no such route in the application:
    // clearance.periods.extend". A comma or semicolon ends the span, because "the module has no extend
    // permission, gated instead on clearance.periods.manage" names a REAL permission in the half of the
    // sentence that is not being denied — the case that made the first version of this rule flag a correct
    // sentence.
    $testedNote = null;
    if (preg_match_all(
        '/\b(?:no|not|never|without|lack(?:s|ing)?|there\s+(?:is|are|was|were)\s+no|none)\b([^,;.]{0,34}?)\b([a-z][a-z0-9_]*(?:\.[a-z0-9_]+){1,3})\b/i',
        $unit, $ids, PREG_SET_ORDER)) {
        foreach ($ids as $x) {
            $between = strtolower($x[1] ?? '');
            $id = strtolower($x[2]);
            if (preg_match('/\b(?:instead|rather|but|and|plus|with)\b/', $between)
                || str_contains($between, '.')) {
                continue;
            }
            $tested++;
            $present = $exactLower[$id] ?? null;
            if ($present !== null) {
                $flags[] = [$id, ['exact identifier present in the inventory: '.$present], $unit];
            }
        }
    }
}

printf("inventories: %d routes, %d columns, %d permissions\n%d noun-inventory pair(s) tested; %d hit(s) to read\n\n",
    count($routes), count($columns), count($permissions), $tested, count($flags));

$seen = [];
foreach ($flags as [$w, $hits, $unit]) {
    $key = $w.'|'.implode(',', $hits);
    if (isset($seen[$key])) {
        continue;
    }
    $seen[$key] = true;
    printf("  \"%s\" — said to be absent, present:\n", $w);
    foreach ($hits as $h) {
        printf("      %s\n", substr($h, 0, $verbose ? 200 : 120));
    }
    if ($verbose) {
        printf("      unit: %s\n", substr(preg_replace('/\s+/', ' ', $unit), 0, 300));
    }
}

printf("\n%d distinct hit(s) of %d total.\n", count($seen), count($flags));
exit(count($flags) === 0 ? 0 : min(99, count($flags)));
