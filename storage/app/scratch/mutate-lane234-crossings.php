<?php

/**
 * Falsifier for `lane234-count-edge-crossings.mjs`, the census that answers "do the drawn lines cross?".
 * It cannot be proved against the delivered figures (their geometry is the render's, not something a leg can plant),
 * so every case writes its own SVG into storage/app/scratch and hands it to the tool: a crossing that MUST be named,
 * a clean pair that MUST be silent, a file with no edge-classed path that MUST refuse rather than report zero, the
 * withheld-rows notice when the count outruns the print cap, a missing file, and an unknown flag by name.
 *
 * Run: php storage/app/scratch/mutate-lane234-crossings.php
 */
$root = dirname(__DIR__, 3);
$scratch = $root.'/storage/app/scratch';
$tool = $scratch.'/lane234-count-edge-crossings.mjs';
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$node = 'node';
$made = [];
$bad = 0;
$legs = 0;

register_shutdown_function(function () use ($scratch, &$made) {
    foreach ($made as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
    $left = glob($scratch.'/lane234-fixture-*.svg');
    if ($left !== false && count($left) > 0) {
        echo "\nBAD   fixture sweep left ".count($left)." file(s): ".implode(', ', array_map('basename', $left))."\n";
    }
});

$run = static function (string $cmd) use ($root): array {
    $out = [];
    $code = 0;
    exec('cd /d '.$root.' && '.$cmd.' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
};

$write = static function (string $name, string $body) use ($scratch, &$made): string {
    $path = $scratch.'/lane234-fixture-'.$name.'.svg';
    file_put_contents($path, $body);
    $made[] = $path;
    return $path;
};

$path = (string) file_get_contents($tool);
if ($path === '') {
    echo "BAD   {$tool} is unreadable; the harness can measure nothing\n";
    exit(1);
}

// An SVG body holding the given <path> elements. The class is mermaid's own edge class, so the tool's filter sees
// connectors and not box outlines.
$body = static function (array $paths): string {
    $inner = '';
    foreach ($paths as $i => $d) {
        $inner .= '<g class="edge"><path class="edge flowchart-link" d="'.$d.'" id="L'.$i.'"/></g>';
    }

    return '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><g class="edges">'.$inner.'</g></svg>';
};

$expect = static function (string $leg, array $want, string $cmd) use (&$bad, &$legs, $run, $php, $node, $tool): void {
    $legs++;
    [$code, $out] = $run($cmd);
    $fails = [];
    foreach ($want as $key => $rule) {
        if ($key === 'exit') {
            $ok = $rule === 'zero' ? $code === 0 : $code !== 0;
            if (! $ok) {
                $fails[] = 'exit '.($rule === 'zero' ? 'must be 0' : 'must be non-zero').', got '.$code;
            }

            continue;
        }
        if ($rule === 'absent') {
            if (str_contains($out, $key)) {
                $fails[] = 'the output must not carry ['.$key.']';
            }

            continue;
        }
        if (! str_contains($out, $rule)) {
            $fails[] = 'expected to read ['.$rule.']';
        }
    }
    if ($fails === []) {
        echo str_pad($leg, 40)."ok    ".($want['exit'] ?? 'exit')." on its own fixture\n";

        return;
    }
    echo str_pad($leg, 40)."BAD   ".implode('; ', $fails)."\n".wordwrap(substr($out, 0, 500), 96, "\n      ", true)."\n";
    $bad++;
};

$q = static fn (string $p): string => '"'.$p.'"';

// 1. two lines that cross. The count must be one and the run must not exit clean.
$cross = $write('crossing', $body(['M10,10L190,190', 'M10,190L190,10']));
$expect('crossing-counted', ['1 crossing pair' => '1 crossing pair(s)', 'exit' => 'nonzero'], $node.' '.$q($tool).' '.$q($cross));

// 2. two parallel lines. The census must say zero and exit clean — a tool that refuses everything would fail this.
$clean = $write('parallel', $body(['M10,10L190,10', 'M10,190L190,190']));
$expect('clean-sheet-silent', ['0 crossing pair(s)' => '0 crossing pair(s)', 'NO CROSSINGS' => 'NO CROSSINGS', 'exit' => 'zero'], $node.' '.$q($tool).' '.$q($clean));

// 3. lines that only meet at a shared endpoint. The tolerance is the whole point of the tool: an arrow head touching
//    its own box is not a crossing, and a census that counts it would accuse every real figure.
$shared = $write('shared-end', $body(['M100,10L100,100', 'M10,100L100,100']));
$expect('shared-endpoint-not-a-crossing', ['0 crossing pair(s)' => '0 crossing pair(s)', 'exit' => 'zero'], $node.' '.$q($tool).' '.$q($shared));

// 4. no connector at all. Reporting "0 crossings" over a file with no edges is the fake-clean verdict this repository
//    has refused many times, so the run must refuse.
$noedge = $write('no-edge', '<svg xmlns="http://www.w3.org/2000/svg"><g class="nodes"><rect x="0" y="0" width="10" height="10"/></g></svg>');
$expect('edgeless-refuses', ['REFUSED' => 'REFUSED', 'exit' => 'nonzero'], $node.' '.$q($tool).' '.$q($noedge));

// 5. more crossings than the print cap. The tally and the printed rows must not silently disagree.
//    Four horizontals crossed by four verticals is 16 interior crossings; the first attempt fanned eight lines to one
//    point, and lines that share an endpoint are not crossings by this tool's own rule — the fixture was wrong, not
//    the census.
$fan = [];
foreach ([20, 60, 100, 140] as $y) {
    $fan[] = 'M0,'.$y.'L200,'.$y;
}
foreach ([20, 60, 100, 140] as $x) {
    $fan[] = 'M'.$x.',0L'.$x.',200';
}
$capped = $write('many', $body($fan));
$expect('withheld-rows-named', [
    '16 crossing pair(s)' => '16 crossing pair(s)',
    'NOT PRINTED' => 'NOT PRINTED',
    'INCOMPLETE' => 'INCOMPLETE',
    'exit' => 'nonzero',
], $node.' '.$q($tool).' '.$q($capped));

// 6. a file that is not there, and a flag the tool does not read.
$expect('missing-file-refuses', ['REFUSED' => 'REFUSED', 'exit' => 'nonzero'], $node.' '.$q($tool).' '.$q($scratch.'/lane234-no-such-file.svg'));
$expect('unknown-flag-refused', ['REFUSED' => 'REFUSED', '--no-such-flag-at-all' => '--no-such-flag-at-all', 'exit' => 'nonzero'], $node.' '.$q($tool).' --no-such-flag-at-all');

// 7. a DIRECTORY where a file was expected. This leg is the reason the guard exists: the census read each path with
//    readFileSync, so handing it Documentation/Diagrams threw EISDIR out of Node's loader and printed a stack trace.
//    The missing-file case above exits non-zero for either reason, so it could never have caught that — a leg that
//    cannot tell a refusal from a crash is how a broken instrument looks like a careful one.
$expect('directory-refused-by-name', [
    'REFUSED' => 'REFUSED',
    'is a directory' => 'is a directory',
    'EISDIR' => 'absent',
    'exit' => 'nonzero',
], $node.' '.$q($tool).' '.$q($root.'/Documentation/Diagrams'));

// 7. two files at once. The footer is the census the reader quotes, so it must count both and separate the clean one.
$expect('footer-counts-both-files', [
    'figures read: 2' => 'figures read: 2',
    'crossing pairs 1 over 1 figure(s)' => 'crossing pairs 1 over 1 figure(s)',
    'clean: 1 of 2 figure(s)' => 'clean: 1 of 2 figure(s)',
    'exit' => 'nonzero',
], $node.' '.$q($tool).' '.$q($cross).' '.$q($clean));

echo "\n";
echo $bad === 0
    ? "ALL KILLED: {$legs} leg(s), 0 bad\n"
    : "{$bad} BAD of {$legs} leg(s) — the census is not proved\n";
exit($bad === 0 ? 0 : 1);
