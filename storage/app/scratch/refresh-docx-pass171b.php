<?php

/**
 * Pass 171b — the four things pass 171 wrote wrongly while correcting what the document had wrong.
 *
 * Self-correction, measured against the re-dumped document rather than against intent:
 *  - a dropped closing parenthesis: "into config(‘settings.*’ at boot" should be "config(‘settings.*’) at boot";
 *  - "configuration that does not exist in this repository" then listed FOUR keys, and the fourth,
 *    clearanceReplacementFee, is read by no template and is not a row in the table — a migration dropped it and
 *    Reference Data owns the amount (P-12);
 *  - " (twelve files today: app, auth, …)" sat directly after "There is no config/settings.php", so it read as
 *    a list of files that do not exist. It is the directory's contents; say which;
 *  - pass 171 left "grep finds … in the six print views and nowhere else … so the settings table does reach the
 *    paper". The consequence does not follow from the grep: what carries the table into those calls is the
 *    provider, and that is the link the previous edition missed.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

// The table's own keys, read from the seeder rather than remembered.
$seeder = (string) file_get_contents($root.'/database/seeders/SettingsSeeder.php');
preg_match_all("/'([a-zA-Z]+)' => \[\s*'value'/", $seeder, $mm);
$keys = $mm[1] ?? [];
if ($keys !== ['schoolName', 'schoolAddress', 'schoolPhone']) {
    printf("REFUSED: the seeder seeds %s, not the three keys this pass names.\n", implode(', ', $keys) ?: '(nothing)');
    exit(1);
}
foreach (glob($root.'/resources/views/prints/*.blade.php') ?: [] as $f) {
    if (str_contains((string) file_get_contents($f), 'settings.clearanceReplacementFee')) {
        exit("REFUSED: a template still reads settings.clearanceReplacementFee.\n");
    }
}

$textEdits = [
    [
        'into config(‘settings.*’ at boot',
        'into config(‘settings.*’) at boot',
    ],
    [
        'build their header from configuration that does not exist in this repository',
        'build their header from configuration keys that no file in this repository defines',
    ],
    [
        '. There is no ',
        '. The fourth key this paragraph used to list, clearanceReplacementFee, is read by no template and is not '
            . 'a row in the table: a migration dropped it and Reference Data owns the amount (P-12). There is no ',
        'The six Blade print templates build their header',
    ],
    [
        ' (twelve files today: app, auth,',
        ' (the directory holds twelve files today: app, auth,',
    ],
    [
        ' in the six print views and nowhere else in the repository, so the ',
        ' in the six print views and in no screen or controller, and what carries the table into those calls is the '
            . 'provider, so the ',
    ],
];

docxApply($src, $root.'/storage/app/scratch/pass171b-backup.docx', $textEdits, [], $apply);
