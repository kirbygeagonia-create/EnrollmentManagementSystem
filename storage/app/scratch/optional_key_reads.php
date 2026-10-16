<?php

// Read-only audit scratch tool (gitignored path).
// Flags `$validated['x']` reads whose validation rule never says "required" —
// an omitted field then raises an Undefined array key error inside the request
// handler (500), which is how Admission/Evaluation intake was failing.

$repo = dirname(__DIR__, 3);

$files = array_merge(
    glob($repo.'/app/Http/Controllers/*.php'),
    glob($repo.'/app/Http/Controllers/*/*.php')
);

$entryPattern = '/^[ \t]*\'([a-zA-Z0-9_.*]+)\'[ \t]*=>[ \t]*(\[[^\n]*\]|[\'"][^\'"]*[\'"])/m';
$readPattern = '/\$(validated|data|rules)\[\'([a-zA-Z0-9_.]+)\'\](?!\s*\?\?)/';

$total = 0;
foreach ($files as $file) {
    $src = file_get_contents($file);
    preg_match_all($entryPattern, $src, $rules, PREG_SET_ORDER);

    $optional = [];
    foreach ($rules as $r) {
        $ruleText = $r[2];
        if (! str_contains($ruleText, 'required')) {
            $optional[$r[1]] = true;
        }
    }
    if ($optional === []) {
        continue;
    }

    $lines = explode("\n", $src);
    $hits = [];
    foreach ($lines as $i => $line) {
        if (preg_match($readPattern, $line, $m)) {
            $key = $m[2];
            if (isset($optional[$key])) {
                $hits[] = '  '.($i + 1).': '.trim($line);
            }
        }
    }
    if ($hits !== []) {
        $total += count($hits);
        echo "\n".str_replace($repo.'/', '', $file)."\n";
        echo implode("\n", $hits)."\n";
        $keys = [];
        foreach ($hits as $h) {
            if (preg_match($readPattern, $h, $m) && isset($optional[$m[2]])) {
                $keys[] = $m[2];
            }
        }
        echo '  keys: '.implode(', ', array_unique($keys))."\n";
    }
}

echo "\nTotal unguarded optional-key reads: $total\n";
