<?php

/**
 * Pass 31 — the two counts that live inside code-styled dictionary paragraphs, and two correction
 * paragraphs for claims the live data has overtaken.
 *
 * The paragraphs are the ones whose sentences alternate prose runs with Consolas runs (a model
 * path, a class name), so a whole-paragraph rewrite would have flattened that typography. Instead
 * each count is replaced inside the single run that holds it, located by the paragraph that owns
 * it; the two corrections are CLONES of their neighbour paragraph, stripped and re-run in the
 * neighbour's own style, which is how the document keeps a new sentence in its own voice.
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

$live = [];
foreach (DB::select("
    select 'staff_roles' t, count(*) n from ems.staff_roles
    union all select 'auditlogs', count(*) from ems.auditlogs") as $r) {
    $live[$r->t] = (int) $r->n;
}
printf("live: staff_roles %d, auditlogs %d\n", $live['staff_roles'], $live['auditlogs']);

$paraBounds = function (string $xml, int $at): array {
    $start = max(strrpos(substr($xml, 0, $at), '<w:p>'), strrpos(substr($xml, 0, $at), '<w:p '));
    $end = strpos($xml, '</w:p>', $at) + 6;

    return [$start, $end];
};

$flat = function (string $para): string {
    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $para, $m);

    return preg_replace('/\s+/', ' ', html_entity_decode(implode('', $m[1]), ENT_QUOTES | ENT_XML1));
};

$edits = [];

// ── 1. the two paragraph-scoped counts ───────────────────────────────────────────────────
// "Rows in the demo database: 28." is written for more than one table, and a model's filename is
// mentioned in several places, so a count is addressed by the ONE paragraph that carries both this
// table's model line and this sentence.
$counts = [
    ['App\Models\StaffRoles', 'Rows in the demo database: 28.', 'Rows in the demo database: '.$live['staff_roles'].'.'],
    ['App\Models\Auditlogs', 'Rows in the demo database: 2858.', 'Rows in the demo database: '.$live['auditlogs'].'.'],
];

preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $allParas, PREG_OFFSET_CAPTURE);

foreach ($counts as [$modelNeedle, $from, $to]) {
    $hits = [];
    foreach ($allParas[0] as $p) {
        $flatText = $flat($p[0]);
        if (str_contains($flatText, $modelNeedle) && str_contains($flatText, $from)) {
            $hits[] = $p;
        }
    }
    if (count($hits) !== 1) {
        fwrite(STDERR, "REFUSE: {$modelNeedle}'s count paragraph matched ".count($hits)." times\n");
        exit(1);
    }
    [$para, $pAt] = $hits[0];
    // The sentence is inside one run, but the run begins with the previous sentence's period, so
    // the replacement is addressed by the text itself rather than by a whole-run match.
    if (substr_count($para, $from) !== 1) {
        fwrite(STDERR, "REFUSE: {$modelNeedle} does not hold \"{$from}\" exactly once\n");
        exit(1);
    }
    $edits[] = [
        'at' => $pAt + strpos($para, $from),
        'len' => strlen($from),
        'to' => $to,
        'label' => $modelNeedle.' count',
    ];
    printf("  %-22s %s → %s\n", $modelNeedle, $from, $to);
}

// ── 2. the two correction paragraphs ─────────────────────────────────────────────────────
$inserts = [
    [
        'anchor' => 'DevReferenceDataSeeder also seeds staff',
        'text' => 'Correction 2026-10-06, read from live ems rather than from this paragraph. Office 8 is'
            .' no longer seeded at all (ruling 12 retired it in data), and office8_head now sits under the'
            .' Registrar — where RbacSeeder step 3 pairs it RegistrarApprover + OfficeHead. So the account'
            .' above does receive a desk role, and it can certify an enrollment: it is no longer the'
            .' negative test this chapter and the acceptance script once used it for. The same re-read found'
            .' that no account on the install is a bare OfficeHead, because every head is paired with their'
            .' own office’s desk role deliberately — that pairing is what keeps each desk’s signature within'
            .' reach. Proving ruling 7 at a desk therefore needs an account that is OfficeHead and nothing'
            .' else, and §2.8 of the acceptance script creates two: one in office 11, one in office 22.',
    ],
    [
        'anchor' => 'the seeder labels its own count two different ways',
        'text' => 'Re-read 2026-10-06, and three things in the table above moved with it. The # and'
            .' Permissions columns are now generated from live role_permissions, ordered by permission name,'
            .' not remembered: ten role counts changed, among them RegistrarApprover 12 → 16 and Dean 24 → 27,'
            .' and IdOfficer’s count was one too high. The description column quotes roles.description except'
            .' where this chapter annotates it — OfficeHead and GuidanceStaff carry the chapter’s own wording,'
            .' which is why the two do not match the seeder string. And RbacSeeder now creates roles with'
            .' updateOrCreate, because firstOrCreate leaves an existing row’s description untouched: that is'
            .' how IdOfficer still read “and card release” after the 2026-09-30 ruling removed card'
            .' production, and how any corrected string would have failed to reach an installed database.',
    ],
];

foreach ($inserts as $i => $ins) {
    $hits = [];
    foreach ($allParas[0] as $p) {
        if (str_contains($flat($p[0]), $ins['anchor'])) {
            $hits[] = $p;
        }
    }
    if (count($hits) !== 1) {
        fwrite(STDERR, "REFUSE: insert {$i} matched ".count($hits)." paragraphs [{$ins['anchor']}]\n");
        exit(1);
    }
    [$para, $pAt] = $hits[0];
    $s = $pAt;
    $e = $pAt + strlen($para);
    preg_match('/<w:r\b[^>]*>.*?<\/w:r>/s', $para, $firstRun);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $firstRun[0] ?? '', $pr);
    $stripped = preg_replace('/<w:r\b[^>]*>.*?<\/w:r>/s', '', $para);
    if (! str_contains($stripped, '</w:p>')) {
        fwrite(STDERR, "REFUSE: insert {$i} has no paragraph end to write into\n");
        exit(1);
    }
    $clone = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .$esc(preg_replace('/\s+/', ' ', $ins['text'])).'</w:t></w:r></w:p>', $stripped);
    $edits[] = ['at' => $e, 'len' => 0, 'to' => $clone, 'label' => "correction paragraph {$i}"];
    printf("  correction paragraph %d cloned from @%d..%d (%d chars)\n", $i, $s, $e, strlen($clone));
}

// ── 3. apply, from the end backwards ─────────────────────────────────────────────────────
usort($edits, fn ($a, $b) => $b['at'] <=> $a['at']);

if (! $apply) {
    printf("\nDRY RUN — %d edits would be written\n", count($edits));
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass31.docx');
foreach ($edits as $ed) {
    $xml = substr_replace($xml, $ed['to'], $ed['at'], $ed['len']);
}

// ── 4. guards ────────────────────────────────────────────────────────────────────────────
$expectedParas = count($inserts);
foreach (['<w:p>' => $expectedParas, '</w:p>' => $expectedParas, '<w:tbl>' => 0, '</w:tbl>' => 0,
    '<w:tr>' => 0, '</w:tr>' => 0, '<w:tc>' => 0, '</w:tc>' => 0] as $tag => $delta) {
    $moved = substr_count($xml, $tag) - substr_count($original, $tag);
    if ($moved !== $delta) {
        fwrite(STDERR, "REFUSE WRITE: {$tag} moved by {$moved}, expected {$delta}\n");
        exit(1);
    }
    printf("guard  %-9s moved by %d as intended\n", $tag, $moved);
}
if (libxml_use_internal_errors(true) && simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSE WRITE: document.xml does not parse\n");
    exit(1);
}
echo "guard  document.xml parses\n";

foreach ($counts as [$modelNeedle, $from, $to]) {
    $found = false;
    preg_match_all('/<w:p\b[^>]*>.*?<\/w:p>/s', $xml, $after, PREG_OFFSET_CAPTURE);
    foreach ($after[0] as $p) {
        $flatText = $flat($p[0]);
        if (str_contains($flatText, $modelNeedle)) {
            if (! str_contains($flatText, $to)) {
                fwrite(STDERR, "REFUSE WRITE: {$modelNeedle}'s paragraph does not read \"{$to}\"\n");
                exit(1);
            }
            $found = true;
        }
    }
    if (! $found) {
        fwrite(STDERR, "REFUSE WRITE: no paragraph names {$modelNeedle} after the write\n");
        exit(1);
    }
    printf("guard  %-22s reads its new count in its own paragraph\n", $modelNeedle);
}
foreach ($inserts as $i => $ins) {
    $probe = mb_substr(preg_replace('/\s+/', ' ', $ins['text']), 0, 40);
    if (substr_count(preg_replace('/\s+/', ' ', strip_tags($xml)), $probe) !== 1) {
        fwrite(STDERR, "REFUSE WRITE: correction paragraph {$i} is not present exactly once\n");
        exit(1);
    }
    printf("guard  correction paragraph %d sits in the document once\n", $i);
}

$z = new ZipArchive();
$z->open($src, ZipArchive::CREATE);
$z->addFromString('word/document.xml', $xml);
$z->close();

echo "\nWROTE {$src}\n";
