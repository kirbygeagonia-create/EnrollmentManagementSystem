<?php

/**
 * Is the use-case diagram's ACTOR vocabulary one-per-role, or is the same role drawn as several stick figures?
 *
 * Why this question: the model folds 41 actor declarations into 28 distinct labels, while `RbacSeeder` seats 17
 * roles. A reviewer reading a "proper" use-case diagram will ask why the cashier appears twice. This probe does not
 * decide that — it prints the clusters and the roles the system actually seats, so a merge is made from evidence
 * rather than from a guess about wording.
 *
 * The actors are read from the generator's own `model.json`, never re-parsed from the markdown here. This repository
 * keeps one definition of "what is an actor": when this probe first read the guide directly it counted 43
 * declarations where the model holds 41, because the two readers disagreed about one label's spelling (an emoji the
 * renderer strips). A second parser invents a count nobody can reconcile.
 *
 * Read-only. Usage: php storage/app/scratch/lane214-probe-actor-vocabulary.php
 */
$root = dirname(__DIR__, 3);
chdir($root);

$fail = static function (string $m): never {
    fwrite(STDERR, "REFUSED: {$m}\n");
    exit(1);
};

$modelPath = $root.'/Documentation/Diagrams/model.json';
if (! is_file($modelPath)) {
    $fail('run the figure generator first: model.json is the only answer to what the model declares.');
}
$model = json_decode((string) file_get_contents($modelPath), true, 512, JSON_THROW_ON_ERROR);
if (($model['census']['actors'] ?? null) === null || ! is_array($model['nodes'] ?? null)) {
    $fail('model.json holds no actor census — the reader is blind, not the diagram clean.');
}

$decls = [];
foreach ($model['nodes'] as $n) {
    if (($n['kind'] ?? '') !== 'actor') {
        continue;
    }
    $decls[] = trim(preg_replace('/\s+/', ' ', (string) $n['label']));
}
if ($decls === []) {
    $fail('the model declares no actor — refuse, do not report an empty vocabulary as agreement.');
}
if (count($decls) !== (int) $model['census']['actors']) {
    $fail('this reader counted '.count($decls)." actor nodes where the model's own census says {$model['census']['actors']} — the two readers are out of step.");
}
$distinct = array_values(array_unique($decls));
$perLabel = [];
foreach ($decls as $l) {
    $perLabel[$l] = ($perLabel[$l] ?? 0) + 1;
}

// ---------------------------------------------------------------- the roles the system actually seats
$seeder = $root.'/database/seeders/RbacSeeder.php';
if (! is_file($seeder)) {
    $fail('RbacSeeder.php is missing, so the role side of the pairing cannot be read.');
}
$src = (string) file_get_contents($seeder);
preg_match_all("/\['name' => '([^']+)'/", $src, $rm);
$roles = array_values(array_unique($rm[1] ?? []));
if ($roles === []) {
    $fail('no role name could be read out of RbacSeeder.php — refuse, do not print an empty list as agreement.');
}

// ---------------------------------------------------------------- cluster the labels by their words
$tokens = static function (string $l): array {
    $l = strtolower(preg_replace('/\((?:[^)]*)\)/', ' ', $l) ?? $l);
    $words = preg_split('/[^a-z]+/', $l, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $stop = array_flip(['and', 'the', 'of', 'for', 'any', 'all', 'or', 'staff', 'officer', 'person']);
    $kept = array_values(array_filter($words, static fn (string $w): bool => ! isset($stop[$w])));

    return $kept === [] ? $words : $kept;
};
$clusters = [];
foreach ($distinct as $l) {
    $w = $tokens($l);
    sort($w);
    $clusters[implode(' ', $w)][] = $l;
}
ksort($clusters);

printf("model: %d actor declarations, %d distinct labels. seeder: %d roles.\n\n", count($decls), count($distinct), count($roles));

$duplicates = array_filter($clusters, static fn (array $g): bool => count($g) > 1);
echo 'clusters whose labels reduce to the same words ('.count($duplicates)."):\n";
if ($duplicates === []) {
    echo "  (none)\n";
}
foreach ($duplicates as $key => $group) {
    echo '  ['.$key.'] -> '.implode('  |  ', $group)."\n";
}

echo "\nevery distinct label, with how many blocks declare it:\n";
foreach ($distinct as $l) {
    printf("  %-46s declared in %d block(s)%s\n", $l, $perLabel[$l], count($tokens($l)) === 0 ? '  (NO DISTINGUISHING WORD)' : '');
}

echo "\nroles the system seats, for comparison:\n  ".implode(', ', $roles)."\n";

// A label with no distinguishing word after the stoplist is a real defect: two such actors are indistinguishable by
// construction, and the generator draws them as two separate figures.
$shapeless = array_values(array_filter($distinct, static fn (string $l): bool => count($tokens($l)) === 0));
printf("\n%s\n", $shapeless === []
    ? 'VERDICT: every actor label keeps at least one distinguishing word.'
    : 'VERDICT: '.count($shapeless).' label(s) reduce to nothing after the stoplist: '.implode(', ', $shapeless));
exit($shapeless === [] ? 0 : 1);
