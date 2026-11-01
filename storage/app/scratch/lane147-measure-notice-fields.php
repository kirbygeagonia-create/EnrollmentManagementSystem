<?php

/**
 * Lane 147 — what a notice actually carries, against the five parts prompt line 444 asks for.
 *
 * The line reads: "Show an intelligible title, action required, record reference, timestamp, and protected deep
 * link." §28.3 G-35 records that the bell renders a sentence and a moment and nothing else. That is a claim about
 * three surfaces at once — what the writers put in `data`, what the rows hold, and what the screen reads — and lane
 * 71's lesson is that a value carried by three surfaces must be read from all three, because agreeing from two while
 * the third is blind is how an absent field survives.
 *
 * So the census binds each part to a NAME that must appear: in the listener's payload array, in the bell's render,
 * and optionally in the rows (`--rows`, against whichever schema is named). A part that no surface spells is MISSING,
 * and a part the screen substitutes for is reported as INVENTED rather than present — the bell's
 * `n.data?.message || 'Notification'` is a fallback word on a screen a person reads, which this repository has
 * refused twice already (a canned rejection sentence, a hand-spelled year level).
 *
 * Usage: php storage/app/scratch/lane147-measure-notice-fields.php [--rows] [--schema=<name>]
 */
$root = dirname(__DIR__, 3);
chdir($root);

$wantRows = false;
$schema = null;
$screenOverride = null;
$listenersOverride = null;
foreach ($argv as $i => $a) {
    if ($i === 0) {
        continue;
    }
    if ($a === '--rows') {
        $wantRows = true;

        continue;
    }
    if (str_starts_with($a, '--schema=')) {
        $schema = substr($a, 9);
        $wantRows = true;

        continue;
    }
    // Addressable surfaces, so a harness can plant a field or delete the bell's row marker without editing a desk
    // screen: a checker whose positive control is unreachable is a checker that can only say "missing".
    if (str_starts_with($a, '--screen=')) {
        $screenOverride = substr($a, 9);

        continue;
    }
    if (str_starts_with($a, '--listeners=')) {
        $listenersOverride = substr($a, 12);

        continue;
    }
    fwrite(STDERR, "REFUSED: this tool takes --rows, --schema=<name>, --screen=<path> or --listeners=<path> and received '{$a}'.\n");

    exit(1);
}

if ($schema !== null && ! preg_match('/\A[A-Za-z0-9_]+\z/', $schema)) {
    fwrite(STDERR, "REFUSED: --schema={$schema} is not a bare schema name.\n");

    exit(1);
}

// The five parts, each with the vocabulary that would satisfy it on either side. Written as a table so a later
// reader changes one list rather than three, and so the prompt's own words are the key.
// The five parts, each with the vocabulary that would satisfy it on either side. On the SCREEN side the tokens are
// payload ACCESSORS, not bare words: a layout file contains the word 'title' in a dozen unrelated places (a page
// header, an aria label, a modal heading), and matching the bare word is what let an earlier run credit the bell
// with fields it never read. Reading `n.data?.title` is the only shape that means "this screen shows that field".
$PARTS = [
    'title' => [
        'writers' => ['title', 'subject'],
        'screen' => ['n.data?.title', 'n.data.title'],
    ],
    'action required' => [
        'writers' => ['actionRequired', 'action', 'requiredAction'],
        'screen' => ['n.data?.actionRequired', 'n.data.actionRequired'],
    ],
    'record reference' => [
        'writers' => ['recordReference', 'schoolIdNumber', 'studentNumber', 'recordId', 'enrollmentId', 'reference'],
        'screen' => ['n.data?.recordReference', 'n.data.recordReference'],
    ],
    'timestamp' => [
        'writers' => [],
        'screen' => ['n.created_at', 'formatSchoolMoment'],
        'note' => 'the column, not the payload: notifications.created_at is the moment',
    ],
    'protected deep link' => [
        'writers' => ['url', 'link', 'route', 'target'],
        'screen' => ['n.data?.link', 'n.data.link'],
    ],
];

// Addressable surfaces, so a harness can plant a field or erase the bell's row marker without editing a desk screen:
// a checker whose positive control is unreachable is a checker that can only ever say "missing".
$writers = $listenersOverride === null
    ? ['app/Listeners/SendEnrollmentNotification.php', 'app/Listeners/SendWorkflowNotification.php']
    : [$listenersOverride];
$screen = $screenOverride ?? 'resources/js/Layouts/AuthenticatedLayout.jsx';

/**
 * The byte span of the array literal that encloses `$at`, scanned by depth so a nested `])` cannot end it early.
 * Returns 0 when no enclosing `[` exists, which the caller must treat as an unread surface rather than as an empty
 * payload.
 */
$braceSpanFrom = static function (string $text, int $at): array {
    $open = strrpos(substr($text, 0, $at), '[');
    if ($open === false) {
        return [0, 0];
    }
    $depth = 0;
    $len = strlen($text);
    for ($p = $open; $p < $len; $p++) {
        $c = $text[$p];
        if ($c === '[') {
            $depth++;
        } elseif ($c === ']') {
            $depth--;
            if ($depth === 0) {
                // The span is the WHOLE array literal, from its opening bracket. Taking it from the `'message'`
                // key instead was the defect the harness caught: a field written before that key — which is where
                // a title would naturally go — sits outside the window, so the census reported a planted title as
                // absent and looked right while reading too little.
                return [$open, $p - $open + 1];
            }
        }
    }

    return [0, 0];
};

foreach (array_merge($writers, [$screen]) as $f) {
    if (! file_exists($root.'/'.$f)) {
        fwrite(STDERR, "REFUSED: {$f} is not in the tree, so the census would judge a surface it never read.\n");

        exit(1);
    }
}

$writerText = '';
foreach ($writers as $f) {
    $writerText .= (string) file_get_contents($root.'/'.$f)."\n";
}
// The payload block only: everything before it is a docblock that may name the same words in prose, which is how a
// reader credits a field the code never writes. The block is taken by brace depth from the array that opens with the
// message key, so a nearby query naming `enrollmentId` cannot be credited to the notice.
$payloadAt = strpos($writerText, "'message'");
[$payloadStart, $payloadLen] = $payloadAt === false ? [0, 0] : $braceSpanFrom($writerText, $payloadAt);
$writerPayload = $payloadLen > 0 ? substr($writerText, $payloadStart, $payloadLen) : '';
// The listeners do not spell `title`, `actionRequired` or `link` in their own literal: both merge a shared builder
// with `+ NoticeFacts::forEnrollment(…)`, which is the one-definition rule this repository applies everywhere. So
// the writer surface reads that builder too, or a census of the listeners alone reports a field as absent when the
// code writes it on every path — the shape where a busy instrument cannot report a negative.
$factsPath = 'app/Support/NoticeFacts.php';
if (! file_exists($root.'/'.$factsPath)) {
    fwrite(STDERR, "REFUSED: {$factsPath} is not in the tree, so the fields the listeners merge in could not be read.\n");

    exit(1);
}
if ($writerPayload === '') {
    // Checked BEFORE the builder is appended. The first version concatenated first and tested for emptiness after,
    // so the refusal could never fire and a listener with no payload literal at all was read as "writer says NO for
    // every field" — an unread surface reported as a measurement, which is the shape this repository refuses.
    fwrite(STDERR, "REFUSED: no `'message'` payload literal was found in the listeners, so the writer surface read "
        ."nothing and every field would be reported absent by accident.\n");

    exit(1);
}
$writerPayload .= "\n".(string) file_get_contents($root.'/'.$factsPath);
printf("writer surface: %d bytes (payload literals plus the shared builder they merge)\n", strlen($writerPayload));

$screenFull = (string) file_get_contents($root.'/'.$screen);
// The bell's ROW block, taken to the end of the map callback rather than a fixed byte window: a 1,800-byte window
// passed an earlier reading, and when the row grew the timestamp fell outside it and the census reported a field the
// screen does read as missing. The bound is the block, so it moves with the code.
$rowAt = strpos($screenFull, '<div key={n.id}');
$mapEnd = strpos($screenFull, '))', $rowAt === false ? 0 : $rowAt);
if ($rowAt === false || $mapEnd === false) {
    fwrite(STDERR, "REFUSED: the bell's row block ('<div key={n.id}' … '))') is not in {$screen}; the screen "
        ."surface would judge a region it never located.\n");

    exit(1);
}
$screenText = substr($screenFull, $rowAt, $mapEnd - $rowAt);
printf("screen row block: %d bytes, from '<div key={n.id}' to the end of the map callback\n\n", strlen($screenText));

$rowsText = '';
if ($wantRows) {
    $env = [];
    foreach (file($root.'/.env') ?: [] as $line) {
        if (preg_match('/\A\s*([A-Z_]+)\s*=\s*(.*)\z/', trim($line), $m) === 1) {
            $env[$m[1]] = trim($m[2], " \t\"'");
        }
    }
    $db = $schema ?? ($env['DB_DATABASE'] ?? null);
    if ($db === null || $db === '') {
        fwrite(STDERR, 'REFUSED: no schema was named and .env answers none, so the rows surface would be read from '
            ."a guess.\n");

        exit(1);
    }
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'] ?? '127.0.0.1',
        $env['DB_PORT'] ?? '3306', $db);
    try {
        $pdo = new PDO($dsn, $env['DB_USERNAME'] ?? 'root', $env['DB_PASSWORD'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (PDOException $e) {
        fwrite(STDERR, "REFUSED: the rows surface could not open '{$db}': ".$e->getMessage().' — say which schema '
            ."was asked for rather than reporting the field as absent.\n");

        exit(1);
    }
    $n = 0;
    foreach ($pdo->query('select `data` from `notifications`') ?: [] as $r) {
        $rowsText .= (string) $r['data']."\n";
        $n++;
    }
    if ($n === 0) {
        fwrite(STDERR, "REFUSED: the `notifications` table in '{$db}' holds no row, so the rows surface can say "
            ."nothing about which fields are ever written.\n");

        exit(1);
    }
    printf("rows surface: %d notification row(s) read from %s\n\n", $n, $db);
}

printf("%-22s %-12s %-12s %s\n", 'part of the notice', 'WRITER', 'SCREEN', 'ROWS');
printf("%-22s %-12s %-12s %s\n", str_repeat('-', 20), str_repeat('-', 10), str_repeat('-', 10), str_repeat('-', 6));
$missing = 0;
foreach ($PARTS as $label => $spec) {
    $w = false;
    foreach ($spec['writers'] as $token) {
        if (str_contains($writerPayload, "'".$token."'")) {
            $w = true;

            break;
        }
    }
    if ($w === false && $label === 'timestamp') {
        // The writers do not put a moment in the payload: the column is the moment, and the screen reads it.
        $w = null;
    }
    $s = false;
    foreach ($spec['screen'] as $token) {
        if (str_contains($screenText, $token)) {
            $s = true;

            break;
        }
    }
    $r = 'n/a';
    if ($wantRows) {
        $r = 'no';
        foreach (array_merge($spec['writers'], $spec['screen']) as $token) {
            if ($token !== '' && str_contains($rowsText, '"'.$token.'"')) {
                $r = 'yes';

                break;
            }
        }
        if ($label === 'timestamp') {
            $r = 'n/a';
        }
    }
    $wLabel = $w === null ? 'column' : ($w ? 'yes' : 'NO');
    printf("%-22s %-12s %-12s %s\n", $label, $wLabel, $s ? 'yes' : 'NO', $r);
    if (! $w && $w !== null) {
        $missing++;
    }
    if (! $s) {
        $missing++;
    }
}

// The invented substitution, reported rather than counted as a present field.
$fallback = substr_count($screenText, "|| 'Notification'");
if ($fallback > 0) {
    printf("\nINVENTED  the bell substitutes the word 'Notification' %d time(s) for a message that did not "
        ."arrive — a label on a screen a person reads, built from nothing.\n", $fallback);
    $missing++;
}

// The rows surface cannot be satisfied by a code change: the 14 notices live `ems` holds were written before this
// build, and a rebuilt payload does not rewrite a stored row. So the claim printed here is about the two surfaces a
// batch can fix, and the row column is named as its own question rather than folded into a total.
$gaps = intdiv($missing, 1);
printf("\n%d side(s) of the writer/screen pair are missing across the five parts the prompt names (%d of 10 cells).\n",
    $gaps, $gaps);
if ($gaps === 0) {
    echo "no gap: every part is written by the listeners and read by the bell.\n";
    if ($wantRows) {
        echo "rows surface: the stored notices keep the payload they were written with, so older rows carry fewer parts. "
            ."That is history, not a defect, and it is why the row column is printed beside the pair rather than inside it.\n";
    }
} else {
    echo "this is §28.3 G-35's measured content: a part the writer or the screen does not carry.\n";
}

exit(0);
