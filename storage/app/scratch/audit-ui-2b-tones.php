<?php
/**
 * UI/UX lane 1 (fixed) — which tones does the app ask for, and which does the kit actually have?
 *
 * Badge falls back to grey for a tone it does not know, so a wrong name is not an error and not a
 * missing colour — it is a badge that quietly stops meaning anything, on a page a desk reads all
 * day. Same shape for WorkflowStepper and CauseEffectModal. This prints the set difference.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js');

$read = fn (string $p): string => str_replace('\\', '/', $p);

// --- what the kit accepts -------------------------------------------------------
$allowed = [];

$badge = (string) file_get_contents($root.'/Components/ui/Badge.jsx');
if (preg_match('/const toneClasses = \{(.*?)\};/s', $badge, $m)) {
    preg_match_all('/([a-zA-Z]+):\s*\'badge-([a-z-]+)\'/', $m[1], $p, PREG_SET_ORDER);
    $allowed['Badge'] = array_map(fn ($x) => $x[1], $p);
}

$stepper = (string) file_get_contents($root.'/Components/ui/WorkflowStepper.jsx');
preg_match_all('/([a-zA-Z]+):\s*\'/', $stepper, $sp);
$allowed['WorkflowStepper'] = [];
if (preg_match('/const (?:toneClasses|statusTones|tones) = \{(.*?)\};/s', $stepper, $sm)) {
    preg_match_all('/([a-zA-Z]+):\s*/', $sm[1], $sk);
    $allowed['WorkflowStepper'] = $sk[1];
}

$ce = (string) file_get_contents($root.'/Components/ui/CauseEffectModal.jsx');
preg_match_all("/tone === '([a-z]+)'/", $ce, $tm);
$allowed['CauseEffectModal'] = array_values(array_unique($tm[1]));

foreach ($allowed as $k => $v) {
    printf("%-18s accepts: %s\n", $k, implode(', ', $v ?: ['(none found)']));
}

// --- what the app asks for ------------------------------------------------------
$asked = [];   // tone => [where]
$files = [];
foreach (['/Pages', '/Components'] as $sub) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$sub, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.jsx?$/', $f->getFilename())) {
            $files[] = $read($f->getPathname());
        }
    }
}

foreach ($files as $file) {
    $short = str_replace($root.'/', '', $file);
    $src = (string) file_get_contents($file);

    // 1. tone="word" and tone={'word'}
    if (preg_match_all('/tone=\{?\s*[\'"]([a-zA-Z]+)[\'"]/', $src, $m)) {
        foreach ($m[1] as $t) {
            $asked[$t][] = $short;
        }
    }

    // 2. a local map feeding a tone: `anything: 'word',` inside a const whose name mentions tone/map
    if (preg_match_all('/const\s+(\w*(?:tone|Tone|Map|tones)\w*)\s*=\s*\{(.*?)\n\s*\};/s', $src, $objs, PREG_SET_ORDER)) {
        foreach ($objs as $o) {
            if (preg_match_all("/:\s*'([a-zA-Z]+)',?/", $o[2], $pairs)) {
                foreach ($pairs[1] as $t) {
                    $asked[$t][] = $short.'  (via '.$o[1].')';
                }
            }
        }
    }

    // 3. statusLabel.js returning a tone literal
    if (str_ends_with($short, 'statusLabel.js') && preg_match_all("/return '([a-zA-Z]+)';/", $src, $r)) {
        foreach ($r[1] as $t) {
            $asked[$t][] = $short.'  (returned)';
        }
    }
}

$known = array_unique(array_merge(...array_values($allowed)));
printf("\n%d distinct tone names asked for; kit knows %d.\n", count($asked), count($known));

$unknown = [];
foreach ($asked as $tone => $where) {
    if (! in_array($tone, $known, true)) {
        $unknown[$tone] = array_values(array_unique($where));
    }
}

if ($unknown === []) {
    echo "no tone name outside the kit\n";
} else {
    echo "\nTONES THE KIT DOES NOT KNOW (these render grey, silently):\n";
    ksort($unknown);
    foreach ($unknown as $tone => $where) {
        printf("   %-18s %d site(s): %s\n", $tone, count($where), implode(', ', array_slice($where, 0, 5)));
    }
}

// Which tone names are asked for by exactly one file — the duplication, counted honestly.
$single = array_filter($asked, fn ($w) => count(array_unique($w)) === 1);
printf("\n%d of the %d tone names are asked for in only one file.\n", count($single), count($asked));
