<?php
/**
 * Lane 61 target probe: for each candidate needle, say whether it lives inside ONE <w:t> run
 * (a text edit can reach it) or spans a run boundary (it needs a paragraph or cell edit), and
 * how many times it occurs. This is the verify-before-write step AGENTS.md requires; a needle
 * chosen from the flat dump is a guess about the XML until this answers.
 *
 * The §25.5 set is kept as a regression check: those needles were written by pass 171/171b and
 * must still resolve exactly once, so a later pass that moves the paragraph is caught here.
 *
 * Usage: php storage/app/scratch/lane61-probe-targets.php
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$phrases = [
    // written by pass 171/171b — must still be single and single-run
    's255SixBlades' => 'The six Blade print templates build their header',
    's255Provider' => 'SettingsServiceProvider.php:20-24 copies every row of the settings table',
    's255ReachesPaper' => '— does reach the paper, which is the one thing the previous edition said it could not do',
    'sealFixed' => 'The seal is no longer asked for by path',
    // §30.1
    's30Title' => 'Fifty-four, and the sixty-two tables in the database',
    's30DomainLabel' => 'Everything the school’s process is recorded in.',
    's30DomainCount' => '51 were created by the August 2026 baseline migration',
    's30Defensible' => 'Applied to the live database the number is still defensible',
    's30FiftyFourNotTarget' => 'fifty-four was not a target',
    's30Row2714' => 'The text said 54 and listed 53',
    // §30.2
    's30EightKeys' => 'The domain tables hold 8 unique keys, not six',
    's30NineKeys' => 'are nine unique keys in the domain tables',
    // §30.4
    's30GrainIntro' => 'while two are stale-by-construction because nothing maintains them',
    's30YearLevel' => 'Every enrollment this system creates carries 1 and nothing promotes it',
    's30YearLevelCost' => 'This is the clearest case in the schema of a column that is not a fact',
    's30HandMaintained' => 'one controller maintains them by hand',
    // §30.5
    's30EmptySettings' => 'Its emptiness is the reason letterheads and fees print a code default',
    's30EmptySettingsLabel' => 'The empty settings table',
    's30CountedInside' => 'They are counted inside the 54',
    // §30.6
    's30FourOpen' => 'Four open items determine whether this section describes a design or an accident',
    's30UntilAnswered' => 'Until those are answered, 30.2 and 30.4 are written as observations',
    // §28.7 / §28.8
    's287Row2' => 'The rows that wait on an answer rather than on code are G-4',
    's287Row8' => 'each waits on an answer in 28.5 before enforcement can be written',
    's287Intro' => 'Ordered by consequence, not by effort',
    's288Intro' => 'Counted from the “Who must act” column of 28.2 and 28.3',
];

foreach ($phrases as $key => $p) {
    $n = substr_count($xml, $p);
    if ($n === 0) {
        printf("%-24s 0 hits  — not in the XML as typed [%s]\n", $key, mb_substr($p, 0, 50));
        continue;
    }
    $p0 = strpos($xml, $p);
    $tOpen = strrpos(substr($xml, 0, $p0), '<w:t');
    $inner = $tOpen === false ? '' : substr($xml, $tOpen, $p0 - $tOpen);
    $sameRun = $tOpen !== false && strpos($inner, '</w:t>') === false;
    printf("%-24s %d hit(s)  @%-8d  inside one run: %s\n", $key, $n, $p0, $sameRun ? 'yes' : 'NO — spans runs');
}
