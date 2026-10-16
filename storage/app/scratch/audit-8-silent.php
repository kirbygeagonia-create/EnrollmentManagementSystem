<?php
/**
 * Audit lane 8 — the ways this system can be wrong without saying so.
 *
 * Four shapes, each printed with enough context that a human can decide, and each deliberately
 * conservative: a hit here is a candidate to read, not a verdict. Today has already shown what
 * happens when a count is mistaken for a conclusion.
 */
$root = str_replace('\\', '/', dirname(__DIR__, 3));

function source(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(php|jsx|js)$/', $f->getFilename())) {
            $out[str_replace('\\', '/', $f->getPathname())] = (string) file_get_contents($f->getPathname());
        }
    }
    ksort($out);

    return $out;
}

$app = source($root.'/app');
$js = source($root.'/resources/js');

// ---- 1. swallowed errors -------------------------------------------------------
$swallow = [];
foreach ($app as $file => $src) {
    $short = str_replace($root.'/app/', '', $file);
    foreach (preg_split('/\R/', $src) as $i => $line) {
        if (preg_match('/catch\s*\([^)]*\)\s*\{\s*\}/', $line) || preg_match('/^\s*@\s*[a-zA-Z$]/', $line)) {
            $swallow[] = $short.':'.($i + 1).'  '.trim(mb_substr($line, 0, 90));
        }
    }
    // a catch whose body is only a comment or only a return of the same value
    if (preg_match_all('/catch\s*\([^)]*\)\s*\{\s*(\/\/[^\n]*)?\s*\}/s', $src, $m)) {
        foreach ($m[0] as $hit) {
            $swallow[] = $short.'  empty catch: '.preg_replace('/\s+/', ' ', mb_substr($hit, 0, 80));
        }
    }
}
printf("== 1. swallowed errors in app/: %d\n", count($swallow));
foreach (array_slice($swallow, 0, 10) as $s) {
    echo '   '.$s."\n";
}

// ---- 2. unbounded reads feeding a screen --------------------------------------
$unbounded = [];
foreach ($app as $file => $src) {
    if (! str_contains($file, 'Controllers')) {
        continue;
    }
    $short = str_replace($root.'/app/Http/Controllers/', '', $file);
    foreach (preg_split('/\R/', $src) as $i => $line) {
        if (preg_match('/->get\(\)/', $line) && ! preg_match('/->paginate\(|->limit\(|->take\(|chunk/', $line)) {
            $unbounded[] = $short.':'.($i + 1).'  '.trim(mb_substr($line, 0, 100));
        }
    }
}
printf("\n== 2. controller reads with ->get() and no bound: %d\n", count($unbounded));
foreach (array_slice($unbounded, 0, 12) as $u) {
    echo '   '.$u."\n";
}
if (count($unbounded) > 12) {
    echo '   …'.(count($unbounded) - 12)." more\n";
}

// ---- 3. money arithmetic in floats --------------------------------------------
$money = [];
foreach ($app as $file => $src) {
    $short = str_replace($root.'/app/', '', $file);
    foreach (preg_split('/\R/', $src) as $i => $line) {
        if (preg_match('/(amount|balance|charge|paid|total|fee|coverage)/i', $line)
            && preg_match('/(\*\s*0\.|\/\s*100|\+\s*[A-Za-z_$.]*[Aa]mount|[Aa]mount[^\n]*[-+*\/]\s*[\d.]+)/', $line)
            && ! preg_match('/bcdiv|bcmul|round\(|number_format|integer|int\)/', $line)) {
            $money[] = $short.':'.($i + 1).'  '.trim(mb_substr($line, 0, 100));
        }
    }
}
printf("\n== 3. arithmetic on a money-named value with no rounding helper: %d\n", count($money));
foreach (array_slice($money, 0, 10) as $m) {
    echo '   '.$m."\n";
}

// ---- 4. policies that get a record but never scope it --------------------------
echo "\n== 4. policy methods that take a model and never mention office/unit/user ownership\n";
foreach ($app as $file => $src) {
    if (! str_contains($file, '/Policies/')) {
        continue;
    }
    $short = str_replace($root.'/app/Policies/', '', $file);
    preg_match_all('/public function (\w+)\([^)]*\$user[^)]*,\s*\??(\w+)\s+\$\w+\)\s*:\s*\w+\s*\{(.*?)\n    \}/s', $src, $methods, PREG_SET_ORDER);

    foreach ($methods as $m) {
        [, $method, $type, $body] = $m;

        if (preg_match('/(ffice|nitId|userId|studentId|ownedBy|By\b|staff)/', $body)) {
            continue;
        }

        if (preg_match('/return\s+\$user->can\(/', $body) && ! preg_match('/&&/', $body)) {
            printf("   %-34s %-22s permission only, no record scope\n", $short, $method.'('.$type.')');
        }
    }
}

// ---- 5. frontend: a prop read that no controller supplies is lane 4's job; this checks the other half
$destructure = [];
foreach ($js as $file => $src) {
    if (! str_contains($file, '/Pages/')) {
        continue;
    }
    if (preg_match_all('/const \{([^}]+)\} = usePage\(\)\.props/', $src, $m)) {
        $destructure[] = str_replace($root.'/resources/js/', '', $file).'  '.trim(preg_replace('/\s+/', ' ', $m[1][0]));
    }
}
printf("\n== 5. pages reading usePage().props directly (lane 4 covered these once): %d\n", count($destructure));
foreach (array_slice($destructure, 0, 8) as $d) {
    echo '   '.$d."\n";
}
