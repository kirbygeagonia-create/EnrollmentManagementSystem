<?php
/**
 * Lane 69, second half: put every desk surface that spells a year level onto the one JS formatter
 * (formatYearLevel / yearLevelChoices in resources/js/Components/ui/statusLabel.js).
 *
 * Five pick-lists hand-wrote `Year ${n}` labels and four cells printed the bare number while three
 * sibling tables already read the formatter — so one number sat on the same screen in three vocabularies.
 * Every edit asserts its own exact match count, and the run validates the whole batch before writing a
 * byte, so a needle that misses cannot leave half the tree moved.
 */

$root = dirname(__DIR__, 3);

$imports = [
    'resources/js/Pages/Blocking/Index.jsx' => [
        "StatCard, formatYearLevel } from '@/Components/ui';",
        "StatCard, formatYearLevel, yearLevelChoices } from '@/Components/ui';",
    ],
    'resources/js/Pages/Evaluation/Index.jsx' => [
        "academicStandingToneFor, formatYearLevel } from '@/Components/ui';",
        "academicStandingToneFor, formatYearLevel, yearLevelChoices } from '@/Components/ui';",
    ],
    'resources/js/Pages/Evaluation/ShiftRequests.jsx' => [
        "FilterBarField, formatStatusLabel } from '@/Components/ui';",
        "FilterBarField, formatStatusLabel, formatYearLevel, yearLevelChoices } from '@/Components/ui';",
    ],
    'resources/js/Pages/Admin/ReferenceData/CurriculumSubjects.jsx' => [
        "FormSection, RadioCards } from '@/Components/ui';",
        "FormSection, RadioCards, formatYearLevel, yearLevelChoices } from '@/Components/ui';",
    ],
    'resources/js/Pages/Admin/ReferenceData/Blocks.jsx' => [
        "EmptyState, FormSection } from '@/Components/ui';",
        "EmptyState, FormSection, formatYearLevel, yearLevelChoices } from '@/Components/ui';",
    ],
];

$optionList = "options={[\n                                    { value: 1, label: 'Year 1' },\n                                    { value: 2, label: 'Year 2' },\n                                    { value: 3, label: 'Year 3' },\n                                    { value: 4, label: 'Year 4' },\n                                    { value: 5, label: 'Year 5' },\n                                ]}";

$edits = [
    ['resources/js/Components/ui/index.js',
        'export { formatStatusLabel, formatYearLevel, enrollmentStatusTone,',
        'export { formatStatusLabel, formatYearLevel, yearLevelChoices, YEAR_LEVELS, enrollmentStatusTone,',
        1],
];

$edits[] = ['resources/js/Pages/Blocking/Index.jsx',
    "const yearLevelOptions = [\n    { value: '', label: 'All Year Levels' },\n    { value: '1', label: 'Year 1' },\n    { value: '2', label: 'Year 2' },\n    { value: '3', label: 'Year 3' },\n    { value: '4', label: 'Year 4' },\n    { value: '5', label: 'Year 5' },\n];",
    "// The filter value travels in the query string, so it stays a string; the label comes from the\n// one formatter, so what a desk picks is what its own table column prints.\nconst yearLevelOptions = [\n    { value: '', label: 'All Year Levels' },\n    ...yearLevelChoices().map((choice) => ({ value: String(choice.value), label: choice.label })),\n];",
    1];

foreach ($imports as $rel => [$old, $new]) {
    $edits[] = [$rel, $old, $new, 1];
}

foreach (['resources/js/Pages/Evaluation/Index.jsx', 'resources/js/Pages/Evaluation/ShiftRequests.jsx'] as $rel) {
    $edits[] = [$rel, 'options={[1, 2, 3, 4, 5].map((n) => ({ value: n, label: `Year ${n}` }))}', 'options={yearLevelChoices()}', 1];
}

$edits[] = ['resources/js/Pages/Evaluation/ShiftRequests.jsx',
    '{r.yearLevel ? ` · Year ${r.yearLevel}` : \'\'}',
    '{r.yearLevel ? ` · ${formatYearLevel(r.yearLevel)}` : \'\'}',
    1];

foreach (['resources/js/Pages/Admin/ReferenceData/CurriculumSubjects.jsx', 'resources/js/Pages/Admin/ReferenceData/Blocks.jsx'] as $rel) {
    $edits[] = [$rel,
        "{ key: 'yearLevel', label: 'Year Level', className: 'text-center' },",
        "{ key: 'yearLevel', label: 'Year Level', className: 'text-center', render: (row) => formatYearLevel(row.yearLevel) },",
        1];
    $edits[] = [$rel, $optionList, 'options={yearLevelChoices()}', 1];
}

$edits[] = ['resources/js/Pages/Evaluation/Show.jsx',
    '<span className="font-semibold text-slate-800">Year {enrollment.yearLevel}</span>',
    '<span className="font-semibold text-slate-800">{formatYearLevel(enrollment.yearLevel)}</span>',
    1];
$edits[] = ['resources/js/Pages/Evaluation/Show.jsx',
    '<td className="px-3 py-2 text-slate-600">{row.yearLevel}</td>',
    '<td className="px-3 py-2 text-slate-600">{formatYearLevel(row.yearLevel)}</td>',
    1];

$failures = [];
$contents = [];
$applied = 0;

foreach ($edits as [$rel, $old, $new, $want]) {
    if (! isset($contents[$rel])) {
        $contents[$rel] = (string) file_get_contents($root.'/'.$rel);
    }
    $have = substr_count($contents[$rel], $old);
    if ($have === 0 && substr_count($contents[$rel], $new) >= 1) {
        $applied++; // a second run over an already-moved file

        continue;
    }
    if ($have !== $want) {
        $failures[] = "{$rel}: expected {$want} match(es), found {$have} for: ".str_replace("\n", ' ', substr($old, 0, 90));
    }
}

if ($failures !== []) {
    echo "REFUSED — no file was written:\n";
    foreach ($failures as $f) {
        echo "  ! {$f}\n";
    }
    exit(1);
}

if ($applied === count($edits)) {
    echo "  .. every edit already in the tree; running the claim check only\n";
} else {
    foreach ($edits as [$rel, $old, $new, $want]) {
        if (substr_count($contents[$rel], $old) === 0) {
            continue;
        }
        $contents[$rel] = str_replace($old, $new, $contents[$rel]);
        echo "  ok {$rel} ×{$want}\n";
    }
    foreach ($contents as $rel => $bytes) {
        file_put_contents($root.'/'.$rel, $bytes);
    }
}

// The claim is the point: no surface under resources/js may spell a level by hand any more. A
// "Year Level" label is the field's name and stays; what must not survive is a level digit or an
// interpolation glued to the word.
$offenders = [];
$definition = str_replace('\\', '/', $root.'/resources/js/Components/ui/statusLabel.js');
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root.'/resources/js', FilesystemIterator::SKIP_DOTS)
);
foreach ($it as $file) {
    if (! $file->isFile() || ! preg_match('/\.(jsx|js)$/i', $file->getFilename())) {
        continue;
    }
    $name = str_replace('\\', '/', $file->getPathname());
    if ($name === $definition) {
        continue; // the definition itself
    }
    $bytes = (string) file_get_contents($name);
    if (preg_match('/Year\s+(?:[0-9]|\$\{)|[\'"`]Year [\'"`]\s*\.|\bYear\s*\{[A-Za-z]/', $bytes, $m)) {
        // Print the line, not just the file: a finding nobody can read is the lane 68 blind-spot defect.
        $lines = explode("\n", $bytes);
        $at = 0;
        foreach ($lines as $i => $line) {
            if (preg_match('/Year\s+(?:[0-9]|\$\{)|[\'"`]Year [\'"`]\s*\.|\bYear\s*\{[A-Za-z]/', $line)) {
                $at = $i + 1;
                break;
            }
        }
        $offenders[] = str_replace(str_replace('\\', '/', $root).'/', '', $name).":{$at} ".trim($lines[$at - 1]);
    }
}

echo $offenders === []
    ? "  ok no page under resources/js spells \"Year N\" by hand any more\n"
    : "  ! still hand-spelled:\n".implode("\n", array_map(fn ($o) => "      {$o}", $offenders))."\n";

echo $offenders === [] ? "DONE — every desk surface reads the one vocabulary.\n" : "DONE WITH FINDINGS.\n";
exit($offenders === [] ? 0 : 1);
