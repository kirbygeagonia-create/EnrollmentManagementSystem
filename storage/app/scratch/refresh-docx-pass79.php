<?php

/**
 * Pass 79 — the error surface: four defects, two new renderers, one claim withdrawn.
 *
 * Recorded at this length deliberately. Three of these facts are the kind a later reader would
 * otherwise "fix" back: the 500 page that is still correct for a page being read, the warning that
 * now shares a redirect with a success, and the rounding that does NOT fix a reproduced bug.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$tail = 'which is what the first attempt at it did.';
$added = $tail
    .' The error surface was then audited, and four real defects came out of it: a warning sent on '
    .'the same redirect as a success was never shown at all, which had been silently swallowing the '
    .'schedule-conflict notice on the Blocking desk; every message dismissed itself after 4.5 seconds '
    .'whatever its severity, so a forty-word instruction on what to do next vanished before it could '
    .'be read; an error was announced with role=status, which a screen reader can leave unread while '
    .'the desk moves on; and the React boundary printed the raw JavaScript exception above the '
    .'guidance, where a person reading it sees lost work rather than a display fault. Two exception '
    .'renderers were added. A submission that meets an expired session now returns the officer to the '
    .'record they were on with a warning naming both facts — it did not save, and nothing already '
    .'saved was lost — and a database constraint the application did not predict returns that same '
    .'calm refusal instead of a 500 page. A page merely being read keeps the normal screen in both '
    .'cases, because there was no step to repeat. Nine tests pin the two renderers, each proved by '
    .'disabling the handler it guards and watching the suite fail. Note that the session renderer '
    .'cannot be written against TokenMismatchException at all: prepareException() turns it into an '
    .'HttpException with status 419 before any render callback is consulted, so the callback keys on '
    .'the converted exception. The audit also closed three classes by finding them already guarded in '
    .'code — the one delete a RESTRICT key can break, and all three duplicate-prone unique indexes. '
    .'One claim from this pass was withdrawn rather than shipped as a fix: the float drift in '
    .'scholarship coverage is real arithmetic but not reachable as a user-visible fault, because the '
    .'DECIMAL(x,2) columns absorb it and min() clamps the second grant to the balance already stored. '
    .'The rounding added there is hardening, and its test pins the reconciliation invariant — '
    .'coverage plus remaining balance equals the amount assessed — rather than a reproduced bug.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: tail needle seen ".substr_count($xml, $esc($tail))." times\n");
    exit(1);
}

$at = strpos($xml, $esc($tail));
$newXml = substr_replace($xml, $esc($added), $at, strlen($esc($tail)));

$parseOk = (function () use ($newXml) {
    libxml_use_internal_errors(true);
    libxml_clear_errors();
    $r = @simplexml_load_string($newXml);
    libxml_clear_errors();

    return $r !== false;
})();

$containersSame = substr_count($xml, '<w:tbl>') === substr_count($newXml, '<w:tbl>')
    && substr_count($xml, '</w:p>') === substr_count($newXml, '</w:p>');

if (! $parseOk || ! $containersSame || substr_count($newXml, $esc($tail)) !== 1
    || substr_count($newXml, $esc('One claim from this pass was withdrawn')) !== 1) {
    fwrite(STDERR, "REFUSE: guards (parses=".($parseOk ? 'y' : 'n').', containers='.($containersSame ? 'y' : 'n').")\n");
    exit(1);
}

printf("ok  @%d  appending %d characters\n", $at, strlen($esc($added)) - strlen($esc($tail)));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass79.docx');
$z2 = new ZipArchive();
$z2->open($src);
$z2->addFromString('word/document.xml', $newXml);
$z2->close();

$z3 = new ZipArchive();
$z3->open($src);
$back = $z3->getFromName('word/document.xml');
$z3->close();

if ($back !== $newXml) {
    fwrite(STDERR, "REFUSE: disk does not hold the edited document.xml\n");
    exit(1);
}

echo "Wrote {$src}\n";
