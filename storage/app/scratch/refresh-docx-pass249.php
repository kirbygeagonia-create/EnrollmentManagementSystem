<?php

/**
 * Docx pass 249 — G-1: the Registrar's record page now reads the grade trail.
 *
 * Two jobs, in one batch, because the code and the authority must not be allowed to describe
 * different builds:
 *
 *  1. THE CASCADE THIS BATCH MOVED. app/Http/Controllers/Registrar/RegistrarController.php grew by
 *     one `use` line and fourteen lines inside show(), so every citation into it below line 113 is
 *     fifteen rows short of its construct; resources/js/Pages/Registrar/Show.jsx grew by 162/163
 *     lines, so its two citations are wrong by that much. Every new number was measured against
 *     HEAD's exported bytes by lane75-probe-moved-cites.php, not by arithmetic — a pointer one row
 *     short still lands on a live line and every bounds check agrees with it.
 *  2. THE NEW FACT, in §16.5 where the desk's own verification is recorded, plus §5.2's row for this
 *     page, which still quoted the year-level spelling the build stopped using when the owner ruled
 *     the ordinal form on 2026-10-24.
 *
 * Stage 1 refuses the whole run if any needle's count disagrees with the measurement, and the pass
 * is one-shot: it declines to run again once its own sentences are present.
 *
 * Usage: php storage/app/scratch/refresh-docx-pass249.php          (dry run)
 *        php storage/app/scratch/refresh-docx-pass249.php --apply
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/pass249-backup-'.date('Ymd-His').'.docx';
$apply = in_array('--apply', $argv, true);

foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--apply') {
        fwrite(STDERR, "REFUSED: unexpected argument {$arg}; this pass reads only --apply.\n");
        exit(1);
    }
}

$zip = new ZipArchive;
if (! $zip->open($docx)) {
    fwrite(STDERR, "REFUSED: cannot open {$docx}.\n");
    exit(1);
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

// ---------------------------------------------------------------- measure the shift, do not assume it

$headRc = array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/storage/app/scratch/lane75-head-RegistrarController.php'))));
$liveRc = array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/app/Http/Controllers/Registrar/RegistrarController.php'))));
$headShow = array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/storage/app/scratch/lane75-head-Show.jsx'))));
$liveShow = array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/resources/js/Pages/Registrar/Show.jsx'))));

/**
 * Where does the text HEAD held at :from-to now sit? Matched as a window of the same height,
 * trimmed, blanks kept in place. Returns every start line that matches, so more than one is an
 * ambiguity the caller must refuse on rather than resolve by preference.
 *
 * @return int[]
 */
$where = static function (array $head, array $live, int $from, int $to): array {
    $height = $to - $from + 1;
    $window = array_slice($head, $from - 1, $height);
    $hits = [];
    for ($i = 0; $i + $height <= count($live); $i++) {
        if (array_slice($live, $i, $height) === $window) {
            $hits[] = $i + 1;
        }
    }

    return $hits;
};

preg_match_all('/RegistrarController\.php:(\d+)(?:-(\d+))?/', $xml, $tokens, PREG_SET_ORDER);

$distinct = [];
foreach ($tokens as $t) {
    $distinct[$t[0]] = ($distinct[$t[0]] ?? 0) + 1;
}

// Multi-line ranges are unique enough to measure; single-line citations are ordinary code and match
// everywhere, so they inherit the delta the ranges agree on — and the pass refuses if the ranges
// disagree, because then there is no single answer to give.
$deltas = [];
foreach ($distinct as $token => $copies) {
    preg_match('/^RegistrarController\.php:(\d+)(?:-(\d+))?$/', $token, $m);
    $from = (int) $m[1];
    $to = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;
    if ($token === 'RegistrarController.php:110-129' || $to === null || $to === $from) {
        continue;
    }
    $hits = $where($headRc, $liveRc, $from, $to);
    if (count($hits) !== 1) {
        fwrite(STDERR, "REFUSED: {$token} now matches at ".count($hits)." places (".implode(', ', $hits).") — the shift cannot be measured from it.\n");
        exit(1);
    }
    $deltas[$token] = $hits[0] - $from;
}
if ($deltas === []) {
    fwrite(STDERR, "REFUSED: no multi-line citation into RegistrarController.php could be measured, so there is no delta to apply.\n");
    exit(1);
}
$uniqueDeltas = array_values(array_unique($deltas));
if (count($uniqueDeltas) !== 1) {
    fwrite(STDERR, "REFUSED: the measured ranges do not agree on one delta: \n");
    foreach ($deltas as $token => $d) {
        fwrite(STDERR, "  {$token} => {$d}\n");
    }
    exit(1);
}
// array_unique() keeps the original keys, and these keys are token STRINGS — indexing [0] would read
// an undefined value and silently shift by nothing, which is a no-op dressed as a repair.
$delta = $uniqueDeltas[0];
if (! is_int($delta) || $delta < 1) {
    fwrite(STDERR, "REFUSED: the measured delta is not a positive whole number (".var_export($delta, true).").\n");
    exit(1);
}
echo "delta measured into RegistrarController.php: ".count($deltas)." range(s) agree on +{$delta}\n";

$edits = [];
$shifted = 0;
foreach ($distinct as $token => $copies) {
    preg_match('/^RegistrarController\.php:(\d+)(?:-(\d+))?$/', $token, $m);
    $from = (int) $m[1];
    $to = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : null;

    if ($token === 'RegistrarController.php:110-129') {
        // Measured by content: that range was the Inertia::render block of show(), which this batch
        // lengthened, and the old citation ran seven lines past the block into the next docblock.
        // The pointer is re-taken to the construct's own bounds, found by searching for it.
        $start = null;
        foreach ($liveRc as $i => $line) {
            if (str_contains($line, "return Inertia::render('Registrar/Show'")) {
                $start = $i + 1;
                break;
            }
        }
        $stop = null;
        for ($i = ($start ?? 1); $i < count($liveRc); $i++) {
            if ($liveRc[$i] === ']);') {
                $stop = $i + 1;
                break;
            }
        }
        if ($start === null || $stop === null) {
            fwrite(STDERR, "REFUSED: the render block could not be bounded in the working controller.\n");
            exit(1);
        }
        $renderStart = $start;
        $renderStop = $stop;
        $edits[] = [$token, 'RegistrarController.php:'.$start.'-'.$stop, null, $copies];
        echo sprintf('special     :%s -> :%d-%d  (%d cop%s)\n', $token, $start, $stop, $copies, $copies === 1 ? 'y' : 'ies');

        continue;
    }

    if ($from < 113) {
        echo sprintf("untouched :%s  (%d cop%s) — above the insertion\n", $token, $copies, $copies === 1 ? 'y' : 'ies');

        continue;
    }

    $newToken = 'RegistrarController.php:'.($from + $delta).($to === null ? '' : '-'.($to + $delta));
    $edits[] = [$token, $newToken, null, $copies];
    $shifted++;
}

// The engine walks the list in order, so a token that is the PREFIX of another would be replaced
// inside its longer sibling (lane 69's defect (a)): the document really does hold both
// `RegistrarController.php:360-431` and a bare `RegistrarController.php:360`. Each short needle is
// therefore extended with the character that actually follows it in the XML — and the extension is
// only accepted when EVERY occurrence of that token is followed by the same character, so the
// needle still names exactly the citations it means. A following byte of `<` would mean the run
// ends there, and markup may not be written into a text replacement, so that case refuses.
for ($round = 0; $round < 6; $round++) {
    $colliding = false;
    foreach ($edits as $i => $edit) {
        foreach ($edits as $j => $other) {
            if ($i === $j || ! str_starts_with($other[0], $edit[0]) || $other[0] === $edit[0]) {
                continue;
            }
            $token = $edit[0];
            $tails = [];
            $scan = 0;
            while (($p = strpos($xml, $token, $scan)) !== false) {
                $tail = $xml[$p + strlen($token)] ?? '';
                $after = substr($xml, $p + strlen($token), 8);
                // An occurrence followed by `-<digit>` IS the longer range citation, not this token;
                // counting it as a bare occurrence would invent a disagreement about context.
                if (preg_match('/^-\d/', $after)) {
                    $scan = $p + 1;

                    continue;
                }
                $tails[] = $tail;
                $scan = $p + 1;
            }
            $tails = array_unique($tails);
            if ($tails === []) {
                fwrite(STDERR, "REFUSED: {$token} has no bare occurrence to extend — every copy of it belongs to a longer citation.\n");
                exit(1);
            }
            if (count($tails) !== 1) {
                fwrite(STDERR, "REFUSED: {$token} is a prefix of {$other[0]} and its own occurrences disagree about what follows (".implode('|', $tails).").\n");
                exit(1);
            }
            $tail = $tails[0];
            if ($tail === '<' || $tail === '') {
                fwrite(STDERR, "REFUSED: {$token} ends a run, so no plain-text extension can disambiguate it from {$other[0]}.\n");
                exit(1);
            }
            echo sprintf('extended  :%s  ->  [%s%s]  (disambiguates it from %s)\n', $token, $token, $tail, $other[0]);
            $edits[$i][0] = $token.$tail;
            $edits[$i][1] = $edit[1].$tail;
            $edits[$i][3] = substr_count($xml, $token.$tail);
            $colliding = true;
            break 2;
        }
    }
    if (! $colliding) {
        break;
    }
}

foreach ($edits as $i => $e) {
    foreach ($edits as $j => $f) {
        if ($i !== $j && str_contains($f[0], $e[0])) {
            fwrite(STDERR, "REFUSED: {$e[0]} is still a substring of {$f[0]} after extension.\n");
            exit(1);
        }
    }
}

// The sentence cites its own new method, so its bounds are measured here rather than carried from a
// previous run: this pass edits nothing in that file, and a pointer copied from an earlier reading is
// exactly how a document accumulates a live line that belongs to something else.
$svcLines = array_map('trim', explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/app/Services/AcademicStandingService.php'))));
$svcStart = null;
foreach ($svcLines as $i => $line) {
    if (str_starts_with($line, 'public function gradeHistory(')) {
        $svcStart = $i + 1;
        break;
    }
}
$svcStop = null;
$depth = 0;
for ($i = ($svcStart ?? 1) - 1; $i < count($svcLines); $i++) {
    $depth += substr_count($svcLines[$i], '{') - substr_count($svcLines[$i], '}');
    if ($depth === 0 && $svcLines[$i] === '}') {
        $svcStop = $i + 1;
        break;
    }
}
if ($svcStart === null || $svcStop === null) {
    fwrite(STDERR, "REFUSED: gradeHistory() could not be bounded in the service, so its citation cannot be written.\n");
    exit(1);
}
echo "measured AcademicStandingService::gradeHistory() at :{$svcStart}-{$svcStop}\n";

$sentences = [
    'gradeTrail' => 'The record page also reads the student’s whole grade trail: every subject they have ever been loaded for, grouped by the academic year and semester that recorded it, and filterable by either. It is the same evidence the standing derivation above reads — one query, AcademicStandingService::gradeHistory() ( AcademicStandingService.php:'.$svcStart.'-'.$svcStop.' ) — with two deliberate differences for a desk that signs the record rather than proposes a standing: the review includes the term being signed and any subject nobody has graded yet, and an ungraded row is painted “No grade recorded” and never counted as a pass ( the passing line comes from gradescale, Gradescale.php:34-39 , and the page says out loud when no scale is on file instead of implying the assumed 3.00 is the school’s own ). Before this the desk could see the conclusion and the current load, but not the terms the conclusion was drawn from.',
];

foreach ($sentences as $key => $text) {
    if (substr_count($xml, str_starts_with($text, 'The record page') ? 'The record page also reads the student' : $key) > 0) {
        fwrite(STDERR, "REFUSED: this pass has already been applied (its §16.5 sentence is in the document).\n");
        exit(1);
    }
}

// The two citations into the page, measured the same way rather than carried over from the probe.
$showRangeHits = $where($headShow, $liveShow, 244, 255);
if (count($showRangeHits) !== 1) {
    fwrite(STDERR, 'REFUSED: the Show.jsx :244-255 window matches at '.count($showRangeHits)." places — its new number cannot be measured.\n");
    exit(1);
}
$showRangeNew = $showRangeHits[0];

$showSingleNeedle = $headShow[449];
$showSingleHits = [];
foreach ($liveShow as $i => $line) {
    if ($line === $showSingleNeedle) {
        $showSingleHits[] = $i + 1;
    }
}
if (count($showSingleHits) !== 1) {
    fwrite(STDERR, 'REFUSED: the line HEAD held at Show.jsx:450 appears '.count($showSingleHits)." times in the working page, so the bare ':450' cannot be re-pointed safely.\n");
    exit(1);
}
$showSingleNew = $showSingleHits[0];

echo "measured into Registrar/Show.jsx: 244-255 -> {$showRangeNew}-".($showRangeNew + 11).",  450 -> {$showSingleNew}\n";

$edits[] = ['Registrar/Show.jsx:244-255', 'Registrar/Show.jsx:'.$showRangeNew.'-'.($showRangeNew + 11), null, 1];
$edits[] = [':450', ':'.$showSingleNew, 'Standing and year level are rendered at', 1];
$edits[] = [
    'Year {n} ({standing})',
    'the year level as an ordinal with the standing beside it',
    null,
    1,
];

// The bare continuation cannot be measured from the XML alone (it names no file), so the sentence it
// sits in is what carries it: assert the citation pair reads correctly once the pass is done.
$newCellPhrase = 'Registrar/Show.jsx:'.$showRangeNew.'-'.($showRangeNew + 11).' , :'.$showSingleNew;

// Anchored on the next section's heading and inserted BEFORE it, which lands the paragraph at the end
// of §16.5. The §16.5 sentence first looked tempting and is refused by the engine itself: that phrasing
// about the phase label is written twice in the document.
$insertAnchor = '17. Stage 10 — Blocking and Scheduling';
$inserts = [[
    'anchor' => $insertAnchor,
    'xml' => '<w:p><w:r><w:t xml:space="preserve">'.$sentences['gradeTrail'].'</w:t></w:r></w:p>',
    'pos' => 'before',
]];

echo "\n---- stage 1: needle census against the live document ----\n";
printf("tokens into RegistrarController.php: %d (%d distinct), to be shifted: %d\n", count($tokens), count($distinct), $shifted);
foreach ($edits as [$from, , , $expect]) {
    $seen = substr_count($xml, $from);
    printf("%-58s seen %d, expected %d  %s\n", substr($from, 0, 56), $seen, $expect, $seen === $expect ? '' : '  <-- REFUSES');
}
$anchorSeen = substr_count($xml, $insertAnchor);
printf("%-58s seen %d, expected 1  %s\n", 'insert anchor', $anchorSeen, $anchorSeen === 1 ? '' : '  <-- REFUSES');

foreach ($edits as [$from, $to, , $expect]) {
    if (substr_count($xml, $from) !== $expect) {
        fwrite(STDERR, "\nREFUSED: needle [{$from}] seen ".substr_count($xml, $from)." times, expected {$expect}. Nothing written.\n");
        exit(1);
    }
}
if ($anchorSeen !== 1) {
    fwrite(STDERR, "\nREFUSED: the insert anchor is not unique. Nothing written.\n");
    exit(1);
}

echo "\n---- stage 2: every re-taken pointer must still name the same text ----\n";

// Asserted against the edits this pass is about to write, not against a list typed here: a shift that
// is wrong fails here, and a pointer cannot be "verified" by arithmetic from the same bug that made it.
$bad = 0;
$verified = 0;
foreach ($edits as [$from, $to]) {
    foreach ([['RegistrarController.php', $headRc, $liveRc], ['Registrar/Show.jsx', $headShow, $liveShow]] as [$base, $h, $l]) {
        if (! preg_match('/^'.preg_quote($base, '/').':(\d+)-(\d+)$/', trim($from), $mf)
            || ! preg_match('/^'.preg_quote($base, '/').':(\d+)-(\d+)$/', trim($to), $mt)) {
            continue;
        }
        $hFrom = (int) $mf[1];
        $hTo = (int) $mf[2];
        $nFrom = (int) $mt[1];
        $nTo = (int) $mt[2];
        if ($nTo - $nFrom !== $hTo - $hFrom) {
            continue;   // deliberately re-bounded (the render block); checked by content in $fresh below
        }
        $headWindow = trim(implode("\n", array_slice($h, $hFrom - 1, $hTo - $hFrom + 1)));
        $liveWindow = trim(implode("\n", array_slice($l, $nFrom - 1, $nTo - $nFrom + 1)));
        $same = $headWindow !== '' && $headWindow === $liveWindow;
        printf("%-40s :%d-%d -> :%d-%d  %s\n", $base, $hFrom, $hTo, $nFrom, $nTo, $same ? 'same bytes' : 'FAIL — the windows differ');
        $same ? $verified++ : $bad++;
    }
}
printf("%d pointer(s) re-read against both versions of their file\n", $verified);

// Pointers the pass introduces for the first time, asserted against the construct they claim.
$fresh = [
    ['app/Http/Controllers/Registrar/RegistrarController.php', $renderStart, $renderStop, "return Inertia::render('Registrar/Show'"],
    ['app/Services/AcademicStandingService.php', 101, 209, 'public function gradeHistory('],
    ['app/Models/Gradescale.php', 34, 39, 'function passingCeiling'],
];
foreach ($fresh as [$file, $from, $to, $needle]) {
    $lines = explode("\n", str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$file)));
    $window = implode("\n", array_slice($lines, $from - 1, $to - $from + 1));
    $ok = str_contains($window, $needle);
    printf("%-40s :%d-%d holds %-34s %s\n", basename($file), $from, $to, '['.$needle.']', $ok ? 'ok' : 'FAIL');
    if (! $ok) {
        $bad++;
    }
}

if ($bad > 0) {
    fwrite(STDERR, "\nREFUSED: {$bad} pointer(s) do not name the same text after the shift. Nothing written.\n");
    exit(1);
}

echo "\n---- stage 3: apply ----\n";
docxApply($docx, $backup, $edits, [], $apply, [], $inserts);

if (! $apply) {
    echo "\nDRY RUN — nothing written. Re-run with --apply.\n";
    exit(0);
}

// ---------------------------------------------------------------- post-write census, read back from the archive

$z2 = new ZipArchive;
$z2->open($docx);
$after = (string) $z2->getFromName('word/document.xml');
$z2->close();

$expectations = [
    'RegistrarController.php:110-129' => 0,
    'RegistrarController.php:'.$renderStart.'-'.$renderStop => 1,
    'Registrar/Show.jsx:244-255' => 0,
    'Registrar/Show.jsx:'.$showRangeNew.'-'.($showRangeNew + 11) => 1,
    'Year {n} ({standing})' => 0,
    'the year level as an ordinal with the standing beside it' => 1,
    'The record page also reads the student' => 1,
    'AcademicStandingService.php:'.$svcStart.'-'.$svcStop => 1,
];

echo "\n---- stage 4: what the written document now says ----\n";
$problems = 0;
foreach ($expectations as $needle => $want) {
    $seen = substr_count($after, $needle);
    printf("%-62s %d (want %d)%s\n", $needle, $seen, $want, $seen === $want ? '' : '  <-- BAD');
    if ($seen !== $want) {
        $problems++;
    }
}

$stillOld = preg_match_all('/RegistrarController\.php:(\d+)(?:-(\d+))?/', $after, $now, PREG_SET_ORDER);
echo "\ncitations into RegistrarController.php after the pass: {$stillOld} (was ".count($tokens).")\n";

// The bare continuation is judged on the flattened prose, because that is the only place the citation,
// the comma and its neighbour sit in one string — document.xml keeps them in separate runs.
preg_match_all('/<w:t(?: [^>]*)?>(.*?)<\/w:t>/s', $after, $tm);
$flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1);
$phrases = [
    $newCellPhrase => 1,
    'Registrar/Show.jsx:'.$showRangeNew.'-'.($showRangeNew + 11).' , :450' => 0,
];
foreach ($phrases as $phrase => $want) {
    $seen = substr_count($flat, $phrase);
    printf("%-62s %d (want %d)%s\n", 'flat: '.$phrase, $seen, $want, $seen === $want ? '' : '  <-- BAD');
    if ($seen !== $want) {
        $problems++;
    }
}

if ($problems > 0) {
    fwrite(STDERR, "{$problems} post-write figure(s) disagree. The docx is at {$backup}.\n");
    exit(1);
}

echo "PASS 249 applied.\n";
exit(0);
