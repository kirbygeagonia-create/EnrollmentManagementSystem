<?php
/**
 * Does §22.3's description column still quote roles.description? Pass 31 claimed two rows are the
 * chapter's own wording; that assertion needs the same evidence as everything else in here.
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

$live = [];
foreach (DB::select("select name, description from roles where guard_name='web'") as $r) {
    $live[$r->name] = preg_replace('/\s+/', ' ', $r->description);
}

$z = new ZipArchive();
$z->open(dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$cellText = function (string $x): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $x, $m);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1)));
};

preg_match_all('/<w:tbl>.*?<\/w:tbl>/s', $xml, $tables, PREG_OFFSET_CAPTURE);
foreach ($tables[0] as $t) {
    preg_match('/<w:tr>.*?<\/w:tr>/s', $t[0], $first);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $first[0], $cs);
    if (array_map($cellText, $cs[0]) !== ['Role', 'Seeded purpose', '#', 'Permissions']) {
        continue;
    }
    preg_match_all('/<w:tr>.*?<\/w:tr>/s', $t[0], $rows);
    foreach ($rows[0] as $row) {
        preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $c2);
        $v = array_map($cellText, $c2[0]);
        if (count($v) !== 4 || ! isset($live[$v[0]])) {
            continue;
        }
        $doc = trim($v[1]);
        printf("%-22s %s\n", $v[0], $doc === $live[$v[0]] ? 'quoted verbatim'
            : "ANNOTATED — live reads: {$live[$v[0]]}");
    }

    break;
}
