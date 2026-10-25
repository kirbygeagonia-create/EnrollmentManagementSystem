<?php

/**
 * The document's citation attribution rules, in ONE place.
 *
 * Three instruments ask the same two questions of the authority docx's flat dump:
 *   - which file does a bare `:NN` belong to, when the sentence spells no file out (`sweep-docx-citations.php`
 *     judges its bounds, `lane70-anchor-cites.php` asks whether the line holds the thing the prose names), and
 *   - which construct a citation is offered for, when a sentence names several (`lane32-cite-meaning-sweep.php`,
 *     and again `lane70-anchor-cites.php`).
 * Each rule was bought with a defect: nearest-distance hung `ProvisionalClearanceRequirements::NAMES` on a range
 * six characters away across a comma, and `logoDataUri` on `:31`, inventing two document defects that did not
 * exist; a checker that needed a filename before the colon saw only one of `ClearanceController.php:380 and :404`
 * and cleared a pointer sitting on a blank line; and an attribution *invented by distance* past 160 characters
 * once hung `:248-267` on `IDPolicy.php`, a 135-line file, where the number was really `IDController.php`'s
 * validate action. `lane70-anchor-cites.php` reimplemented both questions badly — it took the FIRST file named
 * anywhere on the dump row and the FIRST identifier in the sentence — and the measured result was 47 complaints,
 * 0 of them the document's.
 *
 * So the rules live here and each tool asks them. A second definition of one word is the defect lane 69 spent a
 * batch removing for year levels, and it is worth removing here for the same reason: when two checkers disagree
 * about the same sentence, a reader cannot tell which one the document has to satisfy.
 *
 * This file is required, never run. `php tools/check-cite-attribution.php` does not exist; the behaviour is
 * proved by `mutate-lane69-bare-cites.php` (the sweep), `mutate-lane32-sweep.php` (the meaning sweep) and
 * `mutate-lane70-anchor.php` (the anchor checker), which all plant defects in a COPY through --dump= and require
 * the named complaint to appear.
 */

/** The shared filename pattern. Hyphens and directory prefixes belong in the name: `clearance-slip.blade.php:44`
 *  is ONE citation, and a pattern that stops at the hyphen reports a missing file called `slip.blade.php`. */
function docxFilenamePattern(): string
{
    return '(?:(?:[A-Za-z0-9_.\-]+\/)*[A-Za-z0-9_.\-]+\.(?:php|jsx|js|json|neon|xml|md))';
}

/**
 * One scan of the whole dump. Returns:
 *   named   — every `File.php:NN` / `:NN-MM` citation as [file, from, to, offset, end]
 *   bare    — every continuation token as [from, to, offset]; `to` equals `from` when the document wrote one line
 *   anchors — everything a bare token may belong to, in offset order: a full citation, or a file named on its own
 *             in the cell before it (`( Students/Show.jsx ) | Name, School ID ( :152 )` names its file one cell
 *             earlier), because a checker that only reads `File:NN` calls that pointer blind and passes it.
 */
function docxScan(string $text): array
{
    $f = docxFilenamePattern();
    $pattern = '/(?<name>'.$f.'):(?<from>\d+)(?:-(?<to>\d+))?'
        .'|(?<fileonly>'.$f.')(?![:\w])'
        .'|(?<lead>[,(]|\bat|\band|\bto|\bis)[\s;]*:(?<bareFrom>\d+)(?:-(?<bareTo>\d+))?(?![\w\-])/';
    preg_match_all($pattern, $text, $all, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE);

    $named = [];
    $bare = [];
    $anchors = [];
    foreach ($all as $x) {
        $off = $x[0][1];
        if (($x['name'][0] ?? null) !== null) {
            $named[] = [$x['name'][0], (int) $x['from'][0],
                ($x['to'][0] ?? null) === null ? null : (int) $x['to'][0], $off, $off + strlen($x[0][0])];
            $anchors[] = [$off, $off + strlen($x[0][0]), $x['name'][0]];
        } elseif (($x['fileonly'][0] ?? null) !== null) {
            $anchors[] = [$off, $off + strlen($x[0][0]), $x['fileonly'][0]];
        } else {
            $bare[] = [(int) $x['bareFrom'][0],
                ($x['bareTo'][0] ?? null) === null ? null : (int) $x['bareTo'][0], $off];
        }
    }

    return ['named' => $named, 'bare' => $bare, 'anchors' => $anchors];
}

/** Byte offsets at which each dump line starts. `\n` only: `\R` also matches 0x85, which is a UTF-8 continuation
 *  byte inside an emoji, and a report built on it prints line numbers no editor would show. */
function docxLineStarts(string $text): array
{
    $starts = [0];
    $pos = 0;
    while (($nl = strpos($text, "\n", $pos)) !== false) {
        $starts[] = $nl + 1;
        $pos = $nl + 1;
    }

    return $starts;
}

function docxLineOf(int $offset, array $lineStart): int
{
    $lo = 0;
    $hi = count($lineStart) - 1;
    while ($lo < $hi) {
        $mid = intdiv($lo + $hi + 1, 2);
        if ($lineStart[$mid] <= $offset) {
            $lo = $mid;
        } else {
            $hi = $mid - 1;
        }
    }

    return $lo;
}

/**
 * RULE 1 — which file owns each bare token. Parallel to `$bare`: the name spelled most recently BEFORE the token
 * on the SAME dump line, and only within `$window` bytes of its end. Anything further is not attributable, and is
 * returned as null so the caller prints it as a blind spot rather than judging it — the distance is where a
 * checker starts inventing owners. (Measured: at an unbounded window the sweep attributed `:248-267` to
 * `IDPolicy.php`, whose file ends at line 135, and the number was *right* about a different file.)
 */
function docxBareOwners(array $bare, array $anchors, array $lineStart, int $window = 160): array
{
    $owners = [];
    $ai = 0;
    $current = null;
    $currentEnd = -1;
    $currentLine = -1;
    foreach ($bare as $k => [$from, $to, $offset]) {
        $line = docxLineOf($offset, $lineStart);
        while ($ai < count($anchors) && $anchors[$ai][0] < $offset) {
            if (docxLineOf($anchors[$ai][0], $lineStart) === $line) {
                $current = $anchors[$ai][2];      // the loop keeps the LAST name on this line before the token
                $currentEnd = $anchors[$ai][1];
                $currentLine = $line;
            }
            $ai++;
        }
        $owners[$k] = ($current === null || $currentLine !== $line || ($offset - $currentEnd) > $window)
            ? null : ['file' => $current, 'line' => $line, 'from' => $from, 'to' => $to ?? $from];
    }

    return $owners;
}

/**
 * RULE 2 — which citation a named construct owns. The document glosses ranges in BOTH orders, so distance alone
 * is not the rule:
 *   LABEL first  — `signStepByOffice() :150-174`, `recordIssue() at :105`: nothing but spaces or the word `at`
 *                  between name and range, so the name belongs to the range AFTER it. A name that OPENS a
 *                  parenthesis the range sits inside reaches forward too — `confirmClearance() records the
 *                  officer's confirmation as the verdict's last clause ( :958-999 , permission … )`.
 *   GLOSS after  — `:734-740 builds one named requirement line per office from X::NAMES`: the words following a
 *                  range describe THAT range, and a comma, a closing parenthesis or a period means the run of
 *                  words has already moved on to the next item.
 * `$cites` items need `start`/`end`; `$symbols` items need `at`/`end`. Returns two views of ONE pairing rule:
 *   `bySymbol` — symbol key => the citation that symbol owns. `lane32-cite-meaning-sweep.php` judges every one of
 *                these, because two constructs reaching the same range is two claims worth checking.
 *   `byCite`   — citation key => the symbol nearest to it. `lane70-anchor-cites.php` starts here, because asking
 *                a bare pointer "does the line hold the thing the sentence names" only means something once the
 *                sentence's own pairing is settled — and the FIRST name in the sentence is what produced its 33
 *                false complaints. The policy differs; the rule does not.
 */
function docxPairByUnit(string $unit, array $cites, array $symbols): array
{
    $bySymbol = [];
    foreach ($symbols as $key => $s) {
        $best = docxPairForSymbol($unit, $cites, $s['at'], $s['end']);
        if ($best === null) {
            continue;
        }
        $bySymbol[$key] = $best;
    }

    $byCite = [];
    foreach ($bySymbol as $key => $ci) {
        $d = docxPairDistance($cites, $ci, $symbols[$key]);
        // The incumbent's distance is measured from ITS own claim, not from the symbol key read as a cite index.
        if (! isset($byCite[$ci])
            || $d < docxPairDistance($cites, $bySymbol[$byCite[$ci]], $symbols[$byCite[$ci]])) {
            $byCite[$ci] = $key;
        }
    }

    return ['bySymbol' => $bySymbol, 'byCite' => $byCite];
}

/** The distance a construct sits from the citation it claimed — the tie-break when two of them reach one range. */
function docxPairDistance(array $cites, int $ci, array $symbol): int
{
    return (int) abs($cites[$ci]['start'] - $symbol['end']);
}

/**
 * RULE 2, applied to ONE construct. Returns the citation key it owns, or null when nothing owns it. This is the
 * function `lane32-cite-meaning-sweep.php` calls per symbol, so its behaviour is the rule's behaviour.
 */
function docxPairForSymbol(string $unit, array $cites, int $at, int $end): ?int
{
    $next = null;
    $prev = null;
    foreach ($cites as $ci => $c) {
        if ($c['start'] >= $end && ($next === null || $c['start'] < $cites[$next]['start'])) {
            $next = $ci;
        }
        if ($c['end'] <= $at && ($prev === null || $c['end'] > $cites[$prev]['end'])) {
            $prev = $ci;
        }
    }
    $forwardGap = $next === null ? null : substr($unit, $end, $cites[$next]['start'] - $end);
    $reachesForward = $forwardGap !== null
        && (
            (strlen(trim($forwardGap)) < 25 && ! preg_match('/[,.;)\n]/', $forwardGap))
            || (str_contains($forwardGap, '(') && ! str_contains($forwardGap, ')'))
        );
    $best = $reachesForward ? $next : ($prev ?? $next);

    return $best;
}

/**
 * RULE 2b — the construct a cited LINE sits inside, read from the file itself: scanning upward from `$at` for the
 * nearest `function`, `const`, arrow-function/`const` component or `class`. This is what makes a pointer at
 * "the verdict's last clause", "the signature line" or "the cap" true: the citation names a clause INSIDE the
 * construct the prose names, and only an instrument that knows the enclosing construct can tell that from drift.
 * `lane32-cite-meaning-sweep.php` prints this as `encloses`; the anchor checker treats it as OK.
 */
function docxEnclosingConstruct(array $lines, int $at): string
{
    $class = '';
    for ($i = $at - 1; $i >= 0; $i--) {
        $l = trim($lines[$i]);
        if ($class === '' && preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/', $l, $m)) {
            $class = $m[1];
        }
        if (preg_match('/\bfunction\s+([A-Za-z0-9_]+)\s*[({\n]/', $l, $m)) {
            return $m[1].'()';
        }
        if (preg_match('/^const\s+([A-Za-z0-9_]+)/', $l, $m)) {
            return $m[1].' (const)';
        }
        if (preg_match('/\b(?:const|let|var)\s+([A-Z][A-Za-z0-9_]*)\s*=\s*(?:\(\s*\)|[A-Za-z0-9_$]+\s*=>|function)/', $l, $m)) {
            return $m[1].' (component)';
        }
    }

    return $class !== '' ? $class.' (file scope)' : '';
}

/**
 * RULE 3 — a register row's own column label is not a construct the citation was offered for. §28.x rows lead
 * with the field they describe (`ROW 2364: E-11 | … | maxStudents | Filing or editing a schedule …`), and a
 * checker that searches the cited lines for `maxStudents` reports MOVED about a citation that never claimed to
 * point at that column. A label is a cell: it is delimited by the row's own separators, never by prose.
 */
function docxCellLabels(string $dumpLine): array
{
    if (! preg_match('/^ROW \d+:|\|/', $dumpLine)) {
        return [];
    }
    $cells = preg_split('/[|\t]+/', $dumpLine) ?: [];
    $labels = [];
    foreach ($cells as $c) {
        $c = trim(preg_replace('/^ROW \d+:\s*/', '', $c) ?? '');
        if (preg_match('/^(?:[A-Za-z][A-Za-z0-9_]*[A-Z][A-Za-z0-9_]*|[a-z]+(?:[A-Z][a-z0-9]+)+)$/', $c) === 1) {
            $labels[] = $c;
        }
    }

    return $labels;
}
