<?php

/**
 * Pass 115 — #89 was ruled and carried out, so two clauses of the boundary paragraph now describe a
 * database that no longer exists.
 *
 * The paragraph that recorded ruling #89 closed with "keep doing so until the owner rules on amending
 * stored records". The owner ruled the same day, the amendment ran against live `ems` behind a verified
 * dump, and the paragraph would otherwise be the authority the project defers to while describing an
 * older build — the exact failure the docx rule is written against. Two edits, both inside one run
 * (measured: the whole passage is a single <w:t>, so substring offsets do not cross a run boundary):
 *
 *   1  the 29 credits. Repointed to each row's own college desk — dept.ctic 16, dept.cjc 10, dept.cbg 3 —
 *      with the two things the ruling refused to touch recorded beside it: the signatures (a signature is a
 *      claim about who physically signed, and office4_head closed 20 of those boxes without holding
 *      evaluation.sign since ruling 7), and the new finding that one Department Evaluation box was closed
 *      by office2_head, reported rather than repaired.
 *   2  the reason a null unitId is a decision. It used to be the 29 cross-college enrollments. That
 *      argument is gone, and saying so is the honest edit: office4_head now holds no credit at all and 3
 *      open boxes in one college. It stays unbounded for a different reason, and one caution is recorded —
 *      shiftingrequests is empty on the development database, so the shift docket is not evidence yet.
 *
 * DRY RUN unless --apply.
 */

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open source\n");
    exit(1);
}
$original = $xml = $zip->getFromName('word/document.xml');
$zip->close();

$fails = 0;
$edits = [];

$refuse = function (string $why) use (&$fails): void {
    fwrite(STDERR, "REFUSE: {$why}\n");
    $fails++;
};

$swap = function (string $from, string $to, string $label) use ($xml, &$edits, &$refuse): void {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        $refuse("{$label}: anchor appears {$n} time(s), expected 1");

        return;
    }
    $edits[] = [strpos($xml, $from), strlen($from), $to, $label];
};

$swap('What this does not do is rewrite history: the 29 enrollments that already credit office4_head, '
    .'a Guidance account with no evaluation.sign, keep doing so until the owner rules on amending stored '
    .'records, and rehearsing the chain on a copy showed exactly that split — two rows written by the '
    .'changed pipeline landed on dept.ctic, the CTIC desk its BSIT students belong to, and the older rows '
    .'stayed where they were.',
    'What this did not do, on its own, was rewrite history — that part went to the owner and was ruled the '
    .'same day. The 29 enrollments already crediting office4_head had their evaluatedBy repointed to the '
    .'desk each row’s own course names (dept.ctic 16, dept.cjc 10, dept.cbg 3), behind a dump of the live '
    .'database that was opened and checked for the tables before anything was written, and the account the '
    .'boundary had been measured against is now credited with nothing. Two things the ruling refused to '
    .'touch are recorded here rather than quietly kept. The first is the signatures: workflowsteps.signedBy '
    .'still names whoever closed each box, 20 of them office4_head — an account that has not held '
    .'evaluation.sign since ruling 7 split the signing rights out of OfficeHead — because a signature is a '
    .'claim about who physically signed, and rewriting one to make the data tidy destroys the only evidence '
    .'of it. The second is what measuring for the amendment turned up: one enrollment’s Department '
    .'Evaluation box was closed by office2_head, Accounting’s head signing another desk’s paper. It is '
    .'reported, not repaired, for the same reason the signatures are left alone. Rehearsing the chain on a '
    .'copy had shown the split first — two rows written by the changed pipeline landed on dept.ctic, the '
    .'CTIC desk its BSIT students belong to, and the older rows stayed where they were until this '
    .'amendment reached them.',
    'the 29 credits, amended');

$swap('An account with no college is bounded by nothing, and that is a decision with a measured reason '
    .'rather than a gap: office4_head holds the 29 enrollments above, 15 of them in progress across three '
    .'colleges, so bounding it would strand papers no desk could then close, and office7_head with the '
    .'seeded instructors stay unbounded because §7 of the walkthrough walks two colleges and a third’s '
    .'applicant in one login.',
    'An account with no college is bounded by nothing, and that stays a decision rather than a gap — though '
    .'the measurement behind it moved when the credits were repointed, and saying so is part of keeping it '
    .'true. office4_head used to be the account whose 29 cross-college enrollments made bounding it strand '
    .'papers no desk could close; after the amendment it holds no enrollment credit at all and 3 open '
    .'Department Evaluation boxes, all in one college. It stays unbounded because an office head’s work '
    .'belongs to the office rather than to a single college, and office7_head with the seeded instructors '
    .'stay unbounded because §7 of the walkthrough walks two colleges and a third’s applicant in one login. '
    .'One caution for whoever re-measures this: shiftingrequests has no rows on the development database, '
    .'so the cross-college shift docket is not evidence in either direction yet.',
    'the reason a null unitId is a decision');

printf("\nedits planned: %d\n", count($edits));
foreach ($edits as [$off, $len, $to, $label]) {
    printf("  %-34s byte %8d  (%d → %d bytes)\n", $label, $off, $len, strlen($to));
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
$previousStart = PHP_INT_MAX;
foreach ($edits as $e) {
    if ($e[0] + $e[1] > $previousStart) {
        $refuse(sprintf('edits overlap at %d and %d', $e[0], $previousStart));
    }
    $previousStart = $e[0];
}
if ($fails > 0) {
    exit(1);
}

foreach ($edits as [$off, $len, $to, $label]) {
    $xml = substr_replace($xml, $to, $off, $len);
    printf("applied  %s\n", $label);
}

$guards = [
    'no paragraph gained or lost' => substr_count($original, '</w:p>') === substr_count($xml, '</w:p>'),
    'no run gained or lost' => substr_count($original, '<w:r>') === substr_count($xml, '<w:r>'),
    'no table row gained or lost' => substr_count($original, '<w:tr>') === substr_count($xml, '<w:tr>'),
    'no text element gained or lost' => substr_count($original, '<w:t ') === substr_count($xml, '<w:t '),
    'root start tag untouched' => substr($original, 0, strpos($original, '<w:body>')) === substr($xml, 0, strpos($xml, '<w:body>')),
    'no serializer prefixes' => substr_count($xml, 'ns1:') === 0 && substr_count($xml, 'ns5:') === 0,
    'no markdown backticks crept in' => substr_count($xml, '`') === substr_count($original, '`'),
    'the two stale clauses are gone' => substr_count($xml, 'keep doing so until the owner rules on amending stored records') === 0
        && substr_count($xml, 'office4_head holds the 29 enrollments above') === 0,
    'the amendment is stated once, with the distribution' => substr_count($xml, 'dept.ctic 16, dept.cjc 10, dept.cbg 3') === 1
        && substr_count($xml, 'is now credited with nothing') === 1,
    'what was refused is stated once' => substr_count($xml, 'closed by office2_head') === 1
        && substr_count($xml, '20 of them office4_head') === 1,
    'the moved reason is stated once' => substr_count($xml, '3 open Department Evaluation boxes') === 1
        && substr_count($xml, 'shiftingrequests has no rows') === 1,
    'the neighbours this pass did not touch still read as before' => substr_count($xml, 'Seating the desks is what surfaced a second finding') === 1
        && substr_count($xml, 'CollegeDeskScopeTest pins both halves') === 1
        && substr_count($xml, 'Ruled the same day, the boundary those desks work inside') === 1
        && substr_count($xml, 'A desk that asks for another college’s examination roster is refused') === 1,
    'the document still parses' => simplexml_load_string($xml) !== false,
];

foreach ($guards as $name => $ok) {
    if (! $ok) {
        fwrite(STDERR, "REFUSE guard: {$name}\n");
        $fails++;
    } else {
        printf("guard ok %s\n", $name);
    }
}

if ($fails > 0) {
    printf("\n%d problem(s) — nothing was written\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/docx-backup-2026-10-13-pass115.docx');

$out = new ZipArchive;
$out->open($src);
$out->addFromString('word/document.xml', $xml);
$out->close();

printf("WROTE %s  (%+d bytes)\n", $src, strlen($xml) - strlen($original));
