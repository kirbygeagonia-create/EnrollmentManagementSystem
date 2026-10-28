<?php

/**
 * Pass 277: §22 after the RBAC reseed — the desk-head row and the live figures.
 *
 * Two surfaces moved in this issue. `workflow.view` was declared in the seeder (a fifteenth
 * module), and the owner then ruled that live `ems` be reseeded so the two agree again. This pass
 * measures both rather than typing either: the OfficeHead grant list is read out of the seeder
 * block that maintains it, and every live figure is read from the server in this run — the pass
 * refuses outright when the server still answers ninety-five, because then the reseed it assumes
 * has not happened and half of §22 would be rewritten on a premise.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass277.php [--apply]
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

require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'ems']);
Illuminate\Support\Facades\DB::purge('mysql');

$counts = [
    'permissions' => (int) Illuminate\Support\Facades\DB::table('permissions')->where('guard_name', 'web')->count(),
    'modules' => (int) Illuminate\Support\Facades\DB::table('permissions')->where('guard_name', 'web')->distinct()->count('module'),
    'grants' => (int) Illuminate\Support\Facades\DB::table('role_permissions')->count(),
    'roles' => (int) Illuminate\Support\Facades\DB::table('roles')->where('guard_name', 'web')->count(),
];

printf("live ems: %d permissions across %d modules, %d role grants, %d roles\n",
    $counts['permissions'], $counts['modules'], $counts['grants'], $counts['roles']);

if ($counts['permissions'] !== 96 || $counts['modules'] !== 15) {
    fwrite(STDERR, 'REFUSED: the server answers '.$counts['permissions'].' rights in '.$counts['modules']
        ." modules, not 96 in 15 — the RBAC reseed has not run, so §22's live sentences must stay as written.\n");

    exit(1);
}

// The OfficeHead seat, read from the seeder text that maintains it (the same source §22 claims).
$seeder = (string) file_get_contents($root.'/database/seeders/RbacSeeder.php');
if (! preg_match('/\$officeHead->syncPermissions\(\[(.*?)\]\);/s', $seeder, $m)) {
    fwrite(STDERR, "REFUSED: the OfficeHead syncPermissions block was not found — re-read the seeder.\n");

    exit(1);
}
preg_match_all("/'([a-z][A-Za-z.]*\.[A-Za-z][A-Za-z.]*)'/", $m[1], $pm);
$seats = array_values(array_unique($pm[1]));
sort($seats);
if (! in_array('workflow.view', $seats, true)) {
    fwrite(STDERR, "REFUSED: the seeder does not seat workflow.view with OfficeHead.\n");

    exit(1);
}
$seatCount = count($seats);
printf("seeder seats OfficeHead with %d rights; last in order: %s\n", $seatCount, end($seats));

$zip = new ZipArchive;

if ($zip->open($docx) !== true) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");

    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$flatten = static function (string $fragment): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $fragment, $tm);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)));
};

$escape = static fn (string $s): string => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$rowText = static function (string $row) use ($flatten): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm);

    return implode(' | ', array_map($flatten, $cm[0]));
};

$writeCell = static function (string $row, int $index, string $text) use ($flatten, $escape): string {
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);
    if (! isset($cm[0][$index])) {
        fwrite(STDERR, "REFUSED: the row has no cell {$index} (it has ".count($cm[0]).").\n");

        exit(1);
    }
    $out = $row;
    $targets = [];
    foreach ($cm[0] as $j => $x) {
        $targets[] = [$j, (int) $x[1], strlen($x[0]), $x[0]];
    }
    usort($targets, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
    foreach ($targets as [$j, $at, $len, $src]) {
        if ($j !== $index) {
            continue;
        }
        $cell = $src;
        preg_match_all('/<w:p>.*?<\/w:p>/s', $cell, $pm2);
        foreach (array_slice($pm2[0], 1) as $extra) {
            if ($flatten($extra) !== '') {
                fwrite(STDERR, "REFUSED: cell {$index} holds a second paragraph with text.\n");

                exit(1);
            }
            $cell = str_replace($extra, '', $cell);
        }
        preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
        preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
        $body = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
        $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
            .$escape($text).'</w:t></w:r></w:p>', $body);
        $out = substr_replace($out, $new, $at, $len);
    }
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $out, $check);
    if ($flatten($check[0][$index]) !== $text) {
        fwrite(STDERR, "REFUSED: cell {$index} came back as [".$flatten($check[0][$index])."].\n");

        exit(1);
    }

    return $out;
};

// ── 1. the OfficeHead role row: 43 → 44 rights, and the new one named in its list ────────────
$hits = [];
$at = 0;
while (($start = strpos($xml, '<w:tr', $at)) !== false) {
    $end = strpos($xml, '</w:tr>', $start);
    if ($end === false) {
        break;
    }
    $end += 7;
    $row = substr($xml, $start, $end - $start);
    if (strncmp($rowText($row), 'OfficeHead', 10) === 0) {
        $hits[] = [$start, $end, $row];
    }
    $at = $end;
}
if (count($hits) !== 1) {
    fwrite(STDERR, 'REFUSED: the OfficeHead role row matched '.count($hits)." row(s), expected 1.\n");

    exit(1);
}
[$oStart, $oEnd, $oRow] = $hits[0];
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $oRow, $oc);
$cells = array_map($flatten, $oc[0]);
$countIndex = null;
$listIndex = null;
foreach ($cells as $i => $text) {
    if ($text === '43') {
        $countIndex = $i;
    }
    if (str_contains($text, 'admission.view') && str_contains($text, 'user.view')) {
        $listIndex = $i;
    }
}
if ($countIndex === null || $listIndex === null) {
    fwrite(STDERR, "REFUSED: the OfficeHead row's count or list cell was not found among ".count($cells)." cells.\n");

    exit(1);
}
$list = $cells[$listIndex];
if (! str_ends_with($list, 'user.view') || str_contains($list, 'workflow.view')) {
    fwrite(STDERR, "REFUSED: the OfficeHead list cell does not end where this pass expects: [".mb_substr($list, -60)."]\n");

    exit(1);
}
$newRow = $writeCell($oRow, $listIndex, $list.', workflow.view');
$newRow = $writeCell($newRow, $countIndex, (string) $seatCount);
if ($rowText($newRow) === $rowText($oRow)) {
    fwrite(STDERR, "REFUSED: the OfficeHead row did not change.\n");

    exit(1);
}
$xml = substr_replace($xml, $newRow, $oStart, $oEnd - $oStart);

// ── 2. the live-catalogue sentences, typed from the server readings above ────────────────────
$textEdits = [
    ['The live catalogue is 95 permissions and 305 grants after the reseed',
     'The live catalogue is '.$counts['permissions'].' permissions and '.$counts['grants'].' grants after the reseed'],
    ['live after the reseed (95 permissions across 14 modules)',
     'live after the reseed ('.$counts['permissions'].' permissions across '.$counts['modules'].' modules)'],
    ['the permission set 95,', 'the permission set '.$counts['permissions'].','],
    // This batch's own §1.1 row predicted the divergence; the reseed resolved it.
    ['The live database still holds ninety-five '
        .'and its sentences are left as written, because no reseed ran in this batch — re-measure them after one does.',
     'The live catalogue was reseeded the same day, so §22\'s live sentences were re-typed from the server rather than from the seeder.'],
];

foreach ($textEdits as [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n > 1) {
        fwrite(STDERR, "REFUSED: needle occurs {$n} times, expected 0 or 1: [".mb_substr($from, 0, 60)."…]\n");

        exit(1);
    }
    if ($n === 1) {
        $xml = str_replace($from, $to, $xml);
    }
}

foreach ([['<w:p', '</w:p>'], ['<w:t', '</w:t>'], ['<w:tc', '</w:tc>'], ['<w:tr', '</w:tr>'], ['<w:tbl', '</w:tbl>']] as [$open, $close]) {
    $a = preg_match_all('/'.preg_quote($open, '/').'(?=[\s>])/', $xml);
    $b = substr_count($xml, $close);
    if ($a !== $b) {
        fwrite(STDERR, "REFUSED: unbalanced {$open} ({$a} against {$b}).\n");

        exit(1);
    }
}

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses.\n");

    exit(1);
}

if (substr_count($xml, '95 permissions') !== 0) {
    fwrite(STDERR, "REFUSED: a '95 permissions' sentence survived.\n");

    exit(1);
}

printf("%s§22's live sentences and the OfficeHead row re-measured (%d rights seated, %d live)\n",
    $apply ? '' : 'DRY RUN — ', $seatCount, $counts['permissions']);

if (! $apply) {
    echo "DRY RUN — nothing written. Re-run with --apply.\n";

    exit(0);
}

$backup = $root.'/storage/app/scratch/pass277-backup-'.date('Ymd-His').'.docx';

if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup to {$backup}.\n");

    exit(1);
}

$w = new ZipArchive;

if ($w->open($docx, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "REFUSED: cannot reopen for writing. Restore from {$backup}.\n");

    exit(1);
}
$w->addFromString('word/document.xml', $xml);
$w->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();

if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed. Restore from {$backup}.\n");

    exit(1);
}

echo "\npass 277 applied: §22 agrees with the seeder and with the reseeded database.\n";
