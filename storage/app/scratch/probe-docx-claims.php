<?php
/**
 * Sweep the document by CLAIM, not by file name.
 *
 * Pass 64 hunted "ExamController.php" and found the stale ranges; B-2's false sentence survived
 * that sweep because it describes the removed write without citing the file that used to make it.
 * So this probe asks the questions directly: which sentences still say the examination module
 * decides an application, that a program's flags are all unset, that a waiver does not exist, or
 * that some desk sets admissionStatus outside its own approve/reject route?
 *
 * Every hit is printed with its context for a human to judge. Nothing is written.
 */

$docx = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$z = new ZipArchive();
$z->open($docx);
$xml = $z->getFromName('word/document.xml');
$z->close();
$plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1));

$claims = [
    'exam decides the application' => '/(examination|exam|result)s? (therefore |directly )?(sets|decides|approves|rejects)/i',
    'no waiver exists' => '/no waiver path|without any waiver|cannot be waived/i',
    'Stage 2 always needs Stage 1' => '/must (also )?be passed first|general exam (must|has to) be passed/i',
    'flags are nobody\'s decision' => '/is the department.s (answer|call)|still the department|which programs set/i',
    'retention period is unwritten' => '/cannot state one|no retention period|has not supplied it/i',
    'transferee treated as freshman' => '/like a freshman|same rule as a (first|fresh) ?year|does not distinguish applicant types/i',
    'result matched per student' => '/joins on studentId|per student, not per|any program.*answers/i',
    'dead policy enforces' => '/enforced twice|independently requires|second enforcer/i',
];

foreach ($claims as $label => $pat) {
    $hits = [];
    if (preg_match_all($pat, $plain, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as $h) {
            $hits[] = $h[1];
        }
    }
    printf("\n%-34s %d hit(s)\n", $label, count($hits));
    foreach (array_slice($hits, 0, 4) as $p) {
        echo '  @'.$p.'  '.mb_substr(preg_replace('/\s+/', ' ', substr($plain, max(0, $p - 110), 250)), 0, 240)."\n";
    }
}
