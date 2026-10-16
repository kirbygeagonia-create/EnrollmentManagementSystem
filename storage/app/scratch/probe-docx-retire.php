<?php
/**
 * Every place the document still describes the retired right, or quotes a count it changed.
 *
 * Deleting a policy method invalidates the sentences that said it was declared and never called —
 * those were written on 2026-10-07 to correct an even older claim. It also moves the permission and
 * grant totals quoted in the RBAC chapter and the register. This prints each hit with its enclosing
 * paragraph or cell so the rewrite is faithful to its neighbours. Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));

$needles = [
    'verifyGeneralExam',
    'exam.verify.general',
    'ExamPolicy.php:57-63',
    '95 permissions',
    '95 ',
    '317',
    'Exam module (5)',
    'five permissions',
    '15 modules',
    '17 roles',
    'called by nothing',
    'declared and',
    'unenforced with it',
];

foreach ($needles as $n) {
    $c = substr_count($plain, $n);
    printf("\n%-24s raw=%d plain=%d\n", $n, substr_count($xml, $n), $c);
    $scan = 0;
    for ($k = 0; $k < 3 && ($p = strpos($plain, $n, $scan)) !== false; $k++) {
        echo '   @'.$p.'  '.mb_substr(preg_replace('/\s+/', ' ', substr($plain, max(0, $p - 100), 240)), 0, 230)."\n";
        $scan = $p + 1;
    }
}
