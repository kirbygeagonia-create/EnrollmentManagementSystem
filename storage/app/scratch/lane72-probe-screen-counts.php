<?php

/**
 * Read-only probe: how many sentences in the authority docx state how many records a SCREEN shows?
 *
 * lane72-row-count-claims.php weighs a number beside a table name against COUNT(*). It cannot weigh the sentence
 * pass 244 found drifted — the ID desk's Issued tab "holds seven", where the figure's denominator is a controller
 * predicate, not a table — because nothing in that sentence names a table. Before writing a second checker for a
 * class this size is unknown, count the class.
 *
 * The shape measured here: a number (digit or word) with a screen word in the same sentence — tab, queue, screen,
 * page, list, tile, desk, dropdown, column of choices. Each row is printed with its sentence so a reader can say
 * whether it is a claim about a query at all.
 *
 *   php storage/app/scratch/lane72-probe-screen-counts.php
 */
$root = dirname(__DIR__, 3);
$dump = $root.'/storage/app/scratch/docx-flat.txt';
if (! is_file($dump)) {
    exit("REFUSED: no dump — run dump-docx-flat.php first.\n");
}
$words = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve',
    'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen', 'twenty', 'thirty', 'forty',
    'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
$re = '/\b(\d{1,4}|'.implode('|', $words).")\b/u";
$screenRe = '/\b(tabs?|queues?|screens?|pages?|lists?|tiles?|desks?|dropdowns?|pickers?|cards?|filters?|rows?)\b/u';

$n = 0;
foreach ((array) file($dump) as $i => $raw) {
    $line = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
    foreach (preg_split('/(?<=[.;]) (?=[A-Z(“"§])| \| /u', $line) ?: [] as $sent) {
        if (preg_match($screenRe, $sent) !== 1 || preg_match_all($re, $sent, $mm) < 1) {
            continue;
        }
        // A sentence naming a dated record is that day's reading, and this probe is about living claims.
        if (preg_match('/\b(20\d\d-\d\d-\d\d|as of \w+ \d{1,2}, 20\d\d|Closed \d|on 20\d\d)\b/u', $sent) === 1) {
            continue;
        }
        $n++;
        printf("%-5d  %s\n", $i + 1, trim(mb_substr($sent, 0, 240)));
    }
}
printf("\n%d sentence(s) carry a number beside a screen word; read them — the probe does not judge.\n", $n);
