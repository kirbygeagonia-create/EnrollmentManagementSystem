<?php
/**
 * UI/UX lane 0 — measure before proposing. Every number here is a count of something in
 * resources/js, so a claim can be re-checked by re-running this file rather than argued about.
 */
$root = dirname(__DIR__, 3).'/resources/js';

function files(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && preg_match('/\.(jsx|js)$/', $f->getFilename())) {
            // Windows yields backslash paths while $dir may carry slashes; one key shape or
            // every lookup below misses.
            $out[] = str_replace('\\', '/', $f->getPathname());
        }
    }
    sort($out);

    return $out;
}

$root = str_replace('\\', '/', $root);

$all = files($root);
$pages = files($root.'/Pages');

$read = fn (string $p): string => (string) file_get_contents($p);
$src = [];
foreach ($all as $f) {
    $src[$f] = $read($f);
}

$line = fn (string $key): string => str_replace($root.'/', '', $key);
$out = function (string $title, array $hits, int $show = 12) use ($line): void {
    print("== {$title}\n   ".count($hits)." hit(s)\n");
    foreach (array_slice($hits, 0, $show) as $h) {
        echo '   - '.$h."\n";
    }
    if (count($hits) > $show) {
        echo '   …'.(count($hits) - $show)." more\n";
    }
};

// 1. Hard-coded colours: a hex or rgb() literal in JSX bypasses the token layer in app.css.
$hex = [];
foreach ($src as $f => $s) {
    foreach (preg_split('/\R/', $s) as $i => $l) {
        if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $l) && ! str_contains($l, '//')) {
            $hex[] = $line($f).':'.($i + 1).'  '.trim(mb_substr($l, 0, 80));
        }
    }
}
$out('1. hard-coded colour literals (bypass the tokens)', $hex);

// 2. Arbitrary Tailwind values — text-[13px], w-[3%] and friends are a second, unthemed scale.
$arb = [];
foreach ($src as $f => $s) {
    if (preg_match_all('/[a-z]+-\[\d+[^\]]*\]/', $s, $m)) {
        foreach ($m[0] as $hit) {
            $arb[] = $line($f).'  '.$hit;
        }
    }
}
$out('2. arbitrary-value utilities (off the type/spacing scale)', $arb);

// 3. Which pages use the shared kit at all.
$noKit = [];
$kitless = [];
foreach ($pages as $f) {
    $s = $src[$f];
    $imports = preg_match_all("/from '\\/.*(ui|Components)/", $s) ? true : false;
    $usesUi = preg_match('/from ['."']@\/Components\/ui/", $s) || preg_match('/from ['."']\/Components\/ui/", $s) || str_contains($s, "Components/ui");
    $usesClass = (bool) preg_match('/className="[^"]*\b(btn|card|badge|data-table|form-input|empty-state)\b/', $s);
    if (! $usesUi && ! $usesClass) {
        $noKit[] = $line($f);
    }
    if (! $usesUi && $usesClass) {
        $kitless[] = $line($f).'  (raw classes only)';
    }
}
$out('3a. pages using NEITHER a ui/ component nor a token class', $noKit);
$out('3b. pages styling with raw token classes but importing no shared component', $kitless);

// 4. Destructive verbs without a confirmation component.
$deletes = [];
foreach ($pages as $f) {
    $s = $src[$f];
    $n = preg_match_all('/router\.(delete|post|patch)\(\s*route\(\s*[\'"][^\'"]*(destroy|delete|drop|withdraw|revoke|refund|void)/i', $s);
    if ($n > 0) {
        $confirms = preg_match_all('/CauseEffectModal|ConfirmDialog/', $s);
        $deletes[] = $line($f)."  destructive calls: {$n}, confirm components: {$confirms}";
    }
}
$out('4. pages firing a destructive request (and whether they confirm first)', $deletes);

// 5. window.confirm / alert — the browser's own dialog instead of the app's.
$native = [];
foreach ($src as $f => $s) {
    if (preg_match('/window\.(confirm|alert)\(|[^.\w]alert\(/', $s)) {
        $native[] = $line($f);
    }
}
$out('5. native window.confirm/alert left in the app', $native);

// 6. Submit-in-flight protection: forms that can be submitted twice.
$forms = [];
foreach ($pages as $f) {
    $s = $src[$f];
    $submits = preg_match_all('/router\.(post|put|patch)\(/', $s);
    if ($submits === 0) {
        continue;
    }
    $guard = preg_match('/(submitting|saving|busy|loading|inFlight|processing)[A-Za-z]*\s*(&&|\?|\))/i', $s)
        || preg_match('/disabled=\{[^}]*(submitting|saving|loading|busy|processing)/i', $s);
    if (! $guard) {
        $forms[] = $line($f)."  router writes: {$submits}, no in-flight guard found";
    }
}
$out('6. pages that POST/PATCH with no visible in-flight guard', $forms);

// 7. Accessibility surface: aria usage, and click handlers on non-buttons.
$aria = 0;
$divClick = [];
foreach ($src as $f => $s) {
    $aria += preg_match_all('/aria-[a-z]+=/', $s);
    if (preg_match_all('/<div[^>]*onClick=/s', $s, $m)) {
        foreach ($m[0] as $hit) {
            $divClick[] = $line($f).'  '.preg_replace('/\s+/', ' ', mb_substr($hit, 0, 70));
        }
    }
}
print("== 7. accessibility\n   aria attributes across the app: {$aria}\n");
$out('7b. onClick on a <div> (not keyboard-reachable)', $divClick);

// 8. Empty states: list pages that render a table but never say what an empty one means.
$noEmpty = [];
foreach ($pages as $f) {
    $s = $src[$f];
    $hasTable = str_contains($s, '<DataTable') || str_contains($s, 'data-table');
    if (! $hasTable) {
        continue;
    }
    if (! str_contains($s, 'EmptyState') && ! str_contains($s, 'empty-state') && ! preg_match('/length === 0|isEmpty|no .{0,24}found/i', $s)) {
        $noEmpty[] = $line($f);
    }
}
$out('8. table pages with no empty-state path found', $noEmpty);

// 9. Hand-rolled status vocabulary: pages defining their own tone/label maps.
$maps = [];
foreach ($pages as $f) {
    $s = $src[$f];
    if (preg_match('/(tone|Tone|statusMap|labelFor|badgeFor)[A-Za-z]*\s*=\s*\{/', $s)) {
        $maps[] = $line($f);
    }
}
$out('9. pages with a local tone/label map (statusLabel.js is the shared one)', $maps);

printf("\nscanned %d files (%d pages) under resources/js\n", count($all), count($pages));
