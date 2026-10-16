<?php
// Pass 76 pre-flight: are the two needles contiguous inside a single <w:t>, and how many
// copies does the document hold? Never write before this prints ok.
$z = new ZipArchive();
$z->open('Documentation/EMS Complete Documentation.docx');
$xml = $z->getFromName('word/document.xml');
$z->close();

$needles = [
    'command-list' => 'app/Console/Commands contains only BenchmarkHeavyQueries, CreateAdminUser, and PrintFidelitySamples.',
    'revision-tail' => 'not the tester',
    'revision-tail-2' => 'The owner then ruled the dead right retired with its module',
    'images-path' => 'Documentation/Images',
    'print-fidelity-says' => 'renders the Blade templates itself',
];

foreach ($needles as $name => $needle) {
    printf("%-18s raw occurrences: %d\n", $name, substr_count($xml, $needle));
}

// Where does the 2026-10-10 revision row end? Print the tail of that cell as raw XML.
$at = strpos($xml, 'The five-lane audit the owner asked for');
printf("\nrevision row starts at: %s\n", $at === false ? 'NOT FOUND' : $at);

if ($at !== false) {
    $window = substr($xml, $at, 9000);
    $end = strpos($window, '</w:p>');
    echo "cell paragraph ends +{$end}\n";
    echo "...".substr($window, max(0, $end - 700), 700 + $end - max(0, $end - 700))."\n";
}
