<?php
/**
 * Lane A3 pre-flight 2 — an ad-hoc button is only a defect if it lost something the kit gives.
 *
 * Colour is a design choice; focus ring and disabled state are affordances. `.btn` carries
 * focus:ring-2, disabled:opacity-50 and disabled:cursor-not-allowed. A hand-painted button that
 * has them is a deliberate look; one that lacks them is a control a keyboard cannot see and a
 * desk can click twice. This counts those, and prints the label so a human can see what it is.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js/Pages');

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = str_replace('\\', '/', $f->getPathname());
    }
}
sort($files);

$tokenised = 0;
$adhoc = [];

foreach ($files as $file) {
    $short = str_replace($root.'/', '', $file);
    $lines = preg_split('/\R/', (string) file_get_contents($file));

    foreach ($lines as $i => $line) {
        if (! preg_match('/<button\b/', $line)) {
            continue;
        }

        $tag = $line;
        $j = $i;

        while (! preg_match('/>\s*$/', $tag) && isset($lines[$j + 1])) {
            $j++;
            $tag .= ' '.$lines[$j];
        }

        if (! preg_match('/className=\{?[`"\']([^`"\']+)[`"\']/', $tag, $cm)) {
            continue;
        }

        $cls = preg_replace('/\s+/', ' ', $cm[1]);

        if (preg_match('/\bbtn\b/', $cls)) {
            $tokenised++;

            continue;
        }

        $label = trim($lines[$j + 1] ?? '');
        $label = preg_replace('/^\s*[<{].*/', '', $label);
        $label = $label === '' ? '(icon or expression)' : mb_substr($label, 0, 32);

        $adhoc[] = [
            'where' => $short.':'.($i + 1),
            'focus' => preg_match('/focus:/', $cls),
            'disabled' => preg_match('/disabled:/', $cls),
            'type' => preg_match('/type=/', $tag),
            'label' => $label,
            'cls' => mb_substr($cls, 0, 62),
        ];
    }
}

printf("buttons already on .btn tokens: %d\n", $tokenised);
printf("ad-hoc buttons: %d\n", count($adhoc));

$noFocus = array_values(array_filter($adhoc, fn ($a) => ! $a['focus']));
$noDisabled = array_values(array_filter($adhoc, fn ($a) => ! $a['disabled']));
printf("  without a focus ring:    %d\n", count($noFocus));
printf("  without a disabled state: %d\n", count($noDisabled));

echo "\n-- ad-hoc buttons missing an affordance (the defect list) --\n";
foreach ($adhoc as $a) {
    if ($a['focus'] && $a['disabled']) {
        continue;
    }
    printf("   %-34s focus:%s disabled:%s  %s\n", $a['where'], $a['focus'] ? 'y' : 'N', $a['disabled'] ? 'y' : 'N', $a['label']);
}
