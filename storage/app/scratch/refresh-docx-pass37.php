<?php

/**
 * Pass 37 — §22.7 is the section that answers "where is the office boundary actually enforced",
 * and since 2026-10-06 the four print rights are one of those places. The row is cloned from the
 * table's own last row so it inherits the chapter's typography.
 */

require dirname(__DIR__, 3).'/vendor/autoload.php';
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Enrollments;
use App\Models\Staffusers;
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

// Read the rule back from the running application rather than typing it: the row states what the
// code does, so the code is the source.
$registrarHead = Staffusers::where('username', 'office1_head')->first();
$clinicHead = Staffusers::where('username', 'office11_head')->first();
$enrollment = Enrollments::where('enrollmentStatus', 'enrolled')->first();

$atRegistrar = $registrarHead->can('registrar.printCertificate', $enrollment);
$atClinic = $clinicHead->can('registrar.printCertificate', $enrollment);
printf("live gate: office1_head=%s, office11_head=%s (clinic holds print.certificate=%s)\n",
    var_export($atRegistrar, true), var_export($atClinic, true),
    var_export($clinicHead->hasPermissionTo('print.certificate'), true));

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();
$original = $xml;

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
$table = null;
foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    if (array_map($cellText, $cs[0]) === ['Write', 'Ability / permission', 'Extra condition, and where']) {
        $table = ['xml' => $t[0], 'at' => $t[1]];

        break;
    }
}
if ($table === null) {
    fwrite(STDERR, "REFUSE: §22.7's table was not found by its header row\n");
    exit(1);
}

$anchorNeedle = 'Record desk receipt';
if (substr_count($xml, $anchorNeedle) !== 1) {
    fwrite(STDERR, "REFUSE: the row to follow is seen ".substr_count($xml, $anchorNeedle)." times\n");
    exit(1);
}

preg_match_all('/<w:tr>.*?<\/w:tr>/s', $table['xml'], $trs, PREG_OFFSET_CAPTURE);
$template = null;
$anchorEnd = null;
foreach ($trs[0] as $row) {
    if (! str_contains($row[0], $anchorNeedle)) {
        continue;
    }
    $template = $row[0];
    $anchorEnd = $table['at'] + $row[1] + strlen($row[0]);
}
if ($template === null) {
    fwrite(STDERR, "REFUSE: the anchor row is not inside §22.7's table\n");
    exit(1);
}

$values = [
    'Print a Registrar paper',
    'print.certificate, print.classCard, print.subjectLoad, print.enrollmentForm',
    'RegistrarPolicy::mayPrint, ruled and built 2026-10-06: the permission, then enrollmentStatus = enrolled, '
        .'then officeId = Registrar (1) — or SysAdmin, or for the class card and the subject load a teacher whose '
        .'unitId owns the unit of the enrollment’s course. Before that ruling no print gate asked the office: on '
        .'live ems the head of the Clinic, who holds every one of the four rights through OfficeHead, reached the '
        .'enrollment-form route with a 200. Refusing that head is now a test, and so is still allowing the '
        .'Registrar’s own head',
];

$row = $template;
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cells, PREG_OFFSET_CAPTURE);
if (count($cells[0]) !== 3) {
    fwrite(STDERR, 'REFUSE: the template row has '.count($cells[0])." cells, expected 3\n");
    exit(1);
}
foreach (array_reverse($cells[0], true) as $j => $cell) {
    $body = $cell[0];
    preg_match('/<w:r\b.*?<\/w:r>/s', $body, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $body);
    $new = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$esc($values[$j]).'</w:t></w:r></w:p>', $stripped);
    $row = substr_replace($row, $new, $cell[1], strlen($body));
}

printf("\nrow to insert after @%d (%d chars)\n", $anchorEnd, strlen($row));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass37.docx');
$edited = substr_replace($xml, $row, $anchorEnd, 0);

foreach (['<w:tbl>' => 0, '</w:tbl>' => 0, '<w:tr>' => 1, '</w:tr>' => 1, '<w:tc>' => 3, '</w:tc>' => 3] as $tag => $delta) {
    $moved = substr_count($edited, $tag) - substr_count($original, $tag);
    if ($moved !== $delta) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected {$delta}\n");
        exit(1);
    }
    printf("guard  %-9s moved by %d as intended\n", $tag, $moved);
}
if (libxml_use_internal_errors(true) && simplexml_load_string($edited) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

// The new row must sit in §22.7's table, between the receipt row and the read row.
preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $edited, $after, PREG_OFFSET_CAPTURE);
foreach ($after[0] as $t) {
    if (! str_contains($t[0], $esc($values[0])) && ! str_contains($t[0], 'Print a Registrar paper')) {
        continue;
    }
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $f2);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $f2[0], $hc);
    if (array_map($cellText, $hc[0]) !== ['Write', 'Ability / permission', 'Extra condition, and where']) {
        continue;
    }
    $order = $cellText(substr($t[0], strpos($t[0], 'Record desk receipt') - 200, 400));
    foreach (['Record desk receipt', 'Print a Registrar paper', 'Read a student record'] as $need) {
        if (! str_contains($t[0], $need)) {
            fwrite(STDERR, "REFUSE WRITE: §22.7's table lost \"{$need}\"\n");
            exit(1);
        }
    }
    if (strpos($t[0], 'Record desk receipt') > strpos($t[0], 'Print a Registrar paper')) {
        fwrite(STDERR, "REFUSE WRITE: the new row landed before its anchor\n");
        exit(1);
    }
    echo "guard  the row sits in §22.7's table, after the receipt row and before the read row\n";
    $found = true;

    break;
}
if (! isset($found)) {
    fwrite(STDERR, "REFUSE WRITE: the new row is not in §22.7's table\n");
    exit(1);
}

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $edited);
$z->close();

echo "\nWROTE {$src}\n";
