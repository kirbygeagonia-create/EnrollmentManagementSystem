<?php

/**
 * Pass 171 — §25.5's letterhead paragraph describes a bridge that was built twenty days before it was read.
 *
 * Measured 2026-10-21, not recalled:
 *  - app/Providers/SettingsServiceProvider.php:20-24 copies every `settings` row into `config('settings.*')`
 *    at boot, and bootstrap/providers.php:12 registers it. It landed 2026-10-01 in 29b5532.
 *  - So `config('settings.schoolName')` in a print template answers the TABLE's value. Booted against live
 *    `ems` it returned "Southeast Asian Institute of Technology" — the row's title-case spelling, not the
 *    upper-case literal the template passes as its fallback. The paragraph said the opposite: that every call
 *    falls through to the inline default and the name prints as a fallback.
 *  - What is actually blank is the address and the phone: the table holds 3 rows, those two seeded '' by
 *    SettingsSeeder with a description that names the Registrar as the office that owes them.
 *  - Six print templates read `config('settings.`, not five; `config/` holds twelve files, and the paragraph's
 *    own enumeration omits school.php — the file lane 51 added for the school's own zone.
 *  - The seal: all six ask PrintAssets::logoDataUri(), which embeds public/images/logos/seait-logo.png
 *    (PrintAssets.php:21-30). The paragraph still said the templates request asset('images/logo.png'), a file
 *    that does not exist, and that a PDF therefore prints with no logo.
 *
 * Each guard below re-reads the repository, so the pass refuses rather than writing a figure the tree no
 * longer supports. DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// ---------------------------------------------------------------- guards on the measurements

$views = glob($root.'/resources/views/prints/*.blade.php') ?: [];
$readers = [];
foreach ($views as $f) {
    if (str_contains((string) file_get_contents($f), "config('settings.")) {
        $readers[] = basename($f);
    }
}
if (count($readers) !== 6) {
    printf("REFUSED: %d print templates read config('settings.', not 6 — %s\n",
        count($readers), implode(', ', $readers));
    exit(1);
}

$provider = (string) file_get_contents($root.'/app/Providers/SettingsServiceProvider.php');
if (! str_contains($provider, 'config(["settings.') || ! str_contains($provider, 'public function boot')) {
    exit("REFUSED: SettingsServiceProvider no longer hydrates config from the table — re-read this paragraph before writing it.\n");
}
if (! str_contains((string) file_get_contents($root.'/bootstrap/providers.php'), 'SettingsServiceProvider::class')) {
    exit("REFUSED: the provider is not registered in bootstrap/providers.php.\n");
}
$assets = (string) file_get_contents($root.'/app/Support/PrintAssets.php');
if (! str_contains($assets, 'logoDataUri') || ! str_contains($assets, "images/logos/seait-logo.png")) {
    exit("REFUSED: PrintAssets no longer reads the seal from public/images/logos/seait-logo.png.\n");
}
if (! is_file($root.'/public/images/logos/seait-logo.png')) {
    exit("REFUSED: the seal file the helper reads is not on this box.\n");
}
$embedded = 0;
foreach ($views as $f) {
    if (str_contains((string) file_get_contents($f), 'PrintAssets::logoDataUri')) {
        $embedded++;
    }
}
if ($embedded !== 6) {
    printf("REFUSED: %d of 6 templates embed the seal through PrintAssets.\n", $embedded);
    exit(1);
}
if (is_file($root.'/public/images/logo.png')) {
    exit("REFUSED: public/images/logo.png exists again — the paragraph this pass replaces may be true.\n");
}
$cfg = array_values(array_map('basename', glob($root.'/config/*.php') ?: []));
sort($cfg);
if (count($cfg) !== 12 || ! in_array('school.php', $cfg, true)) {
    printf("REFUSED: config/ holds %d files (%s); the sentence being written enumerates twelve including school.php.\n",
        count($cfg), implode(', ', $cfg));
    exit(1);
}
$controller = (string) file_get_contents($root.'/app/Http/Controllers/Admin/UserManagementController.php');
if (! str_contains($controller, 'public function settings') || ! str_contains($controller, 'public function updateSetting')) {
    exit("REFUSED: the settings screen's two actions are not where the row cites them.\n");
}
$test = (string) file_get_contents($root.'/tests/Feature/Admin/StaffAccountManagementTest.php');
if (! str_contains($test, "config(['settings.schoolName' => 'stale'])")) {
    exit("REFUSED: the test that pins the bridge no longer writes a stale config value.\n");
}

// ---------------------------------------------------------------- the edits

$textEdits = [
    [
        'The five Blade templates build their header from configuration that does not exist in this repository',
        'The six Blade print templates build their header from configuration that does not exist in this repository',
    ],
    [
        ' (the directory holds app, auth, cache, database, filesystems, logging, mail, permission, queue, services, session), so every one of those calls falls through to its inline default — the school name prints as the literal fallback, address and phone print as empty strings. ',
        ' (twelve files today: app, auth, cache, database, filesystems, logging, mail, permission, queue, school, services, session), so a schema with no settings rows really does fall through to the inline default. A seeded one does not. SettingsServiceProvider.php:20-24 copies every row of the settings table into config('
            ."‘settings.*’ at boot, and bootstrap/providers.php:12 registers it, so the value an admin maintains is the value the template reads. Measured on a booted app against live `ems`: config('settings.schoolName') answers “Southeast Asian Institute of Technology” — the table's title-case spelling, not the upper-case literal the call passes as its fallback — and the address and phone print empty because those two rows are blank, not because the lookup missed. ",
    ],
    [
        ' in the five print views and nowhere else, so the ',
        " in the six print views and nowhere else in the repository, so the ",
    ],
    [
        ') — has no effect on any printed document, and holds 3 rows on the live database, written by SettingsSeeder.',
        ') — does reach the paper, which is the one thing the previous edition said it could not do. The table holds 3 rows on live `ems`, written by SettingsSeeder: schoolName carries “Southeast Asian Institute of Technology”, and schoolAddress and schoolPhone are seeded blank, each with its own description naming the Registrar as the office that owes it. So the letterhead prints the maintained name and prints nothing where the school has not supplied a value — and StaffAccountManagementTest.php:509-514 pins the bridge itself, by writing a stale config value and requiring the row on disk to answer again.',
    ],
];

$paraEdits = [
    [
        'The seal is broken the same way',
        'The seal is no longer asked for by path. All six templates render the same helper, and PrintAssets.php:21-30 reads public/images/logos/seait-logo.png and returns it as a base64 data URI, so the paper carries the file the repository actually holds and a PDF never has to reach the web root to find it — which is also what removed the round trip into the dev server that once deadlocked the single-threaded print (P-7). The onerror handler is still on the tag, and it now hides only the case the helper itself answers with an empty string: a build where the logo file is absent. The desk-side print preview keeps the plain path, and PrintLayout.jsx:30 names the same file the helper reads.',
    ],
];

$cellEdits = [
    [
        'Partly closed, and the remainder is named.',
        'Partly closed, and the remainder is named.',
        'Partly closed, and the remainder is narrower than this row used to say. The letterhead image is no longer a path to a file that does not exist: it is embedded as a data URI, which also removed the round trip into the dev server that once deadlocked the single-threaded print. Nor is the school name a code default: SettingsServiceProvider.php:20-24 hydrates config('
            ."‘settings.*’) from the settings table at boot, so what an admin edits does reach the printed document, and StaffAccountManagementTest.php:509-514 pins it. Measured 2026-10-21 on live `ems`: the name prints from the table, and the address and the phone print nothing because their two rows are blank.",
        1,
    ],
    [
        'Partly closed, and the remainder is named.',
        'Open, and it is a data question:',
        'Open, and it is a data question with its owner and its screen now named: the two blank values. Admin → User Management → Settings writes them (UserManagementController.php:471-491) under the settings.manage right, which live `ems` grants to one role, SysAdmin, seated on one account. The field validates required, so a value once supplied cannot be blanked again. What the Registrar owes the paper is the campus address and the contact number; the seeder leaves both empty and says so in the row’s own description.',
        1,
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass171-backup.docx', $textEdits, $cellEdits, $apply, $paraEdits);
