<?php

/**
 * Pass 179c — the packet ruling 4 asked for, and the two places the authority document must point
 * at it.
 *
 * §28.7 row 2 says what closed looks like: "Minutes. Each answered becomes a row in §26 or §12; each
 * unanswered stays here." The fourteen unanswered items were read out of the registers themselves on
 * 2026-10-23 — the eight §28.2/§28.3 rows whose "Who must act" column still names an office
 * (C-2, C-4, C-5, C-6, C-7, C-8, C-10, G-13), the five §28.5 rows whose answer ends in a question
 * the school owns, and the two §25.4 print items that are not code decisions at all (P-16 the name,
 * P-2 the blank form) — and became Documentation/School-Decision-Sheet-2026-10-23.md.
 *
 * Two claims in the packet were measured rather than recalled before they were written: live `ems`
 * answers 0 of 16 programs with entranceExamExemptsTransferee set and 5 with
 * requiresCourseSpecificExam set, which is what items 6 and 9 say.
 *
 * The pass also repairs a doubled full stop at the end of §28.5's opening paragraph, found while
 * reading that paragraph for the pointer.
 *
 * DRY RUN unless --apply.
 */

require_once __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$packet = 'Documentation/School-Decision-Sheet-2026-10-23.md';
$guard(is_file($root.'/'.$packet), 'the packet exists, so the document may cite it');
$flags = DB::select('SELECT SUM(entranceExamExemptsTransferee=1) w, SUM(requiresCourseSpecificExam=1) c, COUNT(*) n FROM courses')[0];
$guard((int) $flags->w === 0 && (int) $flags->c === 5,
    'item 6 and item 9 state what live ems holds today (waivers '.$flags->w.' of '.$flags->n.', board flags '.$flags->c.')');
$sheet = (string) file_get_contents($root.'/'.$packet);
$absent = [];
foreach (['C-2', 'C-4', 'C-5', 'C-6', 'C-7', 'C-8', 'C-10', 'G-13', 'P-2', 'P-16'] as $id) {
    if (! str_contains($sheet, $id)) {
        $absent[] = $id;
    }
}
$guard($absent === [], $absent === []
    ? 'every register id the packet claims to ask is named in it'
    : 'ids the packet does not name: '.implode(', ', $absent));
$guard(substr_count(strtolower($sheet), 'initial: ..........') >= 13,
    'each item carries a line to initial (read '.substr_count(strtolower($sheet), 'initial: ..........').')');

$textEdits = [
    ['Documentation/Registrar-Print-Decision-Sheet-2026-10-23.md', $packet],
    [
        'Nothing in this table asks for code..',
        'Nothing in this table asks for code. The fourteen that do wait on an office or on whoever '
            .'provisions the server were put on one sheet for a single meeting on 2026-10-23: '
            .$packet.'.',
    ],
];

$cellEdits = [
    ['Table the decisions in 28.5 with the registrar', 'Minutes. Each answered becomes a row',
        'Minutes. Each answered becomes a row in §26 or §12; each unanswered stays here. Tabled 2026-10-23 as '
            .$packet.' — fourteen items, each naming the register row it closes and carrying a line to initial.'],
];

docxApply($src, $root.'/storage/app/scratch/pass179c-backup.docx', $textEdits, $cellEdits, $apply);
