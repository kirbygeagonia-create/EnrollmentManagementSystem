<?php
/**
 * Lane C pre-flight 2 — do two desks paint the SAME word in different COLOURS?
 *
 * Comparing tone NAMES is wrong: 'approved' and 'success' are different names for one pair of
 * colours, so a name diff cries wolf. This resolves every tone through Badge's table and then
 * through app.css to the two rgb values a person actually sees, and only reports a word whose
 * rendered colour differs between files.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3));
$js = $root.'/resources/js';

// tone name => [bg-class, fg-class]
$badgeTable = [];
preg_match('/const toneClasses = \{(.*?)\};/s', (string) file_get_contents($js.'/Components/ui/Badge.jsx'), $m);
preg_match_all('/([a-zA-Z]+):\s*\'badge-([a-z-]+)\'/', $m[1], $p, PREG_SET_ORDER);
foreach ($p as $x) {
    $badgeTable[$x[1]] = $x[2];
}

// badge-<tone> => the two colour utilities it applies
$css = (string) file_get_contents($root.'/resources/css/app.css');
$toneColours = [];
foreach ($badgeTable as $tone => $cls) {
    if (preg_match('/\.badge-'.preg_quote($cls, '/').'\s*\{\s*@apply\s+badge\s+([^;]+);/', $css, $mm)) {
        $toneColours[$tone] = preg_replace('/\s+/', ' ', trim($mm[1]));
    }
}

// every `word: 'tone'` pair in every map under Pages/ and the shared module
$sources = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($js.'/Pages', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $sources[] = str_replace('\\', '/', $f->getPathname());
    }
}
$sources[] = $js.'/Components/ui/statusLabel.js';

$seen = [];   // word => colour-signature => [where]
$unknown = [];

foreach ($sources as $file) {
    $short = str_replace($js.'/', '', $file);
    $src = (string) file_get_contents($file);

    if (! preg_match_all('/const\s+\w*(?:tone|Tone|Map|tones)\w*\s*=\s*\{(.*?)\n\};/s', $src, $objs, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($objs as $o) {
        if (! preg_match_all("/^\s*([a-zA-Z]+):\s*'([a-zA-Z]+)',?/m", $o[1], $pairs, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($pairs as [, $word, $tone]) {
            if (! isset($toneColours[$tone])) {
                $unknown[$tone][] = $short.'  '.$word;
                continue;
            }
            $seen[$word][$toneColours[$tone]][] = $short;
        }
    }
}

echo "== words painted more than ONE colour across the desks\n";
$bad = 0;
ksort($seen);
foreach ($seen as $word => $colours) {
    if (count($colours) < 2) {
        continue;
    }
    $bad++;
    printf("   %-14s\n", $word);
    foreach ($colours as $colour => $where) {
        printf("       %-44s  %s\n", $colour, implode(', ', array_values(array_unique($where))));
    }
}
echo $bad === 0 ? "   none\n" : '';
printf("\n%d word(s) diverge by rendered colour.\n", $bad);

if ($unknown !== []) {
    echo "\n== tones the kit does not define (these render the neutral fallback)\n";
    foreach ($unknown as $tone => $where) {
        printf("   %-12s %s\n", $tone, implode(', ', array_values(array_unique($where))));
    }
}
