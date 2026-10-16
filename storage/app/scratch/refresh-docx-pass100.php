<?php

/**
 * Pass 100 — the walkthrough's first CI run went red on a database the seeders had filled seconds earlier.
 *
 * Pass 99 documented that the legs got a MySQL step and how they skip. It could not yet say what happened
 * once the step actually ran, because that run was the next morning's failure: the step is deliberately
 * last, so it inherits a schema the suite already used, and a name-keyed seeder's ids follow a counter that
 * a rollback does not rewind. Every figure here was measured on a rehearsal database driven in CI's own
 * order, and the two different failure messages are quoted from CI's own log for commit 7d897d3.
 */
$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$edits = [
    [
        'the same subtraction of proof, in a new place. ',
        'the same subtraction of proof, in a new place. '
        .'Running the step then measured something the local runs could not: it went red on commit 7d897d3 '
        .'with four errors against a database the seeders had filled seconds earlier, because the step is '
        .'deliberately last and so inherits a schema the suite had already reset and then dirtied. '
        .'RefreshDatabase migrates fresh once at the start of its run, which is why the table was empty, and '
        .'every insert a test rolled back had still spent an AUTO_INCREMENT value, which is why the counter '
        .'sat at 53; and because StarterReferenceDataSeeder creates religions by name, their ids follow that '
        .'counter. Driven in CI’s own order against a rehearsal database, the eight seeders landed on 53 '
        .'through 60. Two legs died on students_religionid_foreign; the two that post to the intake route '
        .'were refused by its exists:religions,religionId rule and reported only Laravel’s own assertion '
        .'helper failing, so one cause read two ways in the same log. A leg now asks the table for a '
        .'religion instead of assuming id 1, religions joined the list it checks before running, and the '
        .'class runs with exception handling off — which is how the same poisoned id fails as The selected '
        .'religion id is invalid. from AdmissionController::store(), the sentence the application had been '
        .'trying to print all along. Courses and terms keep their literal ids, because DevReferenceDataSeeder '
        .'pins those explicitly and an insert that names its own primary key is not at the mercy of the '
        .'counter. ',
        1,
        'the walkthrough runs on a schema the suite already used, and what that measured',
    ],
];

$fails = 0;
$statements = [];

foreach ($edits as [$from, $to, $expect, $label]) {
    $count = substr_count($xml, $esc($from));
    if ($count !== $expect) {
        fwrite(STDERR, sprintf("REFUSE [%s]: found %dx, expected %dx\n", $label, $count, $expect));
        $fails++;

        continue;
    }
    $xml = str_replace($esc($from), $esc($to), $xml);
    $statements[] = $label;
    printf("edit ok  %-52s  %dx\n", $label, $expect);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no stray markdown crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the passage still hands off to what follows' => substr_count($xml, $esc('the mercy of the counter. Nothing archives or trims')) === 1,
    'the new claims are present once' => substr_count($xml, $esc('the eight seeders landed on 53 through 60')) === 1
        && substr_count($xml, $esc('The selected religion id is invalid.')) === 1
        && substr_count($xml, $esc('religions joined the list it checks before running')) === 1
        && substr_count($xml, $esc('went red on commit 7d897d3')) === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

printf("\n%d statement(s) verified; %s\n", count($statements), $fails === 0 ? 'all edits and guards ok' : "{$fails} problem(s)");

if ($fails > 0) {
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-12-pass100.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
