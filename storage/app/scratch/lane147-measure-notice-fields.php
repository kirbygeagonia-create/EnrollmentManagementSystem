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
$PARTS = [
    'title' => [
        'writers' => ['title', 'subject'],
        'screen' => ['title', 'subject'],
    ],
    'action required' => [
        'writers' => ['actionRequired', 'action', 'requiredAction'],
        'screen' => ['actionRequired', 'actionRequired'],
    ],
    'record reference' => [
        'writers' => ['recordReference', 'schoolIdNumber', 'studentNumber', 'recordId', 'enrollmentId', 'reference'],
        'screen' => ['recordReference', 'schoolIdNumber', 'studentNumber'],
    ],
    'timestamp' => [
        'writers' => [],
        'screen' => ['created_at', 'formatSchoolMoment'],
        'note' => 'the column, not the payload: notifications.created_at is the moment',
    ],
    'protected deep link' => [
        'writers' => ['url', 'link', 'route', 'target'],
        'screen' => ['url', 'link', 'href', 'route('],
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
if ($writerPayload === '') {
    fwrite(STDERR, "REFUSED: no enclosing array literal was found for a `'message'` key in the listeners, so the "
        ."writer surface read nothing and every field would be reported absent by accident.\n");

    exit(1);
}
printf("writer payload block: %d bytes from the array that holds the 'message' key\n", $payloadLen);

$screenFull = (string) file_get_contents($root.'/'.$screen);
// The bell's ROW block only. The layout file also contains a logo, a menu and a profile dropdown, and words like
// `title` and `route(` live there — crediting the whole file is the same over-scope that made the loading census
// pass 24 pages for a Save button's pending state.
$rowAt = strpos($screenFull, '<div key={n.id}');
if ($rowAt === false) {
    fwrite(STDERR, "REFUSED: the bell's row block ('<div key={n.id}') is not in {$screen}; the screen surface would "
        ."judge a region it never located.\n");

    exit(1);
}
$screenText = substr($screenFull, $rowAt, 1800);
printf("writer payload block: %d bytes from the 'message' literal\n", strlen($writerPayload));
printf("screen row block: %d bytes from '<div key={n.id}'\n\n", strlen($screenText));

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

printf("\n%s part(s) are asked for by prompt line 444 and are not carried by both surfaces.\n", 5);
if ($missing === 0) {
    echo "no gap.\n";
} else {
    echo 'this is §28.3 G-35\'s measured content — the register row says the bell renders a sentence and a moment.\n';
}

exit(0);
