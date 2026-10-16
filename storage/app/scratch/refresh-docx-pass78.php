<?php

/**
 * Pass 78 — the UI/UX lane, and the three rulings it needed from the owner.
 *
 * Recorded because two of these decisions now look like bugs to anyone who does not know they were
 * decisions: a refund and a void sharing one colour, and a badge that reaches its colour by name
 * rather than by fallback. Also recorded because the lane's own measurements overturned three of
 * its first answers, which is the part worth keeping.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

$esc = fn (string $s) => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $s);

$tail = 'each of them re-run under a mutant that makes the command write.';
$added = $tail
    .' The UI/UX pass that followed was measured before anything was changed, and the measuring '
    .'corrected three of its own first answers: the requirement catalog’s “All” badge, which looked '
    .'styled but was reaching its colour through Badge’s neutral fallback, now names the tone it '
    .'renders; 104 off-scale font sizes across 18 desk files became named steps on the type scale, '
    .'defined as plain strings so they set font-size and nothing else, proved inert by a before-and-'
    .'after size-and-line-height histogram on the live registrar queue (three different inherited '
    .'line-heights at 10px would have been flattened by a tuple); the navy chrome literal held in '
    .'three files became a theme step computing to the same rgb(11, 21, 40); and the app gained a '
    .'global disabled-state floor, because that affordance had been declared only inside .btn and '
    .'18 hand-styled desk controls gave no sign of being busy or unavailable. The four auth pages '
    .'were found to be on the kit already, so the report that they were not was withdrawn rather '
    .'than acted on. Three badge colours were then ruled by the owner, not chosen here: refunded '
    .'reads danger at both desks, knowingly sharing a colour with voided because the words on the '
    .'badges differ and the distinction ruling 14 draws lives in the record and not the palette; '
    .'full coverage reads success everywhere; and an incomplete item reads warning everywhere, '
    .'having been grey on the clearance checklist where it most needed to alarm. Six desk files '
    .'each carried their own copy of the identity tone list and two kept a local copy of the '
    .'student-type tones the kit already exports; all of them now read one shared lookup. The '
    .'comparison that found the divergences resolves every tone through the component table into '
    .'the two CSS colours it actually applies — comparing tone names would have missed all four and '
    .'reported none, which is what the first attempt at it did.';

if (substr_count($xml, $esc($tail)) !== 1) {
    fwrite(STDERR, "REFUSE: revision-row tail seen ".substr_count($xml, $esc($tail))." times\n");
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

$guards = $parseOk && $containersSame
    && substr_count($newXml, $esc($tail)) === 1
    && substr_count($newXml, $esc('Three badge colours were then ruled by the owner')) === 1;

if (! $guards) {
    fwrite(STDERR, sprintf(
        "REFUSE: guards (parses=%s containers=%s tail=%d)\n",
        $parseOk ? 'y' : 'n',
        $containersSame ? 'y' : 'n',
        substr_count($newXml, $esc($tail))
    ));
    exit(1);
}

printf("ok  @%d  appending %d characters to the 2026-10-10 revision row\n", $at, strlen($esc($added)) - strlen($esc($tail)));

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-10-pass78.docx');
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
