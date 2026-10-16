<?php

/**
 * Pass 29 — §22.2's permission catalogue and §22.3's role table, regenerated from live `ems`.
 *
 * Both tables state a count and then list the names behind it, and both had been read once and
 * remembered: the catalogue's total still said 87 while the server holds 95, RegistrarApprover
 * still said 12 while its role holds 16, and the print right this pass adds appeared nowhere.
 * Every cell here is written from a query, and every write is checked by re-reading the table
 * from the finished document rather than by trusting the offsets.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

config([
    'database.default' => 'mysql',
    'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root',
    'database.connections.mysql.password' => '',
]);
DB::purge('mysql');

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);
$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

$setCell = function (string $cell, string $text) use ($esc): string {
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);

    return str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'.$esc($text).'</w:t></w:r></w:p>', $stripped);
};

// ── 1. what the server says ──────────────────────────────────────────────────────────────

// Insertion order is the seeder's own grouping order, which is how the catalogue lists names.
$byModule = [];
foreach (DB::select("select name, module from permissions where guard_name='web' order by id") as $p) {
    $byModule[strtolower($p->module)][] = $p->name;
}
$rolePerms = [];
foreach (DB::select("
    select r.name rn, p.name pn
    from role_permissions rp
    join roles r on r.id = rp.roleId
    join permissions p on p.id = rp.permissionId
    where r.guard_name = 'web'
    order by r.name, p.name") as $r) {
    $rolePerms[$r->rn][] = $r->pn;
}
$descriptions = DB::select("select name, description from roles where guard_name='web'");
$desc = [];
foreach ($descriptions as $d) {
    $desc[$d->name] = $d->description;
}
$permissionTotal = array_sum(array_map('count', $byModule));
printf("server: %d permissions across %d modules, %d roles\n",
    $permissionTotal, count($byModule), count($rolePerms));

// ── 2. the two tables ────────────────────────────────────────────────────────────────────

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);

$find = function (array $headerCells) use ($tables, $cellText): ?array {
    foreach ($tables[0] as $t) {
        preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
        if (array_map($cellText, $cs[0]) === $headerCells) {
            return ['xml' => $t[0], 'at' => $t[1]];
        }
    }

    return null;
};

$catalogue = $find(['Module', 'Count', 'Permission names']);
$roleTable = $find(['Role', 'Seeded purpose', '#', 'Permissions']);
if ($catalogue === null || $roleTable === null) {
    fwrite(STDERR, "REFUSE: could not find §22.2 or §22.3 by their header rows\n");
    exit(1);
}

$writes = [];

/** Queues one cell rewrite, remembering its row key so the finished file can be re-read. */
$queue = function (string $which, array $table, array $row, string $rowKey, int $index, string $newValue, string $label) use (&$writes, $setCell, $cellText): void {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row['xml'], $cs, PREG_OFFSET_CAPTURE);
    $was = $cellText($cs[0][$index][0]);
    if ($was === $newValue) {
        return;
    }
    $writes[] = [
        'at' => $table['at'] + $row['at'] + $cs[0][$index][1],
        'len' => strlen($cs[0][$index][0]),
        'to' => $setCell($cs[0][$index][0], $newValue),
        'was' => $was,
        'now' => $newValue,
        'label' => $label,
        'cell' => $index,
        'key' => $which.'|'.$rowKey,
    ];
    printf("  %-32s cell %d: %s… → %s…\n", $label, $index, mb_substr($was, 0, 28), mb_substr($newValue, 0, 28));
};

// §22.2 — Module | Count | Permission names, plus the Total row.
preg_match_all('/<w:tr>.*?<\/w:tr>/s', $catalogue['xml'], $trs, PREG_OFFSET_CAPTURE);
foreach ($trs[0] as $row) {
    $v = [];
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cs0);
    foreach ($cs0[0] as $c) {
        $v[] = $cellText($c);
    }
    if (count($v) !== 3 || $v[0] === 'Module') {
        continue;
    }
    $r = ['xml' => $row[0], 'at' => $row[1]];
    if ($v[0] === 'Total') {
        $queue('catalogue', $catalogue, $r, 'Total', 1, (string) $permissionTotal, 'catalogue Total');

        continue;
    }
    $module = strtolower($v[0]);
    if (! isset($byModule[$module])) {
        echo "  note: the catalogue names a module the server does not use: {$v[0]}\n";

        continue;
    }
    $queue('catalogue', $catalogue, $r, $v[0], 1, (string) count($byModule[$module]), "catalogue {$module} count");
    $queue('catalogue', $catalogue, $r, $v[0], 2, implode(', ', $byModule[$module]), "catalogue {$module} names");
}

// §22.3 — Role | Seeded purpose | # | Permissions. SysAdmin's row is deliberately different:
// its count cell reads "all" and its name cell is a sentence about the blanket grant.
preg_match_all('/<w:tr>.*?<\/w:tr>/s', $roleTable['xml'], $rrs, PREG_OFFSET_CAPTURE);
foreach ($rrs[0] as $row) {
    $v = [];
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row[0], $cs1);
    foreach ($cs1[0] as $c) {
        $v[] = $cellText($c);
    }
    if (count($v) !== 4 || $v[0] === 'Role') {
        continue;
    }
    $role = $v[0];
    $r = ['xml' => $row[0], 'at' => $row[1]];
    if ($role === 'SysAdmin') {
        continue;
    }
    if (! isset($rolePerms[$role])) {
        echo "  note: the role table names a role the server does not hold: {$role}\n";

        continue;
    }
    $queue('roles', $roleTable, $r, $role, 2, (string) count($rolePerms[$role]), "role {$role} count");
    $queue('roles', $roleTable, $r, $role, 3, implode(', ', $rolePerms[$role]), "role {$role} names");
    if ($role === 'IdOfficer') {
        $queue('roles', $roleTable, $r, $role, 1, (string) $desc['IdOfficer'], 'role IdOfficer description');
    }
}

if ($writes === []) {
    fwrite(STDERR, "REFUSE: the two tables already match the server — nothing to write\n");
    exit(1);
}

printf("\n%d cells to rewrite\n", count($writes));

if (! $apply) {
    echo "\nDRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass29.docx');

usort($writes, fn ($a, $b) => $b['at'] <=> $a['at']);
foreach ($writes as $w) {
    $xml = substr_replace($xml, $w['to'], $w['at'], $w['len']);
}

// ── 3. guards ────────────────────────────────────────────────────────────────────────────

foreach (['<w:tbl>', '</w:tbl>', '<w:tr>', '</w:tr>', '<w:tc>', '</w:tc>', '<w:p>', '</w:p>'] as $tag) {
    $moved = substr_count($xml, $tag) - substr_count($original, $tag);
    if ($moved !== 0) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected 0\n");
        exit(1);
    }
}
echo "guard  containers unmoved; only cell text changed\n";
if (libxml_use_internal_errors(true) && simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

// Re-read both tables from the finished document and require every rewritten cell in its place.
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $newTables);
$rows = [];
foreach ($newTables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t, $f);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $f[0], $hc);
    $head = array_map($cellText, $hc[0]);
    $which = null;
    if ($head === ['Module', 'Count', 'Permission names']) {
        $which = 'catalogue';
    } elseif ($head === ['Role', 'Seeded purpose', '#', 'Permissions']) {
        $which = 'roles';
    }
    if ($which === null) {
        continue;
    }
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t, $body);
    foreach ($body[0] as $b) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $b, $cc);
        $vals = array_map($cellText, $cc[0]);
        $rows[$which.'|'.$vals[0]] = $vals;
    }
}

$verified = 0;
foreach ($writes as $w) {
    if (! isset($rows[$w['key']])) {
        fwrite(STDERR, "REFUSE WRITE: the row for {$w['label']} was not found on re-read\n");
        exit(1);
    }
    $got = preg_replace('/\s+/', ' ', $rows[$w['key']][$w['cell']] ?? '');
    $want = preg_replace('/\s+/', ' ', $w['now']);
    if ($got !== $want) {
        fwrite(STDERR, "REFUSE WRITE: {$w['label']} cell {$w['cell']} reads \"".mb_substr($got, 0, 60)."\", expected \"".mb_substr($want, 0, 60)."\"\n");
        exit(1);
    }
    $verified++;
}
echo "guard  all {$verified} rewritten cells re-read from the finished tables\n";

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "\nWROTE {$src}\n";
