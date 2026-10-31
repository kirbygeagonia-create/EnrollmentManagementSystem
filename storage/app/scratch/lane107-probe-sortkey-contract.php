<?php
/**
 * Lane 107 probe: does every page's `sortKey` name a column its own handler lists?
 *
 * `DataTable.jsx` renders a header only when `sort.available` carries the key, so a mismatch should be invisible —
 * but that guard reads the echo of `$sortable`, and a page can still write a `sortKey` the handler dropped while the
 * allow-list and the prop are edited in two different files. A header the handler would not obey is the defect §28.3
 * owns for filters; this asks the same question of the order, over all eight wired screens at once, from source
 * bytes only. Read-only.
 */
$root = dirname(__DIR__, 3);

$pages = [
    'Registrar' => ['app/Http/Controllers/Registrar/RegistrarController.php', 'resources/js/Pages/Registrar/Index.jsx', '$sortable', 'registrar'],
    'Assessment' => ['app/Http/Controllers/Assessment/AssessmentController.php', 'resources/js/Pages/Assessment/Index.jsx', '$sortable', 'assessment'],
    'Accounting' => ['app/Http/Controllers/Accounting/AccountingController.php', 'resources/js/Pages/Accounting/Index.jsx', '$sortable', 'accounting'],
    'Clearance' => ['app/Http/Controllers/Clearance/ClearanceController.php', 'resources/js/Pages/Clearance/Index.jsx', '$sortable', 'clearance'],
    'Clinic' => ['app/Http/Controllers/Clinic/ClinicController.php', 'resources/js/Pages/Clinic/Index.jsx', '$sortable', 'clinic'],
    'ID' => ['app/Http/Controllers/ID/IDController.php', 'resources/js/Pages/ID/Index.jsx', '$sortable', 'id'],
    'Admission' => ['app/Http/Controllers/Admission/AdmissionController.php', 'resources/js/Pages/Admission/Index.jsx', '$sortable', 'admission'],
    'Students' => ['app/Http/Controllers/StudentController.php', 'resources/js/Pages/Students/Index.jsx', '$sortable', 'students'],
];

$bad = 0;
foreach ($pages as $desk => [$ctrlPath, $pagePath, $var, $routeBase]) {
    $ctrl = $root.'/'.$ctrlPath;
    $page = $root.'/'.$pagePath;
    if (! is_file($ctrl) || ! is_file($page)) {
        printf("%-11s SKIP  missing file (%s | %s)\n", $desk, is_file($ctrl) ? 'ctrl ok' : 'NO CTRL', is_file($page) ? 'page ok' : 'NO PAGE');
        $bad++;
        continue;
    }
    $c = (string) file_get_contents($ctrl);
    if (preg_match('/\\'.$var.' = \[(.*?)\n        \];/s', $c, $m) !== 1) {
        printf("%-11s REFUSED  cannot read %s from %s\n", $desk, $var, basename($ctrlPath));
        $bad++;
        continue;
    }
    // Keys are read at the allow-list's OWN indentation. The first cut matched any `'name' => [`, which also caught
    // the nested `'joins' => [` of a two-hop spec, so the handler looked like it listed more columns than it does and
    // a genuine ghost could have slipped through the widened set.
    preg_match_all("/^ {12}'([A-Za-z][A-Za-z0-9]*)' => \[/m", $m[1], $keys);
    $allowed = $keys[1];
    if ($allowed === []) {
        printf("%-11s REFUSED  no allow-list key read at 12 spaces in %s — the shape moved\n", $desk, basename($ctrlPath));
        $bad++;
        continue;
    }

    $p = (string) file_get_contents($page);
    preg_match_all("/sortKey: '([A-Za-z0-9]+)'/", $p, $pm);
    $offered = array_values(array_unique($pm[1]));

    $ghost = array_values(array_diff($offered, $allowed));
    $unlit = array_values(array_diff($allowed, $offered));
    printf(
        "%-11s handler lists %2d | page offers %d | GHOST (header the handler would ignore): %s | no-header keys: %s\n",
        $desk,
        count($allowed),
        count($offered),
        $ghost === [] ? 'none' : implode(',', $ghost),
        $unlit === [] ? 'none' : implode(',', $unlit)
    );
    if ($ghost !== []) {
        $bad++;
    }
}

echo $bad === 0
    ? "VERDICT: every sortKey on all eight wired pages names a column its own handler lists.\n"
    : sprintf("%d desk(s) need reading.\n", $bad);
exit($bad === 0 ? 0 : 1);
