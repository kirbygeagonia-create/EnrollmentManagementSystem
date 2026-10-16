<?php

/**
 * DEPLOYMENT.md §2/§3/§8 — the private folder is reachable by path, and one secret keeps it shut.
 *
 * Three claims in this document were disproved by measurement on 2026-10-12:
 *
 *   1. "`storage/app/private`, which no web path reaches" — Laravel registers GET and PUT
 *      `/storage/{path}` for the `local` disk, which is rooted exactly there, with no auth
 *      middleware on either (their gathered middleware prints as `(NONE)`).
 *   2. "written to the **default** disk" — both upload sites name the private disk
 *      (`AdmissionController.php:278`, `IDController.php:144`); nothing writes through the default.
 *   3. the silent corollary: since a signature is the only gate, `APP_KEY` and one config line
 *      (`'visibility' => 'public'`) are load-bearing in a way §2 never mentioned.
 *
 * Every edit asserts it matched exactly once, in a file read as bytes with its CRLF kept, and the
 * script refuses to write if any anchor is missing, duplicated, or lands twice after the fact.
 * It then re-reads the result and checks no doubled carriage return crept in — the defect that
 * made an earlier pass invisible to a CRLF counter.
 */

$file = dirname(__DIR__, 3).'/DEPLOYMENT.md';
$raw = file_get_contents($file);
$eol = str_contains($raw, "\r\n") ? "\r\n" : "\n";

echo 'file read: '.strlen($raw)." bytes, line ending ".($eol === "\r\n" ? 'CRLF' : 'LF')."\n";

/** @return list<array{0:string,1:string}> */
$block = implode($eol, [
    '**Two routes answer with no login, and one secret stands in front of them.** Because',
    '`config/filesystems.php` gives the `local` disk `'."'".'serve'."'".' => true`, Laravel registers',
    '`GET|HEAD /storage/{path}` and `PUT /storage/{path}` for it **outside every middleware group** —',
    'not `auth`, not `web`, no CSRF token. Nothing in the application ever asks for a signature',
    '(`temporarySignedRoute` and `temporaryUrl` appear nowhere in `app/` or `resources/`), so in',
    'normal use the two are dead doors, and what stops a stranger walking through one is',
    '`ServeFile::hasValidSignature()` / `ReceiveFile::hasValidSignature()` — an HMAC of the path and',
    'query, keyed by `APP_KEY`. Measured 2026-10-12 by driving the route through the HTTP kernel',
    '(`storage/app/scratch/audit-24b-storage-routes.php`, pinned by `ServedUploadPathTest`): unsigned',
    '→ 403; signed with this install'."'".'s key → 204; signed with the **empty** secret, on an install',
    'that never ran `key:generate` → **204, and the file is written**. Two faults follow, and',
    '`php artisan ems:preflight` now fails the run for each:',
    '',
    '- **`APP_KEY` left blank.** That is the state `.env.example` leaves it in, and §3 corrects it with',
    '  one line. The damage reads as something else: every login throws on cookie encryption, so a',
    '  keyless server looks like a broken session while `/storage/{path}` — which needs no encryption',
    '  at all — keeps accepting uploads from anybody who can compute an HMAC over an empty key.',
    '- **`'."'".'visibility'."'".' => '."'".'public'."'".'` on the served disk.** That single line switches',
    '  `ServeFile`'."'".'s signature check off: the unsigned `GET` this configuration refuses with 403',
    '  then walks straight through and only a missing file answers 404 (measured the same way by',
    '  `audit-24c-servegate.php`). An operator adding it to chase a broken image would publish every',
    '  applicant paper and face photo, because the served `local` disk and the `documents` disk are',
    '  rooted at the same folder.',
]);

$edits = [
    // 1. the reachability claim.
    [
        '`storage/app/private`, which no web path reaches. Setting `FILESYSTEM_DISK=public` and',
        '`storage/app/private` — the same folder the `documents` disk writes to — and it is reachable',
        'at `/storage/{path}` for anyone holding a signature; see the two routes below. Setting',
        '`FILESYSTEM_DISK=public` and',
    ],
    // 2. which disk the uploads actually name.
    [
        'are written to the **default** disk and',
        'are written to the named **`documents`** disk and',
    ],
    // 3. the new block, inserted before the prune paragraph.
    [
        $eol.$eol.'To see what has accumulated there,',
        $eol.$eol.$block.$eol.$eol.'To see what has accumulated there,',
    ],
    // 4. what preflight says it checks.
    [
        'disk, the folders Laravel must write into, a browser for PDFs, and the queue connection — and',
        'disk, whether that served folder keeps its signature, the application key, the folders Laravel',
        'must write into, a browser for PDFs, and the queue connection — and',
    ],
    // 5. the smoke-test list gains the two rows.
    [
        'not stamp the same moment two different ways.',
        'not stamp the same moment two different ways, and `Application key` and `Served folder`, the two',
        'rows that check the routes nobody logs in to use (§2).',
    ],
];

// §8 numbered the same item twice, which the insert above does not fix on its own.
$renumber = [
    ['7. `php artisan ems:uploads:prune`', '8. `php artisan ems:uploads:prune`'],
    ['6. `php artisan ems:audit:record-states`', '7. `php artisan ems:audit:record-states`'],
    ['5. Download one paper from each desk', '6. Download one paper from each desk'],
    ['4. Admin → User Management creates', '5. Admin → User Management creates'],
    ['3. Login as the SysAdmin from §4 works', '4. Login as the SysAdmin from §4 works'],
    ['2. `/register` returns 404', '3. `/register` returns 404'],
];

foreach ($edits as $i => $edit) {
    $find = $edit[0];
    $replace = implode($eol, array_slice($edit, 1));
    $hits = substr_count($raw, $find);
    if ($hits !== 1) {
        fwrite(STDERR, sprintf("EDIT %d REFUSED: anchor matched %d times, expected exactly 1.\n  anchor: %s\n",
            $i + 1, $hits, str_replace($eol, ' / ', mb_substr($find, 0, 90))));
        exit(1);
    }
    $raw = str_replace($find, $replace, $raw);
    printf("edit %d applied (%d occurrence(s) replaced)\n", $i + 1, $hits);
}

foreach ($renumber as [$find, $replace]) {
    $hits = substr_count($raw, $find);
    if ($hits !== 1) {
        fwrite(STDERR, sprintf("RENUMBER REFUSED for '%s': %d matches.\n", $find, $hits));
        exit(1);
    }
    $raw = str_replace($find, $replace, $raw);
}
echo "renumbered §8 so the list runs 1..8\n";

file_put_contents($file, $raw);

$back = file_get_contents($file);
$doubled = substr_count($back, "\r\r\n");
$lfOnly = preg_match("/(?<!\r)\n/", $back);
printf("re-read: %d bytes, doubled-CR = %d, stray bare-LF = %s\n",
    strlen($back), $doubled, $lfOnly ? 'YES' : 'no');

foreach (['Two routes answer with no login', 'reachable', 'named **`documents`** disk',
    'whether that served folder keeps its signature', '`Application key` and `Served folder`'] as $needle) {
    $n = substr_count($back, $needle);
    printf("  %-52s present %d time(s) %s\n", $needle, $n, $n >= 1 ? '' : '← MISSING');
    if ($n < 1) {
        exit(1);
    }
}

foreach (['which no web path reaches', 'written to the **default** disk and'] as $gone) {
    $n = substr_count($back, $gone);
    printf("  %-52s remaining %d %s\n", $gone, $n, $n === 0 ? '(removed as intended)' : '← STILL THERE');
    if ($n !== 0) {
        exit(1);
    }
}

exit($doubled === 0 && ! $lfOnly ? 0 : 1);
