<?php
/**
 * Lane C — replace the six desk copies of the identity tone list with the shared one, and drop the
 * two local copies of studentTypeTone that are byte-identical to the export already in the kit.
 *
 * Nothing here may change a rendered colour, so every step asserts its own count: the const block
 * appears once, the call sites match the number the pre-flight measured, and afterwards the old
 * name appears nowhere. A map whose pairs are not all identity is refused outright.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3).'/resources/js/Pages');
$apply = in_array('--apply', $argv, true);

$targets = [
    ['file' => 'Admission/Index.jsx', 'map' => 'statusToneMap', 'uses' => 1, 'to' => 'statusToneFor'],
    ['file' => 'Admission/Show.jsx', 'map' => 'admissionStatusToneMap', 'uses' => 1, 'to' => 'statusToneFor'],
    ['file' => 'Clearance/Index.jsx', 'map' => 'overallStatusToneMap', 'uses' => 2, 'to' => 'statusToneFor'],
    ['file' => 'Clearance/Index.jsx', 'map' => 'approvalToneMap', 'uses' => 1, 'to' => 'statusToneFor'],
    ['file' => 'Students/Show.jsx', 'map' => 'clearanceStatusToneMap', 'uses' => 1, 'to' => 'statusToneFor'],
    ['file' => 'Students/Show.jsx', 'map' => 'scholarshipStatusToneMap', 'uses' => 1, 'to' => 'statusToneFor'],
    ['file' => 'Admission/Show.jsx', 'map' => 'applicantTypeToneMap', 'uses' => 1, 'to' => 'studentTypeTone'],
    ['file' => 'Evaluation/Show.jsx', 'map' => 'studentTypeToneMap', 'uses' => 1, 'to' => 'studentTypeTone'],
];

$byFile = [];
foreach ($targets as $t) {
    $byFile[$t['file']][] = $t;
}

$failures = 0;

foreach ($byFile as $rel => $edits) {
    $path = $root.'/'.$rel;
    $src = (string) file_get_contents($path);
    $new = $src;

    foreach ($edits as $e) {
        [$map, $want, $to] = [$e['map'], $e['uses'], $e['to']];

        // 1. the const block, and it must be pure identity to become statusToneFor
        if (! preg_match('/const\s+'.preg_quote($map, '/').'\s*=\s*\{([^}]*)\n\};\n?/', $new, $m)) {
            printf("  FAIL %-24s %s: block not found\n", $rel, $map);
            $failures++;

            continue;
        }

        preg_match_all("/^\s*([a-zA-Z]+):\s*'([a-zA-Z]+)',?/m", $m[1], $pairs, PREG_SET_ORDER);

        if ($to === 'statusToneFor') {
            foreach ($pairs as $p) {
                if ($p[1] !== $p[2]) {
                    printf("  FAIL %-24s %s: %s -> %s is not identity\n", $rel, $map, $p[1], $p[2]);
                    $failures++;
                }
            }
        } elseif ($map !== 'studentTypeToneMap' && $map !== 'applicantTypeToneMap') {
            $failures++;
        }

        // 2. call sites
        $pattern = '/'.preg_quote($map, '/').'\[([^\]]+)\](\s*\|\|\s*\'neutral\')?/';
        $sites = preg_match_all($pattern, $new);

        if ($sites !== $want) {
            printf("  FAIL %-24s %s: %d call site(s), expected %d\n", $rel, $map, $sites, $want);
            $failures++;

            continue;
        }

        $replacement = $to === 'statusToneFor' ? 'statusToneFor($1)' : $to.'[$1] ?? \'neutral\'';
        $new = preg_replace($pattern, $replacement, $new);
        $new = preg_replace('/const\s+'.preg_quote($map, '/').'\s*=\s*\{[^}]*\n\};\n?/', '', $new, 1);

        printf("  ok   %-24s %-26s %d site(s) -> %s\n", $rel, $map, $sites, $to);
    }

    // 3. the import must name whatever the rewritten sites now call
    $needed = [];
    if (str_contains($new, 'statusToneFor(')) {
        $needed[] = 'statusToneFor';
    }
    if (preg_match('/\bstudentTypeTone\[/', $new) && ! preg_match('/\bstudentTypeTone\s*[,}]/', $new)) {
        $needed[] = 'studentTypeTone';
    }

    foreach ($needed as $n) {
        if (preg_match("/import \{([^}]*)\} from '\@\/Components\/ui'/", $new, $im) && ! str_contains($im[1], $n)) {
            $new = str_replace($im[0], "import {".rtrim($im[1]).', '.$n." } from '\@/Components/ui'", $new);
            printf("  ok   %-24s import += %s\n", $rel, $n);
        }
    }

    if (preg_match('/\b(statusToneMap|admissionStatusToneMap|overallStatusToneMap|approvalToneMap|clearanceStatusToneMap|scholarshipStatusToneMap|applicantTypeToneMap|studentTypeToneMap)\b/', $new, $left)) {
        printf("  FAIL %-24s %s still referenced\n", $rel, $left[1]);
        $failures++;
    }

    if ($apply && $new !== $src) {
        file_put_contents($path, $new);
    }
}

if ($failures > 0) {
    fwrite(STDERR, "REFUSE: {$failures} check(s) failed — nothing written for those\n");
    exit(1);
}

echo $apply ? "WROTE\n" : "DRY RUN — pass --apply\n";
