<?php

/**
 * Read two of lane 34's negative-claim hits against live `ems`, where the claim is mechanically answerable.
 *
 * `lane34-negative-claim-sweep.php` reported 14 hits: sentences that say a thing is absent, beside an inventory that
 * holds it. Reading the fourteen shows almost all of them are the sweep's own artifact — the negation attaches to a
 * ROLE's permission set ("the Dean role does not hold block.view"), to a rule about enum values ("no assignment beyond
 * Blocks.maxStudents"), or to a table-vs-column distinction ("…is not a table") — never to the existence of the
 * identifier. Two claims are real and checkable, and this file checks exactly those two and refuses the rest:
 *
 *   1. §13's Admission claim: `admissions.applicantType` "only allows firstYear and transferee".
 *   2. Ruling (#90): no dean account holds `block.view` — the card was hidden rather than the right granted.
 *
 * Read-only. Usage: php storage/app/scratch/lane34-probe-negative-hits.php
 */
$root = dirname(__DIR__, 3);

// `.env` line by line: parse_ini_file() warns on this repository's line 3 and returns nothing.
$env = [];
foreach ((array) @file($root.'/.env') as $l) {
    if (preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/u', trim((string) $l), $m) === 1) {
        $env[$m[1]] = trim(trim($m[2]), "\"'");
    }
}
$schema = $env['DB_DATABASE'] ?? '';
if ($schema !== 'ems') {
    printf("REFUSED: this probe reads live rows and the environment points at %s.\n", $schema ?: '(nothing)');
    exit(1);
}
try {
    $pdo = new PDO('mysql:host='.($env['DB_HOST'] ?? '127.0.0.1').';dbname='.$schema,
        $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '');
} catch (Throwable $e) {
    printf("REFUSED: cannot read %s (%s).\n", $schema, $e->getMessage());
    exit(1);
}

// --- 1. the applicantType enum and the values the table actually holds -------
$decl = (string) $pdo->query("select COLUMN_TYPE from information_schema.COLUMNS
    where TABLE_SCHEMA = DATABASE() and TABLE_NAME = 'admissions' and COLUMN_NAME = 'applicantType'")->fetchColumn();
if ($decl === '') {
    exit("REFUSED: `admissions.applicantType` answered no column type — the claim cannot be weighed.\n");
}
$held = array_map(static fn (array $r): string => (string) $r[0],
    $pdo->query('select distinct applicantType from admissions order by 1')->fetchAll(PDO::FETCH_NUM));
printf("1) declared: %s\n   held on %s: %s\n   values outside firstYear|transferee: %s\n",
    $decl, $schema, $held === [] ? '(the table holds no rows)' : implode(', ', $held),
    implode(', ', array_values(array_diff($held, ['firstYear', 'transferee']))) ?: 'none');
preg_match("/enum\((.*)\)/iu", $decl, $me);
$allowed = $me === [] ? [] : array_map(static fn (string $v): string => trim($v, "' \""), explode(',', (string) $me[1]));
printf("   enum allows: %s\n   the sentence is %s\n",
    $allowed === [] ? '(unparsed)' : implode(', ', $allowed),
    $allowed !== [] && array_diff($allowed, ['firstYear', 'transferee']) === []
        ? 'TRUE as written — the column type itself admits only those two'
        : 'CHECK BY HAND — the declared enum holds more than the two named values');

// --- 2. does any dean account hold block.view? -------------------------------
// `staff_roles` is the Spatie pivot and its columns are roleId/model_id; there is no model_has_roles here. The user
// key is DERIVED, not recalled: this schema names its primary keys by subject (`staffUserId`, not `id`), and a probe
// that assumes `id` dies mid-claim having proved nothing about the claim at all.
$key = (string) $pdo->query("select COLUMN_NAME from information_schema.KEY_COLUMN_USAGE
    where TABLE_SCHEMA = DATABASE() and TABLE_NAME = 'staffusers' and CONSTRAINT_NAME = 'PRIMARY'")->fetchColumn();
$permKey = (string) $pdo->query("select COLUMN_NAME from information_schema.KEY_COLUMN_USAGE
    where TABLE_SCHEMA = DATABASE() and TABLE_NAME = 'permissions' and CONSTRAINT_NAME = 'PRIMARY'")->fetchColumn();
if ($key === '' || $permKey === '') {
    printf("REFUSED: the primary keys were not answered (staffusers=%s permissions=%s), so the pivot cannot be joined.\n",
        $key ?: '(none)', $permKey ?: '(none)');
    exit(1);
}
printf("\n2) keys derived: staffusers.%s, permissions.%s\n", $key, $permKey);
// `staff_roles.model_type` is stored double-escaped (`App\\Models\\Staffusers`), so a single-backslash literal matches
// nothing and every account looks role-less; match the class WORD instead, and print what was seen so the reader can
// tell an empty answer from a filtered one.
$types = $pdo->query('select distinct model_type from staff_roles')->fetchAll(PDO::FETCH_COLUMN);
printf("   staff_roles.model_type values present: %s\n", $types === [] ? '(the pivot holds no rows)' : implode(' | ', $types));
$deanHolders = $pdo->query('select distinct u.username, p.name
    from staffusers u
    join staff_roles sr on sr.model_id = u.'.$key." and sr.model_type like '%Staffusers%'
    join role_permissions rp on rp.roleId = sr.roleId
    join permissions p on p.".$permKey." = rp.permissionId
    where (lower(u.role) like '%dean%' or lower(u.username) like '%dean%') and p.name = 'block.view'
    order by u.username")->fetchAll(PDO::FETCH_NUM);
printf("\n2) dean-shaped accounts holding block.view: %d\n", count($deanHolders));
foreach ($deanHolders as $r) {
    echo "     {$r[0]} | {$r[1]}\n";
}
$rolesWith = (int) $pdo->query('select count(distinct rp.roleId) from permissions p
    join role_permissions rp on rp.permissionId = p.'.$permKey." where p.name = 'block.view'")->fetchColumn();
printf("   roles that hold block.view at all: %d\n", $rolesWith);
printf("   the ruling (#90, card hidden rather than the right granted) is %s\n",
    $deanHolders === [] ? 'UPHELD — no dean account holds it' : 'CONTRADICTED — read the rows above');
exit(0);
