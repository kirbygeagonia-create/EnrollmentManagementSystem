<?php

/**
 * Pass 13 — the statements the build made false outside §28's G-family:
 * the two enum cells that now list values the columns no longer hold, the dictionary's
 * completeness claim, and the C-11 / D-1 / E-1 / E-4 / P-12 / X-4 register rows.
 *
 * Two kinds of edit, both resolved against the document before anything is written:
 *   text — a needle that lives inside one <w:t> run, replaced in place (keeps styling);
 *   cell — a whole table cell, found by (unique row anchor, unique cell prefix).
 * Any count that is not exactly one is a failure and nothing is written.
 */

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    fwrite(STDERR, "cannot open docx\n");
    exit(1);
}
$xml = $zip->getFromName('word/document.xml');
$zip->close();

// Kept for the write-time structural guard.
$original = $xml;

$textEdits = [
    ['enum(paid|partial|pending)',
        'enum(paid|partial|pending|refunded)'],
    ['enum(pending|validated|released|cancelled)',
        'enum(pending|validated)'],
    ['enum(active|revoked|expired)',
        'enum(active|revoked|expired) — all three produced: the Assessment desk grants, Accounting or Scholarship withdraws (ruling 15)'],
    ['Generated: no column is listed that the server does not have, and none that it has is omitted',
        'Generated: no column is listed that the server does not have. Read against information_schema on 2026-10-04, then checked back against the migrations that landed after the previous edition of this chapter: the refund pair on payments (refundedAt, refundedBy, refundedReason), the withdrawal trio on studentscholarships (statusChangedBy, statusChangedAt, statusReason), enrollments.dropReason, enrollments.clearanceConfirmedBy and clearanceConfirmedAt, the pinned enrollments.curriculumId, documentprintlog.studentId and blockId, and idrequests.mismatchRemark — plus one whole table this chapter has not yet listed, shiftingrequests. Those columns and that table are described where the behaviour is documented (§12-§22) and named in §28 by the class or migration that added them, but their rows are NOT yet re-listed in these tables, so the counts above are the 2026-10-04 reading minus those additions. Rebuilding the listings is the next edition’s job and is stated here rather than left for a reader to discover.'],
];

$cellEdits = [
    // ---- C-11: the dead status values were ruled, item by item.
    ['Should the status values no code produces be given behaviour or removed from the schema', 'Eleven values are accepted',
        'Ruled 2026-10-02 (#13, #15, #16) and built. Given behaviour: payments.partial (a receipt below the balance now records partial and holds the account up, and held() is the one way to ask whether money is still in the drawer), studentscholarships.revoked and .expired (a named withdrawal decision, a reason, and coverage recomputed from the grants still standing), clearanceperiods.extended (reachable only through the new extend action, never the status radio, and it accepts slips like an open window). Removed from the schema because no rule could reach them: idrequests.cancelled and idrequests.released (with the release action itself, ruling 3 of the September set), and the release permission. Kept after being challenged: clinicrecords.reopened — it is reachable, tested and use case 11.4.1, and zero live rows only means the demo never used it.'],
    ['Should the status values no code produces be given behaviour or removed from the schema', 'Project owner with the developer',
        'Closed by the owner’s rulings; built'],

    // ---- D-1: legacy office 8 folded into the Registrar.
    ['A seeded office named', 'offices id 8 and its clearancerequirements row',
        'Closed 2026-10-04 by ruling 12. The seeder now retires office 8 in foreign-key order — its staff, then the approval rows that point at its requirement, then the requirement, then the office — and the one account seated there is moved under the Registrar (office 1) and retitled. Live ems after the change: 9 offices, 9 clearance requirement lines all carrying their own name, no orphaned approvals, and each clearance still carries a full set of lines. ClearancePolicy no longer has an office 8 to honor; the Clearance counter’s acts are the Registrar’s, which is what §12 has always said. The account keeps its username — renaming a login is a separate decision from retiring an office.'],
    ['A seeded office named', 'Registrar (naming) and developer (data)',
        'Closed in data and code'],

    // ---- E-1: the zero-balance deadlock has a second edge into paid, and it is signed.
    ['An assessment settles to a zero balance', 'No payment can be recorded',
        'Closed. Accounting may now settle an assessed load that owes nothing (settleNoBalance, gated on payment.settle and a balance of zero or less) without inventing a receipt, and the fee sheet states on the paper that the account was settled with no receipt and names the hand and the hour off the Accounting workflow step. A recorded payment still cannot be ₱0, so the only path to paid with no money is the explicit settlement — which is the registrar’s and accounting’s ruling 9, and the reason the note is printed rather than inferred.'],
    ['An assessment settles to a zero balance', 'PaymentPolicy.php:46-49 requires',
        'AccountingController::settleNoBalance(), PaymentPolicy::settle(), Studentassessments::outstandingBalance() through held(); the settlement note is settlementWithoutReceipt on the fee sheet'],

    // ---- E-4: the same edge exists on the way back through the pipeline.
    ['That returned enrollment has to come back through the pipeline', 'finalize only fires from evaluated',
        'Closed with E-1: settlement is available at assessed, which is exactly where a returned or refunded load arrives once the department re-costs it, so a re-evaluated zero-balance account no longer needs a receipt it cannot have. A refund that reopens the account moves paid|enrolled back to assessed for the same reason.'],
    ['That returned enrollment has to come back through the pipeline', 'AssessmentController.php:274-276',
        'AccountingController::settleNoBalance(), AccountingController::refund() (paid|enrolled → assessed), EnrollmentStateMachine'],

    // ---- P-12: the printed fee and the charged fee are one number now.
    ['The slip footer states a ₱100.00 replacement fee from a config default', 'The slip footer states',
        'Closed 2026-10-03. Reference Data owns the figure: Feetypes::clearanceSlipReplacementFee() returns the fee type row or null, with no config default and no hard-coded 100 anywhere. The desk refuses to replace a slip while the fee row does not exist and names which row to create; the printed footer prints the fee type’s amount, and prints nothing until it does — so paper and payments cannot disagree, and a school that charges ₱150 says ₱150.'],
    ['The slip footer states a ₱100.00 replacement fee from a config default', 'Policy question',
        'Closed in code; the amount is maintained in Reference Data'],

    // ---- X-4: OfficeHead is a counter role, not a signature role.
    ['An OfficeHead of any office holds', 'An OfficeHead of any office holds',
        'Ruled 2026-10-02 (#7) and built 2026-10-03. The five signature acts — enrollment.approve, admission.approve, evaluation.sign, clinic.sign, id.sign — are gone from OfficeHead and sit with the desk that owns each box (RegistrarApprover, AdmissionOfficer, DeptEvaluator with Dean and ProgramHead, ClinicStaff, IdOfficer). Everything a head does at their own counter stayed: blocking, the clearance window and slip and receipt, recording a payment, computing and finalizing an assessment, capturing and proposing and crediting at Evaluation, admission intake and verification, the three print rights. Live proof that this removed cross-desk reach and nothing else: the eleven accounts holding OfficeHead each still hold the sign right of their own office, because the seater pairs an office head with their desk role. One deliberate exception, confirmed by the owner rather than assumed: clearance.approve stayed on OfficeHead, because it is a requirement-line signature scoped to the owning office and no desk role holds clearance.view — moving it would leave the act reachable by nobody.'],
    ['An OfficeHead of any office holds', 'Project owner with the Registrar',
        'Closed by ruling 7; the clearance.approve exception is the owner’s confirmed choice'],
];

/** @var array<int,array{0:int,1:string,2:int}> $resolveFailures */
$failures = 0;
$writes = [];

foreach ($textEdits as $i => [$from, $to]) {
    $n = substr_count($xml, $from);
    if ($n !== 1) {
        echo sprintf("FAIL text %d: %d occurrences [%s]\n", $i, $n, substr($from, 0, 60));
        $failures++;

        continue;
    }
    $pos = strpos($xml, $from);
    printf("ok   text %2d  @%d  %s → %s\n", $i, $pos, substr($from, 0, 46), substr($to, 0, 46));
    $writes[] = ['mode' => 'text', 'at' => $pos, 'len' => strlen($from), 'to' => $to];
}

foreach ($cellEdits as $i => [$rowNeedle, $cellPrefix, $to]) {
    $rowPos = strpos($xml, $rowNeedle);
    if ($rowPos === false || strpos($xml, $rowNeedle, $rowPos + 1) !== false) {
        echo sprintf("FAIL cell %d: row needle %s [%s]\n", $i, $rowPos === false ? 'not found' : 'not unique', substr($rowNeedle, 0, 50));
        $failures++;

        continue;
    }

    $start = strrpos(substr($xml, 0, $rowPos), '<w:tr');
    $end = strpos($xml, '</w:tr>', $rowPos) + 7;
    if ($start === false || strpos(substr($xml, $start, $rowPos - $start), '</w:tr>') !== false) {
        echo sprintf("FAIL cell %d: row anchor is not inside one row\n", $i);
        $failures++;

        continue;
    }

    $row = substr($xml, $start, $end - $start);
    preg_match_all('/<w:tc>.*?<\/w:tc>/s', $row, $cm, PREG_OFFSET_CAPTURE);

    $hits = [];
    foreach ($cm[0] as $j => $cell) {
        preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell[0], $tm);
        $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
        if (strncmp(ltrim($flat), $cellPrefix, min(strlen($cellPrefix), 60)) === 0) {
            $hits[] = $j;
        }
    }

    if (count($hits) !== 1) {
        echo sprintf("FAIL cell %d: prefix matched %d cells [%s]\n", $i, count($hits), substr($cellPrefix, 0, 45));
        $failures++;

        continue;
    }

    $cell = $cm[0][$hits[0]][0];
    if (substr_count($cell, '<w:p>') !== 1) {
        echo sprintf("FAIL cell %d: target cell holds %d paragraphs\n", $i, substr_count($cell, '<w:p>'));
        $failures++;

        continue;
    }

    preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
    printf(
        "ok   cell %2d  cell %d of row @%d  was: %s\n                             now: %s\n",
        $i,
        $hits[0],
        $start,
        substr(preg_replace('/\s+/', ' ', html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1)), 0, 72),
        substr($to, 0, 72)
    );

    $writes[] = [
        'mode' => 'cell',
        'start' => $start,
        'end' => $end,
        // Absolute offset in the document: the cell search ran on the row substring, and
        // writing with that row-relative number is how a previous run spliced text into the
        // middle of unrelated tags.
        'at' => $cm[0][$hits[0]][1] + $start,
        'len' => strlen($cell),
        'to' => $to,
    ];
}

if ($failures > 0) {
    echo "\n{$failures} target(s) unresolved — nothing written\n";
    exit(1);
}

echo "\nall ".count($writes)." targets resolved uniquely\n";

if (! $apply) {
    echo "DRY RUN — pass --apply to write\n";
    exit(0);
}

copy($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-04-pass13.docx');

// Every edit — text-in-a-run or whole-cell — becomes one absolute replacement. Cells are
// rebuilt from the untouched row so they keep their own run properties, then all
// replacements are applied from the end backwards, so no earlier offset moves.
// (Applying the two kinds in separate phases was the bug this guards against: the cell
// rewrites change lengths, and a text offset computed before them points elsewhere.)
$replacements = [];

foreach ($writes as $w) {
    if ($w['mode'] === 'text') {
        $replacements[] = ['at' => $w['at'], 'len' => $w['len'], 'to' => str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $w['to'])];

        continue;
    }

    $cell = substr($xml, $w['at'], $w['len']);
    preg_match('/<w:r\b.*?<\/w:r>/s', $cell, $rm);
    preg_match('/<w:rPr>.*?<\/w:rPr>/s', $rm[0] ?? '', $pr);
    $newCell = preg_replace('/<w:r\b.*?<\/w:r>/s', '', $cell);
    $newCell = str_replace('</w:p>', '<w:r>'.($pr[0] ?? '').'<w:t xml:space="preserve">'
        .str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $w['to']).'</w:t></w:r></w:p>', $newCell);

    $replacements[] = ['at' => $w['at'], 'len' => $w['len'], 'to' => $newCell];
}

usort($replacements, fn ($a, $b) => $b['at'] <=> $a['at']);

foreach ($replacements as $r) {
    $xml = substr_replace($xml, $r['to'], $r['at'], $r['len']);
}

// Refuse to write a document that is not the document: the containers must be untouched,
// the run and text tags must balance, it must still parse, every stale needle must be gone
// and every replacement must be present. (A previous run spliced text into the middle of
// unrelated tags with a row-relative offset; that is what this guard is for.)
$counts = function (string $doc, array $tags) {
    $out = [];
    foreach ($tags as $t) {
        $out[$t] = preg_match_all('/'.preg_quote($t, '/').'/',$doc);
    }

    return $out;
};

$containers = ['<w:body>', '</w:body>', '<w:tbl>', '</w:tbl>', '<w:tr ', '<w:tr>', '</w:tr>', '<w:tc>', '</w:tc>', '<w:p>', '</w:p>'];
$before = $counts($original, $containers);
$after = $counts($xml, $containers);
foreach ($before as $tag => $n) {
    if ($after[$tag] !== $n) {
        fwrite(STDERR, "REFUSED WRITE: {$tag} moved from {$n} to {$after[$tag]}\n");
        exit(1);
    }
}
echo 'guard  containers untouched ('.array_sum($before)." elements)\n";

$extras = ['<w:r ' => '<w:r>', '<w:t ' => '<w:t>', '<w:p ' => '<w:p>'];
foreach ([['<w:r ', '</w:r>'], ['<w:t ', '</w:t>'], ['<w:p ', '</w:p>']] as [$open, $close]) {
    $o = preg_match_all('/'.preg_quote($open, '/').'/', $xml)
        + preg_match_all('/'.preg_quote($extras[$open], '/').'/', $xml);
    $c = preg_match_all('/'.preg_quote($close, '/').'/', $xml);
    if ($o !== $c) {
        fwrite(STDERR, "REFUSED WRITE: {$open}/{$close} do not balance ({$o} vs {$c})\n");
        exit(1);
    }
}
echo "guard  run and text tags balance\n";

if (simplexml_load_string($xml) === false) {
    fwrite(STDERR, "REFUSED WRITE: document.xml no longer parses\n");
    exit(1);
}
echo "guard  document.xml parses\n";

foreach ($textEdits as [$from, $to]) {
    if (substr_count($xml, $to) !== 1) {
        fwrite(STDERR, "REFUSED WRITE: replacement missing for [".substr($to, 0, 40)."]\n");
        exit(1);
    }
}
foreach ($cellEdits as [$rowNeedle, $prefix, $to]) {
    $needle = mb_substr($to, 0, 40);
    if (! str_contains($xml, $needle)) {
        fwrite(STDERR, "REFUSED WRITE: cell rewrite missing [{$needle}]\n");
        exit(1);
    }
}
echo "guard  all ".count($replacements)." replacements present\n";

$tmp = sys_get_temp_dir().'/ems-docx13-'.uniqid().'.docx';
copy($src, $tmp);
$z2 = new ZipArchive();
$z2->open($tmp);
$z2->addFromString('word/document.xml', $xml);
$z2->close();
rename($tmp, $src);

echo "WROTE {$src}\n";
