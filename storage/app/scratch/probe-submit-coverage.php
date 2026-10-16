<?php
/**
 * Does the audit SEE every submit button? Count, with the audit's own regex, how many
 * <button> elements carry type="submit" and how many of those are split into the two branches
 * (explicit form="id" vs relying on nesting). If the branches do not add up to the total, the
 * audit has a blind spot and its "no findings" means less than it looks like.
 */
$root = dirname(__DIR__, 3).'/resources/js';
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
        $files[] = $f->getPathname();
    }
}
sort($files);

$total = $withForm = $withoutForm = $otherTag = 0;

foreach ($files as $file) {
    $src = file_get_contents($file);

    // Any element with type="submit" — button or otherwise.
    if (preg_match_all('/<[a-z]+\b[^>]*?type="submit"[^>]*?>/s', $src, $all, PREG_OFFSET_CAPTURE)) {
        foreach ($all[0] as $m) {
            $total++;
            if (! str_contains($m[0], '<button')) {
                $otherTag++;
                echo 'NON-BUTTON: '.basename($file).'  '.trim(preg_replace('/\s+/', ' ', substr($m[0], 0, 70)))."\n";
                continue;
            }
            str_contains($m[0], 'form="') ? $withForm++ : $withoutForm++;
        }
    }
}

printf("elements with type=\"submit\": %d\n  <button> with form=\"id\": %d\n  <button> relying on nesting: %d\n  other tags: %d\n  adds up: %s\n",
    $total, $withForm, $withoutForm, $otherTag,
    ($withForm + $withoutForm + $otherTag === $total) ? 'yes' : 'NO — the audit has a blind spot');
