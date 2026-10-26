<?php

// Throwaway measurement: the new line bounds of the two constructs the docx cites by number.

$root = dirname(__DIR__, 3);
$split = fn (string $p): array => explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$p)));

$show = $split('resources/js/Pages/Registrar/Show.jsx');
$headShow = $split('storage/app/scratch/lane75-head-Show.jsx');

echo "--- working Show.jsx 404-419 ---\n";
for ($i = 404; $i <= 419; $i++) {
    printf("  %d| %s\n", $i, trim($show[$i - 1]));
}

echo "--- working Show.jsx 606-618 ---\n";
for ($i = 606; $i <= 618; $i++) {
    printf("  %d| %s\n", $i, trim($show[$i - 1]));
}

echo "--- HEAD Show.jsx 444-456 (what :450 was inside) ---\n";
for ($i = 444; $i <= 456; $i++) {
    printf("  %d| %s\n", $i, trim($headShow[$i - 1]));
}

$rc = $split('app/Http/Controllers/Registrar/RegistrarController.php');
$start = null;
foreach ($rc as $n => $line) {
    if (str_contains($line, "return Inertia::render('Registrar/Show'")) {
        $start = $n + 1;
        break;
    }
}
$end = null;
for ($i = ($start ?? 1) - 1; $i < count($rc); $i++) {
    if (rtrim($rc[$i]) === '        ]);') {
        $end = $i + 1;
        break;
    }
}
echo "--- RegistrarController.php render block now: :".(int) $start.'-'.(int) $end." ---\n";
echo "HEAD held that construct at :110-122 (13 lines); the docx row cites :110-129, which ran 7 lines past it into the next docblock.\n";
