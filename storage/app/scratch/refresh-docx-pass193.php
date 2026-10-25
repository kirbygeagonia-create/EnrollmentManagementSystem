<?php

/**
 * Pass 193 — the §21.2 inventory's "Seeded?" column, named and re-pointed.
 *
 * Those cells carry a bare number each: `courses | Yes — CRUD | Yes ( :162 )`. Their owning file is stated once
 * for the whole table, in the sentence above the header — "Seeded = created by DevReferenceDataSeeder" — which
 * no row-scoped checker can see, so none of the nine ever had its bounds checked. Resolving each table to its
 * model and finding the model's create call in the seeder gave the truth: three rows were right, five were not.
 *
 *   courses               :162 -> :198   (Courses::firstOrCreate; :162 is a closing `];`)
 *   majors                :189 -> :271   (Majors::firstOrCreate; :189 is a comment about a dropped program)
 *   curriculums           :592 -> :674   (Curriculums::firstOrCreate; :592 is room-section data)
 *   curriculumsubjects    :608 -> :690   (Curriculumsubjects::updateOrCreate; :608 is a course map entry)
 *   admissionrequirements :647 -> :732   (Admissionrequirements::create; :647 is section data)
 *   academicyears         :137/:141/:148 already correct — three Academicyears::firstOrCreate calls — only unnamed
 *
 * A needle that is not unique document-wide is skipped, never guessed: the seeder's line numbers collide with
 * citations to other files elsewhere in the document, and this repository has already been caught once by a
 * "fix" that rewrote a neighbour's pointer. `lane70-seeder-cites.php` is the read-only version of this check.
 *
 *   php storage/app/scratch/refresh-docx-pass193.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass193.php --apply  # write
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$say = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'BAD ', $what);
    if (! $ok) {
        $fails++;
    }
};

$zip = new ZipArchive;
if ($zip->open($src) !== true) {
    echo "REFUSED: cannot open the docx.\n";
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// Verified against database/seeders/DevReferenceDataSeeder.php, whose create calls this line list names.
$seeder = file($root.'/database/seeders/DevReferenceDataSeeder.php');
foreach ([198 => 'Courses::', 271 => 'Majors::', 674 => 'Curriculums::', 690 => 'Curriculumsubjects::',
    732 => 'Admissionrequirements::', 137 => 'Academicyears::', 141 => 'Academicyears::', 148 => 'Academicyears::'] as $ln => $needle) {
    $say(str_contains((string) $seeder[$ln - 1], $needle), "seeder :{$ln} is a {$needle}create call");
}

$edits = [
    [':162', 'DevReferenceDataSeeder.php:198'],
    [':189', 'DevReferenceDataSeeder.php:271'],
    [':592', 'DevReferenceDataSeeder.php:674'],
    [':608', 'DevReferenceDataSeeder.php:690'],
    [':647', 'DevReferenceDataSeeder.php:732'],
    [':137', 'DevReferenceDataSeeder.php:137'],
];

$accepted = [];
foreach ($edits as [$old, $new]) {
    $n = substr_count($xml, $old);
    if ($n === 1) {
        $accepted[] = [$old, $new];
        printf("  ok   %-10s unique -> %s\n", $old, $new);
    } else {
        printf("  skip %-10s occurs %d times document-wide\n", $old, $n);
    }
}
$say(count($accepted) >= 3, 'enough of the column is addressable to be worth writing ('.count($accepted).')');

$staged = $xml;
foreach ($accepted as [$old, $new]) {
    $staged = str_replace($old, $new, $staged);
}
foreach ($accepted as [$old, $new]) {
    $say(str_contains($staged, $new), 'written: '.$new);
    foreach (['<', '>', '&'] as $ch) {
        $say(! str_contains($new, $ch), $new.' carries no raw '.$ch);
    }
}
$say(substr_count($xml, '<w:tbl>') === substr_count($staged, '<w:tbl>'), 'no table moved');
$say(@(new DOMDocument)->loadXML($staged) !== false, 'the staged document.xml parses');
printf("\n  %d edit(s); staged byte delta: %+d\n", count($accepted), strlen($staged) - strlen($xml));
printf("%d guard(s) failed.\n", $fails);
if ($fails > 0) {
    echo "NOTHING WRITTEN.\n";
    exit(1);
}
if (! $apply) {
    echo "\nDRY RUN: the Seeded? column would name the seeder.\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass193.docx');
$z = new ZipArchive;
if ($z->open($src) !== true) {
    echo "REFUSED: the docx cannot be reopened for the write — nothing written.\n";
    exit(1);
}
if ($z->getFromName('word/document.xml') !== $xml) {
    echo "REFUSED: document.xml changed under this pass since it was read — nothing written.\n";
    exit(1);
}
$z->addFromString('word/document.xml', $staged);
$z->close();
echo "WROTE {$src}\n";
