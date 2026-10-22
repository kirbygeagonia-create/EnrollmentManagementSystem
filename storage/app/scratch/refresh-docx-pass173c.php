<?php

/**
 * Pass 173c — pass 173 added a register row, so the register counts it had just written were wrong on disk.
 *
 * §28.8 and G-29 both state the actor column as measured: 8 rows name an actor, and the population they are 8 of
 * was 56 when the sentence was drafted. G-29 is itself row 57. lane61-who-owes.php answers today: 57 rows parsed,
 * 8 still name an actor, 46 of the remainder state an answer in that cell, three (G-7, G-17, G-26) cannot be read
 * by column because their own text carries a pipe inside a code span.
 *
 * This is the same rule pass 142 learned for §28.3's size, met from the other side: a row that grows moves every
 * place that counts the register, including the sentence that explains why the row was added.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$flat = (string) file_get_contents($root.'/storage/app/scratch/docx-flat.txt');
foreach ([
    'of the 56 register rows 8 still name an actor while the other 48 name none — 45 of those state the answer in that cell' => 1,
    'names an actor in 8 of the 56 register rows and names none in the other 48' => 1,
] as $needle => $want) {
    if (substr_count($flat, $needle) !== $want) {
        printf("REFUSED: the dump holds %d copies of [%s…], expected %d.\n",
            substr_count($flat, $needle), substr($needle, 0, 40), $want);
        exit(1);
    }
}

docxApply($src, $root.'/storage/app/scratch/pass173c-backup.docx', [
    [
        'of the 56 register rows 8 still name an actor while the other 48 name none — 45 of those state the answer in that cell',
        'of the 57 register rows 8 still name an actor while the other 49 name none — 46 of those state the answer in that cell',
    ],
    [
        'names an actor in 8 of the 56 register rows and names none in the other 48',
        'names an actor in 8 of the 57 register rows and names none in the other 49',
    ],
], [], $apply);
