<?php

/**
 * Re-point EVERY citation into one moved file, by bytes, in one pass.
 *
 * A batch that inserts lines above a cited construct leaves the old number on live code that
 * belongs to something else, which is the one defect shape a bounds sweep cannot see. The shift
 * is therefore measured, never arithmetically derived: for every range the document cites, the
 * text HEAD held there is located in the working copy as a window of the same height, and only a
 * unique match is a move. This is `lane75-probe-moved-cites.php`'s rule, with the write added.
 *
 * Two shapes the writer has to respect, both bought by a defect recorded in AGENTS.md:
 *   - a short citation is the PREFIX of a long one (`:844` sits inside `:844-898`), so every
 *     number token is matched with `(?![\d-])` and taken as one regex pass, never a needle list;
 *   - a bare continuation (`…:628-632`) belongs to the file named most recently before it, and
 *     only within 160 characters — so a bare token is rewritten only when the attribution module
 *     says it is ours AND the text before the match in `document.xml` holds no other filename.
 *
 * Refusals, each named on its own line: a range whose HEAD text is nowhere in the working copy
 * (GONE — the construct was edited, so a human must re-read it), a range matching in more than one
 * place (AMBIGUOUS), a mapped range that turns out to occur zero times in the XML, and a flag the
 * tool does not read. `--map=OLD=NEW` is the human half of that: an override for a GONE range whose
 * new home was read off the file, verified by printing the lines it lands on before anything is
 * written.
 *
 * Usage: php storage/app/scratch/lane76-shift-cites.php --token=EvaluationController.php \
 *            --head=storage/app/scratch/lane76-head-EvaluationController.php \
 *            --live=app/Http/Controllers/Evaluation/EvaluationController.php            (dry run)
 *        … --apply
 */

require __DIR__.'/docx-cite-attribution.php';

$root = dirname(__DIR__, 3);

$known = ['token', 'head', 'live', 'dump', 'apply', 'map', 'skip'];
$args = [];
foreach (array_slice($argv, 1) as $arg) {
    if (! preg_match('/^--([a-z0-9-]+)(?:=(.*))?$/', $arg, $m)) {
        fwrite(STDERR, "REFUSED: unrecognised argument {$arg}; this tool reads only --".implode(', --', $known).".\n");
        exit(1);
    }
    if (! in_array($m[1], $known, true)) {
        fwrite(STDERR, "REFUSED: --{$m[1]} is not a flag this tool reads (known: --".implode(', --', $known).").\n");
        exit(1);
    }
    if (($m[2] ?? null) === null && $m[1] !== 'apply') {
        fwrite(STDERR, "REFUSED: --{$m[1]} needs a value.\n");
        exit(1);
    }
    // Repeated value flags accumulate. A cascade names one --map= per unmappable range, and a
    // parser that overwrote the key would apply the LAST one and report the rest as GONE — which
    // reads like the document still has six defects rather than like a flag that was dropped.
    if (isset($args[$m[1]]) && in_array($m[1], ['map', 'skip'], true)) {
        $existing = (array) $args[$m[1]];
        $existing[] = $m[2];
        $args[$m[1]] = $existing;
    } else {
        $args[$m[1]] = $m[2] ?? true;
    }
}

foreach (['token', 'head', 'live'] as $required) {
    if (! isset($args[$required])) {
        fwrite(STDERR, "REFUSED: --{$required}= is required.\n");
        exit(1);
    }
}

$token = (string) $args['token'];
$headPath = $root.'/'.$args['head'];
$livePath = $root.'/'.$args['live'];
$apply = ($args['apply'] ?? false) === true;

if (! is_file($headPath) || ! is_file($livePath)) {
    fwrite(STDERR, "REFUSED: could not read both versions of {$token}. Take the HEAD copy with\n"
        ."  git show HEAD:<path> > {$args['head']}\n"
        ."because shell_exec cannot reach git from PHP on this box.\n");
    exit(1);
}

$split = static fn (string $p): array => array_map(
    'trim',
    explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($p)))
);
$head = $split($headPath);
$live = $split($livePath);
if (count($head) < 2 || count($live) < 2) {
    fwrite(STDERR, "REFUSED: could not read both versions (head ".count($head)." lines, live ".count($live)." lines).\n");
    exit(1);
}

$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
if (strlen($xml) < 100000) {
    fwrite(STDERR, "REFUSED: word/document.xml read back as ".strlen($xml)." bytes.\n");
    exit(1);
}

$dumpPath = $root.'/storage/app/scratch/'.($args['dump'] ?? 'docx-flat.txt');
$dump = (string) file_get_contents($dumpPath);
if (trim($dump) === '') {
    fwrite(STDERR, "REFUSED: the dump at {$dumpPath} is empty — re-run dump-docx-flat.php.\n");
    exit(1);
}

/** Is a cited spelling ours? `routes/web.php` and `web.php` are one file; `ID/Show.jsx` is not
 *  `Evaluation/Show.jsx`. The test is a path-boundary suffix in either direction. */
$isOurs = static function (string $name) use ($token): bool {
    if ($name === $token) {
        return true;
    }

    return str_ends_with($name, '/'.$token) || str_ends_with($token, '/'.$name);
};

$scan = docxScan($dump);
$lineStart = docxLineStarts($dump);
$owners = docxBareOwners($scan['bare'], $scan['anchors'], $lineStart);

$keys = [];
$namedKeys = [];
$bareKeys = [];
// `--skip=` holds a key back for a different writer: a comma list (`:58,190`) is TWO citations
// and a pattern that takes only the first would leave the second at its old number while making
// the pair look shifted. Those pairs go through the edit engine as one token, so they must not be
// touched here.
$skips = [];
foreach ((array) ($args['skip'] ?? []) as $skip) {
    if ($skip !== true) {
        $skips[trim((string) $skip)] = true;
    }
}
foreach ($scan['named'] as [$name, $from, $to]) {
    if ($isOurs($name)) {
        $key = $from.($to === null ? '' : '-'.$to);
        $keys[$key] = [$from, $to];
        $namedKeys[$key] = true;
    }
}
foreach ($scan['bare'] as $k => [$from, $to, $offset]) {
    // `docxBareOwners` answers an ARRAY (file, dump line, from, to) — a reader that asks for a
    // string silently collects zero bare tokens, and the bare half of the cascade then stays
    // pointing at the old lines while every named one moves.
    $owner = $owners[$k] ?? null;
    $ownerFile = is_array($owner) ? ($owner['file'] ?? null) : $owner;
    if (is_string($ownerFile) && $isOurs($ownerFile)) {
        $key = $from.($to === null ? '' : '-'.$to);
        $keys[$key] = [$from, $to];
        $bareKeys[$key] = true;
    }
}

foreach (array_keys($skips) as $s) {
    if (! isset($keys[$s])) {
        fwrite(STDERR, "REFUSED: --skip={$s} names no citation this run collected, so the flag would be inert.\n");
        exit(1);
    }
    unset($keys[$s], $namedKeys[$s], $bareKeys[$s]);
    echo "SKIP {$s} — left for a writer that can address the whole token\n";
}

if ($keys === []) {
    fwrite(STDERR, "REFUSED: the dump cites no range as {$token}, so this run would read nothing.\n");
    exit(1);
}

$map = [];
$gone = [];
$ambiguous = [];
$overrides = [];
foreach ((array) ($args['map'] ?? []) as $row) {
    if ($row === true || ! preg_match('/^(\d+(?:-\d+)?)=(\d+(?:-\d+)?)$/', $row, $m)) {
        fwrite(STDERR, "REFUSED: --map wants OLD=NEW with plain line numbers, got ".var_export($row, true).".\n");
        exit(1);
    }
    $overrides[$m[1]] = [$m[2], null];
    if (str_contains($m[2], '-')) {
        [$a, $b] = explode('-', $m[2]);
        $overrides[$m[1]] = [(int) $a, (int) $b];
    } else {
        $overrides[$m[1]] = [(int) $m[2], null];
    }
}

foreach ($keys as $key => [$from, $to]) {
    if (isset($overrides[$key])) {
        $map[$key] = $overrides[$key];
        $end = $overrides[$key][1] ?? $overrides[$key][0];
        if ($end > count($live) || $overrides[$key][0] < 1) {
            fwrite(STDERR, "REFUSED: --map {$key} lands past the end of the live file ({$end} of ".count($live).").\n");
            exit(1);
        }
        $lands = array_slice($live, $overrides[$key][0] - 1, $end - $overrides[$key][0] + 1);
        if (trim(implode(' ', $lands)) === '') {
            fwrite(STDERR, "REFUSED: --map {$key} lands on blank lines.\n");
            exit(1);
        }
        echo "OVERRIDE {$key} -> {$overrides[$key][0]}".($overrides[$key][1] === null ? '' : '-'.$overrides[$key][1])
            ."\n         lands on: ".substr(implode(' | ', $lands), 0, 150)."\n";

        continue;
    }

    $len = ($to ?? $from) - $from + 1;
    $window = array_slice($head, $from - 1, $len);
    if (count($window) !== $len || $from < 1) {
        $gone[] = $key.' (outside HEAD, which ends at line '.count($head).')';

        continue;
    }

    $hits = [];
    for ($p = 0; $p + $len <= count($live); $p++) {
        if (array_slice($live, $p, $len) === $window) {
            $hits[] = $p + 1;
        }
    }
    if (count($hits) === 0) {
        $gone[] = $key.'  HEAD text: '.substr(implode(' | ', array_slice($window, 0, 2)), 0, 110);

        continue;
    }
    if (count($hits) > 1) {
        $ambiguous[] = $key.'  matched '.count($hits).' places ('.implode(',', $hits).')';

        continue;
    }
    $map[$key] = [$hits[0], $hits[0] + $len - 1 === $hits[0] ? null : $hits[0] + $len - 1];
}

$pattern = '/(?<name>'.docxFilenamePattern().')?:(?<from>\d+)(?:-(?<to>\d+))?(?![\d\-])/u';
preg_match_all($pattern, $xml, $found, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE);

$replacements = [];
$touched = [];
$foreignBare = [];
foreach ($found as $x) {
    $off = $x[0][1];
    $from = (int) $x['from'][0];
    $to = $x['to'][0] === null ? null : (int) $x['to'][0];
    $key = $from.($to === null ? '' : '-'.$to);
    if (! isset($map[$key])) {
        continue;
    }

    $name = $x['name'][0] === null ? null : (string) $x['name'][0];
    if ($name === null) {
        // A bare token is ours only when nothing but ours is near it: the dump says so, and the
        // bytes before the match in document.xml must hold no other filename inside 160 characters.
        if (! isset($bareKeys[$key])) {
            continue;
        }
        $before = preg_replace('/<[^>]*>/', ' ', substr($xml, max(0, $off - 400), min(400, $off)));
        if (preg_match_all('/'.docxFilenamePattern().'/u', $before, $near) > 0) {
            foreach ($near[0] as $candidate) {
                if (! $isOurs((string) $candidate)) {
                    $foreignBare[] = $key.' (nearest name '.$candidate.')';

                    continue 2;
                }
            }
        }
    } elseif (! $isOurs($name)) {
        continue;
    }

    [$newFrom, $newTo] = $map[$key];
    $numberText = (string) $newFrom.($newTo === null ? '' : '-'.$newTo);
    $replacements[] = [
        'at' => $x['from'][1],
        'len' => strlen($x[0][0]) - strlen((string) ($x['name'][0] ?? '')) - 1,
        'to' => $numberText,
        'key' => $key,
    ];
    $touched[$key] = ($touched[$key] ?? 0) + 1;
}

printf("citations into %s: %d distinct range(s), %d resolved, %d GONE, %d ambiguous\n",
    $token, count($keys), count($map), count($gone), count($ambiguous));

foreach ($map as $key => [$newFrom, $newTo]) {
    $new = (string) $newFrom.($newTo === null ? '' : '-'.$newTo);
    $hits = $touched[$key] ?? 0;
    printf("  %-12s -> %-12s %d occurrence(s) %s\n", $key, $new, $hits, $hits === 0 ? 'REFUSED-BELOW' : '');
}

$refusals = [];
foreach ($map as $key => $row) {
    if (($touched[$key] ?? 0) === 0) {
        $refusals[] = "mapped {$key} occurs nowhere in document.xml — the needle definition and the dump disagree";
    }
}
foreach ($gone as $g) {
    $refusals[] = "GONE {$g} — the construct was edited; read the sentence and pass --map=".$g;
}
foreach ($ambiguous as $a) {
    $refusals[] = "AMBIGUOUS {$a}";
}
foreach ($foreignBare as $f) {
    $refusals[] = "BARE-DEFERRED {$f}";
}

if ($refusals !== []) {
    echo "\n".count($refusals)." item(s) this run will not write:\n";
    foreach ($refusals as $r) {
        echo '  '.preg_replace('/\s+/', ' ', $r)."\n";
    }
    fwrite(STDERR, "\nREFUSED: nothing written. Resolve every item above and run again.\n");
    exit(1);
}

usort($replacements, static fn (array $a, array $b): int => $b['at'] <=> $a['at']);
foreach ($replacements as $r) {
    $xml = substr_replace($xml, $r['to'], $r['at'], $r['len']);
}

printf("\n%s%d number token(s) rewritten%s\n", $apply ? '' : 'DRY RUN — ', count($replacements), $apply ? '' : ' (not written)');

if (! $apply) {
    exit(0);
}

$backup = $root.'/storage/app/scratch/shift-'
    // A cited spelling carries a slash (`Evaluation/Show.jsx`), which is a directory separator in
    // a file name: the first run tried to write into a folder that does not exist and refused
    // itself — correctly, but only after the whole shift had been computed.
    .str_replace('/', '-', $token)
    .'-'.date('Ymd-His').'.docx';
if (! copy($docx, $backup)) {
    fwrite(STDERR, "REFUSED: could not write the backup {$backup}.\n");
    exit(1);
}

$zip = new ZipArchive;
if (! $zip->open($docx, ZipArchive::CREATE)) {
    fwrite(STDERR, "REFUSED: cannot reopen {$docx} for writing.\n");
    exit(1);
}
$zip->addFromString('word/document.xml', $xml);
$zip->close();

$again = new ZipArchive;
$again->open($docx);
$read = (string) $again->getFromName('word/document.xml');
$again->close();
if ($read !== $xml) {
    fwrite(STDERR, "REFUSED: the archive did not read back the bytes it was handed.\n");
    exit(1);
}

echo 'backup: '.basename($backup)."\nwritten.";
exit(0);
