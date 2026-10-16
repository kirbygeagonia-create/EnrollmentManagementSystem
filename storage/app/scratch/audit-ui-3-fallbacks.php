<?php
/**
 * UI/UX lane 2 — the silent-fallback class, finished.
 *
 * Every one of these components falls back to a neutral/default when handed a name it does not
 * know: no console error, no broken layout, just a badge or a dialog that quietly stops carrying
 * its meaning. So the check is set membership, twice over: the keys the component defines, and
 * the values the app actually passes or the enum can produce.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3));
$js = $root.'/resources/js';

function pages(string $js): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($js.'/Pages', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
            $out[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
        }
    }
    ksort($out);

    return $out;
}

$app = pages($js);
$comp = pages($js); // placeholder overwritten below
$comp = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($js.'/Components', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && preg_match('/\.jsx?$/', $f->getFilename())) {
        $comp[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
    }
}
$all = $app + $comp;

$short = fn (string $f): string => str_replace($js.'/', '', $f);

// --- 1. CauseEffectModal: what it defines vs what call sites pass ----------------
$ceXml = '';
foreach ($comp as $f => $s) {
    if (str_ends_with($f, 'CauseEffectModal.jsx')) {
        $ceXml = $s;
    }
}
preg_match('/const toneConfig = \{(.*?)\n\};/s', $ceXml, $m);
preg_match_all('/^\s{4}([a-zA-Z]+):/m', $m[1] ?? '', $keys);
$defined = $keys[1];
echo "== CauseEffectModal toneConfig defines: ".implode(', ', $defined)."\n";

$passed = [];
foreach ($all as $f => $s) {
    if (preg_match_all('/<CauseEffectModal\b.*?tone="([a-zA-Z]+)"/s', $s, $mm)) {
        foreach ($mm[1] as $t) {
            $passed[$t][] = $short($f);
        }
    }
}
ksort($passed);
foreach ($passed as $t => $where) {
    $ok = in_array($t, $defined, true) ? 'ok  ' : 'BAD ';
    printf("   %s tone=\"%s\" — %d site(s): %s\n", $ok, $t, count(array_unique($where)), implode(', ', array_slice(array_unique($where), 0, 4)));
}

// --- 2. WorkflowStepper: its map vs the enum the server can send ----------------
$wsXml = '';
foreach ($comp as $f => $s) {
    if (str_ends_with($f, 'WorkflowStepper.jsx')) {
        $wsXml = $s;
    }
}
preg_match('/const stepStatusTone = \{(.*?)\};/s', $wsXml, $wm);
preg_match_all("/([a-zA-Z]+):\s*'/", $wm[1] ?? '', $wk);
$stepperKeys = $wk[1];
printf("\n== WorkflowStepper maps %d statuses: %s\n", count($stepperKeys), implode(', ', $stepperKeys));

$enumFile = $root.'/app/Enums/WorkflowStepStatus.php';
preg_match_all("/case\s+\w+\s*=\s*'([a-zA-Z]+)'/", (string) file_get_contents($enumFile), $ec);
$enumValues = $ec[1];
printf("   the enum can produce %d: %s\n", count($enumValues), implode(', ', $enumValues));
$missing = array_diff($enumValues, $stepperKeys);
echo $missing === []
    ? "   every enum value has a tone\n"
    : '   NO TONE (renders neutral): '.implode(', ', $missing)."\n";

// --- 3. Badge: every tone the app can hand it, from literals and from maps -------
preg_match('/const toneClasses = \{(.*?)\};/s', (string) file_get_contents($js.'/Components/ui/Badge.jsx'), $bm);
preg_match_all('/([a-zA-Z]+):\s*\'badge-/', $bm[1], $bk);
$badgeTones = $bk[1];

$asked = [];
foreach ($all as $f => $s) {
    if (preg_match_all('/tone=\{?\s*[\'"]([a-zA-Z]+)[\'"]/', $s, $mm)) {
        foreach ($mm[1] as $t) {
            $asked[$t][] = $short($f);
        }
    }
    if (preg_match_all('/const\s+(?:\w*(?:tone|Tone|Map)\w*)\s*=\s*\{(.*?)\n\s*\};/s', $s, $objs, PREG_SET_ORDER)) {
        foreach ($objs as $o) {
            if (preg_match_all("/:\s*'([a-zA-Z]+)',?/", $o[1], $pp)) {
                foreach ($pp[1] as $t) {
                    $asked[$t][] = $short($f);
                }
            }
        }
    }
}
$bad = array_diff(array_keys($asked), $badgeTones);
printf("\n== Badge accepts %d tones; the app asks for %d.\n", count($badgeTones), count($asked));
echo $bad === []
    ? "   every asked tone exists\n"
    : "   ASKED BUT UNKNOWN (renders grey): ".implode(', ', array_map(
        fn ($t) => $t.' ← '.implode(', ', array_slice(array_unique($asked[$t]), 0, 3)),
        $bad
    ))."\n";

// --- 4. Does a badge tone class exist for every tone Badge names? ----------------
$css = (string) file_get_contents($root.'/resources/css/app.css');
$classes = [];
preg_match_all('/\.badge-([a-z-]+)/', $css, $cm);
$classes = array_unique($cm[1]);
$orphan = array_diff($badgeTones, $classes);
echo "\n== CSS defines ".count($classes)." .badge-* classes.\n";
echo $orphan === []
    ? "   every tone Badge knows has a class\n"
    : '   TONE WITH NO CLASS: '.implode(', ', $orphan)."\n";
