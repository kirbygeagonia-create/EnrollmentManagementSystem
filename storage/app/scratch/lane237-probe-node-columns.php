<?php

/**
 * Where did dagre actually put the nodes of the actor-to-desk overview? The generator's answer to "too many lines"
 * was a coarser figure, and a 360x4189 image says the layout did not become wide. This prints the distinct x
 * centres (the rank columns) and how many nodes share each, because "three columns of twelve" and "one column of
 * thirty" are different defects with the same aspect ratio and only the positions say which one happened.
 */
$svg = $argv[1] ?? 'Documentation/Diagrams/use-case-roles-at-desks.svg';
if (! is_file($svg)) {
    fwrite(STDERR, "REFUSED: {$svg} is not a file\n");
    exit(1);
}
$xml = file_get_contents($svg);
// <g class="node …" id="flowchart-XXX-7" transform="translate(x, y)"> — mermaid writes the comma AND a space, so the
// separator between the two numbers is `[, ]+`; with a single character class every node reads as absent and the
// probe refuses a layout it could have measured.
preg_match_all('#<g class="[^"]*\bnode\b[^"]*"[^>]*?transform="translate\(([-0-9.]+)[, ]+([-0-9.]+)\)"#', $xml, $m);
if (count($m[0]) === 0) {
    fwrite(STDERR, "REFUSED: {$svg} holds no positioned nodes — the reader saw nothing, which is not a layout verdict\n");
    exit(1);
}
$cols = [];
for ($i = 0; $i < count($m[0]); $i++) {
    $key = (int) round((float) $m[1][$i] / 40) * 40;
    $cols[$key][] = trim(preg_replace('#\s+#', ' ', (string) preg_replace('#<[^>]*>#', '', $m[0][$i])));
}
ksort($cols);
echo basename($svg).': '.count($m[0])." node(s) in ".count($cols)." x-column(s)\n";
foreach ($cols as $x => $names) {
    echo '  x='.$x.'  '.count($names)." node(s)\n";
}
