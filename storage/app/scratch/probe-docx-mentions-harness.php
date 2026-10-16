<?php
/**
 * Does the authority docx describe the walkthrough test or the CI guard? If it does, the docx has to follow
 * any change to them in the same batch; if it does not, this is a test-harness detail and the docx stays put.
 *
 * Reports the character offset and the surrounding text for each hit so the answer is readable, not a count.
 */
$docx = $argv[1] ?? 'Documentation/EMS Complete Documentation.docx';

if (! is_file($docx)) {
    fwrite(STDERR, "not found: {$docx}\n");
    exit(1);
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "cannot open the docx as a zip\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

echo 'document.xml characters: '.strlen($xml)."\n\n";

$terms = ['EnrollmentWalkthroughTest', 'no-silent-skips', 'AssertNoSilentSkips', 'E2E_DB_DATABASE',
    'fail-on-skipped', 'religionId', 'ubuntu-24.04', 'walkthrough'];

foreach ($terms as $term) {
    $at = strpos($xml, $term);
    if ($at === false) {
        printf("%-26s absent\n", $term);

        continue;
    }
    $count = substr_count($xml, $term);
    $flat = preg_replace('/<[^>]+>/', '', substr($xml, max(0, $at - 300), 700));
    printf("%-26s %d hit(s), first at offset %d\n    …%s…\n\n", $term, $count, $at, trim($flat));
}
