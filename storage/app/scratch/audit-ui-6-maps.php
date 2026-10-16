<?php
/**
 * Lane C pre-flight — which local tone maps can be moved to the shared module without changing
 * a single rendered colour?
 *
 * Only a map where EVERY pair is `word: 'word'` is a pure rename away from a shared lookup.
 * Anything else carries a decision (firstYear is 'info', a derived balance tone, etc.) and must
 * stay where its author put it until someone rules on it. This prints both lists and the call
 * sites each identity map has, because rewriting those is where an invisible mistake would live.
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

$identity = [];
$mixed = [];
$noUse = [];

foreach ($files as $file) {
    $short = str_replace($root.'/', '', $file);
    $src = (string) file_get_contents($file);

    if (! preg_match_all('/const\s+(\w*(?:tone|Tone|Map|tones)\w*)\s*=\s*\{(.*?)\n\};/s', $src, $objs, PREG_SET_ORDER)) {
        continue;
    }

    foreach ($objs as $o) {
        [, $name, $body] = $o;
        preg_match_all("/^\s*([a-zA-Z]+):\s*'([a-zA-Z]+)',?/m", $body, $pairs, PREG_SET_ORDER);

        if ($pairs === []) {
            continue;
        }

        $pure = true;
        $notes = [];

        foreach ($pairs as $p) {
            if ($p[1] !== $p[2]) {
                $pure = false;
                $notes[] = $p[1].'→'.$p[2];
            }
        }

        $uses = preg_match_all('/\b'.preg_quote($name, '/').'\b/', $src) - 1;

        if ($pure) {
            $identity[] = sprintf('%-34s %-30s %d use(s)', $short, $name, $uses);
            if ($uses === 0) {
                $noUse[] = $short.'  '.$name;
            }
        } else {
            $mixed[] = sprintf('%-34s %-30s %s', $short, $name, implode(', ', $notes));
        }
    }
}

printf("== pure identity maps (movable): %d\n", count($identity));
foreach ($identity as $i) {
    echo '   '.$i."\n";
}
printf("\n== maps carrying a decision (must stay or be ruled on): %d\n", count($mixed));
foreach ($mixed as $m) {
    echo '   '.$m."\n";
}
printf("\n== identity maps with no use at all (dead): %d\n", count($noUse));
foreach ($noUse as $n) {
    echo '   '.$n."\n";
}
