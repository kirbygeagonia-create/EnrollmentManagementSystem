<?php

/**
 * Pass 157 — one clause pass 156 wrote is not true, and a register row that overstates its own fix is the kind
 * of claim this document has been corrected for before.
 *
 * Pass 156 wrote that "all three create sites call" the hoisted pricing helper. Measured against the tree: two
 * of the three (the unpaid and part-paid sections) still insert their four `charges` rows by hand, and only the
 * section that had forgotten them calls it. The code is left as it is — unifying it would move every line the
 * register quotes today, for a tidiness the new audit rule already guards — so the sentence is made to match.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$from = 'and here); it is the hoisted `charge_demo_fees()` and all three create sites call it.';
$to = 'and here); the helper is the hoisted `charge_demo_fees()` now, and the section that had no lines calls it, '
    .'while the two above it still write their four rows by hand — which is why the register counts a bare sheet '
    .'wherever it appears rather than trusting the call sites to agree.';

$zip = new ZipArchive();

if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");

    exit(1);
}

$xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);

    if (! $ok) {
        $fails++;
    }
};

$count = substr_count($xml, $from);
$guard($count === 1, "the overstated clause occurs exactly once (found $count)");
$guard(substr_count($xml, 'and all three create sites call it') === 1, 'no second copy of the claim survives elsewhere');

$new = $count === 1 ? str_replace($from, $to, $xml) : $xml;

$guard(substr_count($new, $to) === 1, 'the corrected clause is in place');
$guard(substr_count($new, 'all three create sites') === 0, 'the false count is gone');

foreach (['&amp;amp;', '&amp;gt;', '&amp;lt;'] as $bad) {
    $guard(substr_count($new, $bad) === 0, "no double-escaped entity introduced: $bad");
}

$guard(strlen($new) !== strlen($xml), 'the rewrite changed the byte length, so it really replaced something');

libxml_use_internal_errors(true);
$doc = new DOMDocument;
$guard($doc->loadXML($new) === true, 'document.xml still parses');
libxml_clear_errors();

printf("\n%d → %d bytes\n", strlen($xml), strlen($new));

if ($fails > 0) {
    fwrite(STDERR, "{$fails} guard(s) failed — nothing written\n");

    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";

    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-18-pass157.docx');
$z0 = new ZipArchive;
$z0->open($src);
$partsBefore = count($z0);
$z0->close();

$z1 = new ZipArchive();

if ($z1->open($src, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "cannot open the docx for updating — restore the backup\n");

    exit(1);
}

$z1->addFromString('word/document.xml', $new);
$z1->close();

$check2 = new ZipArchive();
$check2->open($src);
$onDisk = $check2->getFromName('word/document.xml');
$partsAfter = count($check2);
$check2->close();

if ($onDisk !== $new) {
    fwrite(STDERR, "the file on disk does not hold the edited document.xml — restore the backup\n");

    exit(1);
}

if ($partsAfter !== $partsBefore) {
    fwrite(STDERR, "the archive went from {$partsBefore} parts to {$partsAfter} — restore the backup\n");

    exit(1);
}

printf("WROTE %s — byte-verified, all %d parts present\n", $src, $partsAfter);
