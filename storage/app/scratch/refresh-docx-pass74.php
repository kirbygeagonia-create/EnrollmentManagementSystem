<?php

/**
 * Pass 74 — §22 rebuilt from the installed database, not from memory, after dashboard.view was
 * retired (owner ruling 2026-10-10).
 *
 * Measuring first turned up three things, not one:
 *  1. dashboard.view itself — 16 §22.3 role rows name it, §22.2 carries its module row, and the
 *     SysAdmin row says "every one of the 94 permissions".
 *  2. §22.2's exam row still lists exam.verify.general, retired on 2026-10-08 (#47), and the
 *     section's rows sum to 92 under a stated Total of 95.
 *  3. The `shift` module (3 permissions, ruling 11) has no row in §22.2 at all, and §22.5 still
 *     lists clearance.slip.replace as SysAdmin-only although ruling 8 gave it to Accounting and
 *     the Registrar.
 *
 * So the tables are regenerated from live `ems` (permissions + role_permissions), which is what the
 * section's own sentence claims. Row deletion is done by removing the exact <w:tr> bytes; the one
 * row that must be ADDED (shift) is cloned from the students row the way pass 71 cloned a revision
 * row, because the engine cannot move a <w:tr>. Every count and name list is asserted against the
 * measured numbers afterwards, not trusted from arithmetic.
 *
 * Dry-run by default; --apply to write. Backup + verify-after-read.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems',
    'database.connections.mysql.username' => 'root', 'database.connections.mysql.password' => '']);
DB::purge('mysql');

// ---- measured truth -------------------------------------------------------------
$modules = [];
foreach (DB::table('permissions')->orderBy('module')->orderBy('name')->get(['module', 'name']) as $p) {
    $modules[$p->module][] = $p->name;
}
$permissionTotal = array_sum(array_map('count', $modules));
$moduleTotal = count($modules);
$grantTotal = DB::table('role_permissions')->count();

$roles = [];
foreach (DB::table('role_permissions as rp')
    ->join('roles as r', 'r.id', '=', 'rp.roleId')
    ->join('permissions as p', 'p.id', '=', 'rp.permissionId')
    ->orderBy('p.name')
    ->select('r.name as role', 'p.name as permission')->get() as $g) {
    $roles[$g->role][] = $g->permission;
}
$sysadminId = DB::table('roles')->where('name', 'SysAdmin')->value('id');
$sharedElsewhere = DB::table('role_permissions')->where('roleId', '!=', $sysadminId)->distinct()->pluck('permissionId')->all();
$sysadminOnly = DB::table('permissions')->whereNotIn('id', $sharedElsewhere)->orderBy('name')->pluck('name')->all();

printf("measured: %d permissions across %d modules, %d grants, %d roles, %d SysAdmin-only\n",
    $permissionTotal, $moduleTotal, $grantTotal, count($roles), count($sysadminOnly));

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$parses = function (string $x): bool {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($x);
    libxml_clear_errors();

    return $r !== false;
};

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);
$decode = function (string $cellXml): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cellXml, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode(' ', $m[1] ?? []), ENT_QUOTES | ENT_XML1)));
};

/** All <w:tr> blocks of the table that follows a heading, with absolute offsets. */
$tableRows = function (string $xml, string $heading) use ($decode): array {
    $h = strpos($xml, $heading);
    if ($h === false) {
        fwrite(STDERR, "REFUSE: heading not found: {$heading}\n");
        exit(1);
    }
    $tblStart = strpos($xml, '<w:tr', $h);
    $tblEnd = strpos($xml, '</w:tbl>', $tblStart);
    preg_match_all('/<w:tr\b.*?<\/w:tr>/s', substr($xml, $tblStart, $tblEnd - $tblStart + 8), $rows, PREG_OFFSET_CAPTURE);
    $out = [];
    foreach ($rows[0] as $r) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $r[0], $cells);
        $out[] = ['xml' => $r[0], 'at' => $tblStart + $r[1], 'cells' => array_map($decode, $cells[0]), 'cellXml' => $cells[0]];
    }

    return $out;
};

/** Rebuild one row from new cell texts, keeping each original cell's paragraph/run shell. */
$rebuildRow = function (string $rowXml, array $cellXml, array $newTexts) use ($esc): string {
    $out = $rowXml;
    foreach ($cellXml as $i => $cell) {
        if (! array_key_exists($i, $newTexts) || $newTexts[$i] === null) {
            continue;
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$esc($newTexts[$i]).'</w:t></w:r></w:p>', $body);
        $out = str_replace($cell, $new, $out);
    }

    return $out;
};

$edits = [];      // [offset, length, replacement]
$apply_ = function (array $edits, string $xml): string {
    usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);   // descending: earlier offsets stay valid
    $nextStart = PHP_INT_MAX;
    foreach ($edits as $e) {
        [$at, $len] = $e;
        if ($at + $len > $nextStart) {
            throw new RuntimeException(sprintf(
                'edits overlap or share an offset: one ends at %d, the next starts at %d', $at + $len, $nextStart));
        }
        $nextStart = $at;
    }
    foreach ($edits as [$at, $len, $repl]) {
        $xml = substr_replace($xml, $repl, $at, $len);
    }

    return $xml;
};

// ---- 1. §22.2 module catalogue --------------------------------------------------
$rows = $tableRows($xml, '22.2 Permission catalogue');
$byModule = [];
foreach ($rows as $r) {
    if (count($r['cells']) === 3 && $r['cells'][0] !== 'Module' && $r['cells'][0] !== 'Total') {
        $byModule[$r['cells'][0]] = $r;
    }
}
$missing = array_diff(array_keys($modules), array_keys($byModule));
printf("22.2 rows=%d modules=%d  docx is missing: [%s]  docx has but live has not: [%s]\n",
    count($byModule), $moduleTotal, implode(',', $missing), implode(',', array_diff(array_keys($byModule), array_keys($modules))));

foreach ($byModule as $name => $r) {
    if (! isset($modules[$name])) {
        continue;   // handled by deletion below
    }
    $want = [null, (string) count($modules[$name]), implode(', ', $modules[$name])];
    if ($r['cells'][1] === $want[1] && $r['cells'][2] === $want[2]) {
        continue;
    }
    $edits[] = [$r['at'], strlen($r['xml']), $rebuildRow($r['xml'], $r['cellXml'], $want)];
}

// delete the retired dashboard module row
if (! isset($byModule['dashboard'])) {
    fwrite(STDERR, "REFUSE: expected a dashboard row to delete\n");
    exit(1);
}
$r = $byModule['dashboard'];
if ($r['cells'] !== ['dashboard', '1', 'dashboard.view']) {
    fwrite(STDERR, "REFUSE: dashboard row is not the one-permission row expected: [".implode('|', $r['cells'])."]\n");
    exit(1);
}
$edits[] = [$r['at'], strlen($r['xml']), ''];

// add the shift module row, cloned from the students row (the engine cannot invent a <w:tr>)
$students = $byModule['students'] ?? null;
if ($students === null) {
    fwrite(STDERR, "REFUSE: no students row to clone for the shift module\n");
    exit(1);
}
$shiftRow = $rebuildRow($students['xml'], $students['cellXml'], ['shift', (string) count($modules['shift']), implode(', ', $modules['shift'])]);
if (! str_contains($shiftRow, 'shift.request.create')) {
    fwrite(STDERR, "REFUSE: cloned shift row lost its names\n");
    exit(1);
}

// Total row — and the cloned shift row rides in front of it. The insert used to be its own edit at
// the offset where the students row ends, which is exactly where the Total row begins: two edits on
// one offset, applied in the wrong order, and the second one cut the first in half. One edit cannot
// collide with itself.
foreach ($rows as $r) {
    if (($r['cells'][0] ?? '') === 'Total') {
        $edits[] = [$r['at'], strlen($r['xml']), $shiftRow.$rebuildRow($r['xml'], $r['cellXml'], [null, (string) $permissionTotal])];
    }
}

// ---- 2. §22.3 roles -------------------------------------------------------------
$rows = $tableRows($xml, '22.3 Roles and the permissions each one holds');
$touched = 0;
foreach ($rows as $r) {
    $role = $r['cells'][0] ?? '';
    if (! isset($roles[$role]) || count($r['cells']) !== 4) {
        continue;
    }
    if ($role === 'SysAdmin') {
        // Its permissions cell is prose ("Every one of the N permissions …"); patch the number only.
        $cell = $r['cellXml'][3];
        $newCell = str_replace($esc('Every one of the 94 permissions'), $esc('Every one of the '.$permissionTotal.' permissions'), $cell);
        if ($cell === $newCell) {
            fwrite(STDERR, "REFUSE: SysAdmin row does not carry the 94-permission sentence\n");
            exit(1);
        }
        $edits[] = [$r['at'], strlen($r['xml']), str_replace($cell, $newCell, $r['xml'])];
        $touched++;

        continue;
    }
    $want = [null, null, (string) count($roles[$role]), implode(', ', $roles[$role])];
    if ($r['cells'][2] === $want[2] && $r['cells'][3] === $want[3]) {
        continue;
    }
    $edits[] = [$r['at'], strlen($r['xml']), $rebuildRow($r['xml'], $r['cellXml'], $want)];
    $touched++;
}
printf("22.3 role rows rewritten: %d of %d\n", $touched, count($roles));

// ---- 3. §22.5 SysAdmin-only list ------------------------------------------------
$rows = $tableRows($xml, '22.5 What only SysAdmin can do');
$listed = [];
foreach ($rows as $r) {
    $name = $r['cells'][0] ?? '';
    if (count($r['cells']) >= 2 && $name !== 'Permission' && preg_match('/^[\w.]+$/', $name)) {
        $listed[$name] = $r;
    }
}
foreach ($listed as $name => $r) {
    if (in_array($name, $sysadminOnly, true)) {
        continue;
    }
    printf("22.5: %s is no longer SysAdmin-only — removing its row\n", $name);
    $edits[] = [$r['at'], strlen($r['xml']), ''];
}

// ---- 4. prose claims ------------------------------------------------------------
$prose = [
    ['94 permissions across 15 modules', $permissionTotal.' permissions across '.$moduleTotal.' modules', 2],
    ['95 permission definitions in 15 modules', $permissionTotal.' permission definitions in '.$moduleTotal.' modules', 1],
    ['These 25 permissions are defined in the catalogue but granted to no role other than SysAdmin',
        'These '.count($sysadminOnly).' permissions are defined in the catalogue but granted to no role other than SysAdmin', 1],
    ['The live catalogue is 94 permissions and 315 grants after the reseed',
        'The live catalogue is '.$permissionTotal.' permissions and '.$grantTotal.' grants after the reseed', 1],
    ['(dashboard.view, granted to 17 of 17 roles, awaiting the owner\'s ruling like #47)',
        '(dashboard.view, granted to 17 of 17 roles; the owner ruled it retired the same day and its whole module went with it, '
        .'taking the live catalogue to '.$permissionTotal.' permissions in '.$moduleTotal.' modules and '.$grantTotal.' grants)', 1],
];

// Every target was first measured as a CONTIGUOUS string in the raw XML. Sentences in this document
// are split across <w:t> runs, so a target copied from the flattened dump matches nothing at all —
// the first draft of this pass refused for exactly that reason, which is the same failure mode the
// B-3 cell had in pass 72.
foreach ($prose as [$from, $to, $want]) {
    $f = $esc($from);
    $n = substr_count($apply_($edits, $xml), $f);
    if ($n !== $want) {
        fwrite(STDERR, "REFUSE: prose target seen {$n} times, want {$want}: '".mb_substr($from, 0, 64)."…'\n");
        exit(1);
    }
}

$newXml = $apply_($edits, $xml);
foreach ($prose as [$from, $to, $want]) {
    $newXml = str_replace($esc($from), $esc($to), $newXml);
    if (substr_count($newXml, $esc($from)) !== 0 || substr_count($newXml, $esc($to)) !== $want) {
        fwrite(STDERR, "REFUSE: prose replacement did not land cleanly: '".mb_substr($from, 0, 60)."…'\n");
        exit(1);
    }
}

// DIAGNOSTIC: apply the edits cumulatively and report the first one that breaks the parse.
$sorted = $edits;
usort($sorted, fn ($a, $b) => $b[0] <=> $a[0]);
$probe = $xml;
foreach ($sorted as $k => [$at, $len, $repl]) {
    $probe = substr_replace($probe, $repl, $at, $len);
    if (! $parses($probe)) {
        fwrite(STDERR, "FIRST BROKEN EDIT #{$k} at {$at} len {$len}
");
        exit(1);

        exit(1);
    }
}
echo "all ".count($sorted)." edits parse cleanly on their own
";

// ---- guards ---------------------------------------------------------------------
if (! $parses($newXml)) {
    fwrite(STDERR, "REFUSE: document.xml does not parse\n");
    exit(1);
}
$flat = html_entity_decode(preg_replace('/<[^>]+>/', ' ', $newXml), ENT_QUOTES | ENT_XML1);
$flat = preg_replace('/\s+/', ' ', $flat);

$mustAbsent = ['94 permissions across 15 modules', '95 permission definitions',
    'These 25 permissions', 'awaiting the owner\'s ruling like #47',
    'Every one of the 94 permissions'];
foreach ($mustAbsent as $bad) {
    if (substr_count($flat, $bad) !== 0) {
        fwrite(STDERR, "REFUSE: stale claim survives: {$bad}\n");
        exit(1);
    }
}
// dashboard.view must now be named exactly once in the whole document — in the revision row that
// records its retirement. Any other copy is a table that was not regenerated.
if (substr_count($flat, 'dashboard.view') !== 1) {
    fwrite(STDERR, "REFUSE: dashboard.view appears ".substr_count($flat, 'dashboard.view')." times, want 1\n");
    exit(1);
}
// A retired right may still be *described* in prose — the registers and the revision rows do exactly
// that — but it must appear in no catalogue cell. Counting document-wide occurrences was the wrong
// test: it fails on legitimate history (four prose mentions) and would pass on a stale table row.
foreach (['dashboard.view', 'exam.verify.general'] as $retired) {
    foreach (['22.2 Permission catalogue', '22.3 Roles and the permissions each one holds',
              '22.5 What only SysAdmin can do'] as $h) {
        foreach ($tableRows($newXml, $h) as $r) {
            foreach ($r['cells'] as $c) {
                if (str_contains($c, $retired)) {
                    fwrite(STDERR, "REFUSE: {$retired} still listed in a {$h} cell: [".mb_substr($c, 0, 90)."…]\n");
                    exit(1);
                }
            }
        }
    }
}
$mustPresent = [$permissionTotal.' permissions across '.$moduleTotal.' modules',
    $permissionTotal.' permission definitions in '.$moduleTotal.' modules',
    'These '.count($sysadminOnly).' permissions are defined in the catalogue'];
foreach ($mustPresent as $good) {
    if (substr_count($flat, $good) < 1) {
        fwrite(STDERR, "REFUSE: expected rebuilt claim absent: {$good}\n");
        exit(1);
    }
}
// §22.2 rows must now sum to the measured total, and every live module must have exactly one row.
$rowsAfter = $tableRows($newXml, '22.2 Permission catalogue');
$sum = 0;
$names = [];
foreach ($rowsAfter as $r) {
    if (count($r['cells']) === 3 && ! in_array($r['cells'][0], ['Module', 'Total'], true)) {
        $names[] = $r['cells'][0];
        $sum += (int) $r['cells'][1];
        if ($r['cells'][2] !== implode(', ', $modules[$r['cells'][0]] ?? ['zzz'])) {
            fwrite(STDERR, "REFUSE: module {$r['cells'][0]} names do not match the live catalogue\n");
            exit(1);
        }
    }
}
if ($sum !== $permissionTotal || array_diff($names, array_keys($modules)) !== [] || count($names) !== $moduleTotal) {
    fwrite(STDERR, "REFUSE: 22.2 rows sum to {$sum} for ".count($names)." modules; live is {$permissionTotal} in {$moduleTotal}\n");
    exit(1);
}
printf("guards  22.2 sums to %d across %d modules; %d role rows verified against role_permissions\n",
    $sum, count($names), count($roles));

printf("changes: %d row-level edits, document.xml %d -> %d bytes\n", count($edits), strlen($xml), strlen($newXml));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}
copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass74.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();
$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();
if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: disk does not hold the edited document.xml\n");
    exit(1);
}
echo "Wrote {$src}\n";
