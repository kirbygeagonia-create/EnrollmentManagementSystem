<?php

/**
 * Lane 42 — the two third-party assessments at the top of this repository, re-read against the build they describe.
 *
 * `SEAIT-EMS-Round3-Current-State.md` and `Documentation/Audit-Remediation-2026-08-29.md` are kept as provenance,
 * not as current documentation. Half their sentences describe the moment they were written and stay true as
 * history; the other half are present-tense state claims the build has since walked away from — and a reader
 * cannot tell the two apart. `Documentation/UI-UX-Refinement-Task-Analysis-2026-09-17.md` points at the Round-3
 * file, so a stale figure there is a live wrong number here.
 *
 * Rule applied to every count claim:
 *   the figure is read from its own paragraph, and the LAST figure for that label in the paragraph wins (that is
 *   what a re-measurement appends to). If it matches the build: ok. If it does not and the paragraph carries a
 *   date: hist — the sentence is honestly dated to the day it counted. If it does not and no date is in sight:
 *   MISS, because an un-dated present-tense number is what a reader will quote.
 *
 * Claims that need the registry, the network, or a run we did not take are printed, never failed.
 *
 * Usage: php storage/app/scratch/lane42-assessment-claims.php
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

set_exception_handler(function (\Throwable $e): void {
    fwrite(STDERR, 'FATAL: '.$e->getMessage()."\n".$e->getFile().':'.$e->getLine()."\n");
    exit(1);
});

$root = dirname(__DIR__, 3);
$round3Path = $root.'/SEAIT-EMS-Round3-Current-State.md';
$auditPath = $root.'/Documentation/Audit-Remediation-2026-08-29.md';

foreach ([$round3Path, $auditPath] as $f) {
    if (! is_file($f)) {
        fwrite(STDERR, 'missing assessment file: '.$f."\n");
        exit(1);
    }
}

// normalize once so byte offsets, paragraph bounds and line numbers all agree
$r3 = str_replace("\r\n", "\n", (string) file_get_contents($round3Path));
$au = str_replace("\r\n", "\n", (string) file_get_contents($auditPath));
$database = DB::connection()->getDatabaseName();

printf("Round 3: %s\nAudit:   %s\nDatabase: %s\n\n", basename($round3Path), basename($auditPath), $database);

$problems = 0;
$seen = 0;
$resolved = 0;

$check = function (string $doc, string $claim, bool $ok, string $detail) use (&$problems, &$seen, &$resolved): void {
    $seen++;
    $resolved++;
    printf("  %s [%s] %-46s %s\n", $ok ? 'ok  ' : 'MISS', $doc, $claim, $detail);
    if (! $ok) {
        $problems++;
    }
};

/** A claim about a dated moment, or about something outside this repository: printed, never failed. */
$hist = function (string $doc, string $claim, string $detail) use (&$seen, &$resolved): void {
    $seen++;
    $resolved++;
    printf("  hist [%s] %-46s %s\n", $doc, $claim, $detail);
};

/** A claim the checker could not locate in the document. Counts as seen, never as resolved. */
$unbound = function (string $doc, string $claim, string $detail) use (&$seen): void {
    $seen++;
    printf("  UNBOUND [%s] %-46s %s\n", $doc, $claim, $detail);
};

/**
 * The paragraph (blank-line-delimited block) containing a byte offset.
 *
 * @return array{text:string,line:int}
 */
$paragraphAt = function (string $text, int $offset): array {
    $before = substr($text, 0, $offset);
    $start = strrpos($before, "\n\n");
    $end = strpos($text, "\n\n", $offset);
    $para = substr($text, $start === false ? 0 : $start + 2, ($end === false ? strlen($text) : $end) - ($start === false ? 0 : $start + 2));

    return ['text' => $para, 'line' => substr_count(substr($text, 0, $offset), "\n") + 1];
};

/**
 * Split on LF only. `preg_split('/\R/')` also breaks on the byte 0x85, which occurs as a UTF-8
 * continuation byte inside the emoji these reports use — it read 71 lines in a 55-line file and
 * shifted every line number it printed.
 *
 * @return list<string>
 */
$lines = function (string $text): array {
    return array_map(static fn (string $l): string => rtrim($l, "\r"), explode("\n", $text));
};

$DATED = '/20\d\d-\d\d-\d\d|Round [123]|at the time|as of|measured on|today|just now|fresh (clone|install)/i';

$labels = [
    'permissions' => '/\b(\d{1,3}) permissions\b/',
    'tables' => '/\b(\d{1,3}) tables\b/',
    'controllers' => '/\b(\d{1,3}) controllers\b/',
];

$permissions = (int) DB::table('permissions')->count();
$roles = (int) DB::table('roles')->count();
$tableRows = DB::select('SELECT COUNT(*) c FROM information_schema.tables WHERE table_schema = ?', [$database]);
$tables = (int) $tableRows[0]->c;
$controllers = 0;
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/Http/Controllers')) as $f) {
    if (str_ends_with((string) $f, 'Controller.php')) {
        $controllers++;
    }
}

$actual = ['permissions' => $permissions, 'tables' => $tables, 'controllers' => $controllers];

echo "=== Round 3: figures a reader will quote\n";

foreach ($labels as $label => $re) {
    if (! preg_match_all($re, $r3, $all, PREG_OFFSET_CAPTURE)) {
        $unbound('round3', $label.' count', 'no `N '.$label.'` figure appears in the file');

        continue;
    }
    foreach ($all[0] as $i => $m) {
        $para = $paragraphAt($r3, (int) $m[1]);
        // last figure for this label inside the paragraph is the one a re-measurement leaves on top
        preg_match_all($re, $para['text'], $later);
        if ($later === false || count($later[1]) === 0) {
            $unbound('round3', $label.' count @'.$para['line'], 'the match did not sit inside a readable paragraph');

            continue;
        }
        $stated = (int) end($later[1]);
        $claim = $label.' count ('.($i + 1).') @'.$para['line'];
        if ($stated === $actual[$label]) {
            $check('round3', $claim, true, 'paragraph says '.$stated.', build holds '.$actual[$label]);

            continue;
        }
        if (preg_match($DATED, $para['text'])) {
            $hist('round3', $claim, 'says '.$stated.' vs '.$actual[$label].' today, but the paragraph is dated — history, not a live count');

            continue;
        }
        $check('round3', $claim, false, 'says '.$stated.', build holds '.$actual[$label].', and no date in the paragraph tells the reader which it is');
    }
}

if (preg_match('/(\d{1,2}) process roles \+ Staff\/Instructor\/OfficeHead cross-cutting roles/u', $r3, $m, PREG_OFFSET_CAPTURE)) {
    $para = $paragraphAt($r3, (int) $m[1][1]);
    $sum = (int) $m[1][0] + 3;
    if ($sum === $roles) {
        $check('round3', 'role arithmetic @'.$para['line'], true, $m[1][0].' process + 3 cross-cutting = '.$sum.', roles table holds '.$roles);
    } elseif (preg_match($DATED, $para['text'])) {
        $hist('round3', 'role arithmetic @'.$para['line'], $m[1][0].' + 3 = '.$sum.' vs '.$roles.' today; the paragraph is dated');
    } else {
        $check('round3', 'role arithmetic @'.$para['line'], false, $m[1][0].' + 3 = '.$sum.', roles table holds '.$roles.', undated');
    }
} else {
    $unbound('round3', 'role arithmetic', 'the "N process roles + ..." sentence could not be read');
}

echo "\n=== Round 3: the item it left open that the code has since shipped\n";

$securityHeaders = (string) file_get_contents($root.'/app/Http/Middleware/SecurityHeaders.php');
$cspLive = str_contains($securityHeaders, "'Content-Security-Policy'");
$cspLine = 0;
foreach ($lines($securityHeaders) as $n => $l) {
    if (str_contains($l, "'Content-Security-Policy'")) {
        $cspLine = $n + 1;

        break;
    }
}

$openWords = '/(?:📋 Still open|still open|not (?:yet )?(?:shipped|set)|deferred|worth planning)/i';
// A line that says the item *was* open, or carries the date of the correction, is telling the reader
// about the change rather than claiming it. Without this, the addendum's own "was not deferred" reads
// as an open item — which is how the first run of this checker flagged its own fix.
$closedWords = '/\b(?:not deferred|not open|was never|no longer|corrected|closes?|closed|shipped)\b/i';
$cspClaims = [];
foreach ($lines($r3) as $n => $l) {
    if (! preg_match('/Content-Security-Policy|\bCSP\b/', $l)) {
        continue;
    }
    if (! preg_match($openWords, $l)) {
        continue;
    }
    $cspClaims[] = [$n + 1, trim($l), preg_match($closedWords, $l) === 1 || preg_match($DATED, $l) === 1];
}
if ($cspClaims === []) {
    $unbound('round3', 'CSP open-item rows', 'no line naming the CSP also says it is open — either the row was rewritten or the wording moved');
}
foreach ($cspClaims as [$line, $row, $qualified]) {
    if ($qualified) {
        $hist('round3', 'CSP row @'.$line.' mentions open', 'dated or negated, so it reports the change: "'.substr($row, 0, 44).'…"');

        continue;
    }
    if ($cspLive) {
        $check('round3', 'CSP row @'.$line.' calls it open', false,
            'the middleware sets it at SecurityHeaders.php:'.$cspLine.', so this row describes a closed item: "'.substr($row, 0, 48).'…"');
    } else {
        $check('round3', 'CSP row @'.$line.' calls it open', true, 'agree: the middleware sets no CSP header');
    }
}

echo "\n=== Round 3: negative claims — things that must still be absent\n";

$authRoutes = (string) file_get_contents($root.'/routes/auth.php');
$check('round3', '/register absent from routes/auth.php', substr_count($authRoutes, 'register') === 0,
    'the report verified `grep -c register routes/auth.php` = 0; today the word appears '.substr_count($authRoutes, 'register').' time(s)');

foreach (['app/Models/User.php', 'app/Http/Controllers/Auth/RegisteredUserController.php',
    'resources/js/Pages/Auth/Register.jsx', 'database/factories/UserFactory.php'] as $path) {
    // the audit file names these without the `.php`, so bind on the stem
    $stem = pathinfo(basename($path), PATHINFO_FILENAME);
    if (! str_contains($au, $stem)) {
        $unbound('audit', $stem.' stays deleted', 'the audit file never names it, so the checker has no claim to bind to');

        continue;
    }
    $check('audit', $stem.' stays deleted', ! is_file($root.'/'.$path),
        $path.' is '.(is_file($root.'/'.$path) ? 'back on disk' : 'still gone'));
}

$literalOffice = [];
foreach (['app', 'resources/js', 'tools'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (! in_array(strtolower($f->getExtension()), ['php', 'jsx', 'js'], true)) {
            continue;
        }
        $text = (string) file_get_contents((string) $f);
        if (preg_match_all("/officeId'?\s*(?:=>|===?|,|\))\s*[0-9]+/", $text, $mm)) {
            foreach ($mm[0] as $hit) {
                if (! str_contains($hit, 'OfficeId::')) {
                    $literalOffice[] = str_replace($root.'/', '', (string) $f).': '.$hit;
                }
            }
        }
    }
}
$check('round3', 'zero raw office-id integers anywhere', $literalOffice === [],
    'swept app/, resources/js/, tools/ — '.(count($literalOffice) === 0 ? 'clean' : implode('; ', array_slice($literalOffice, 0, 5))));

echo "\n=== Round 3: things it says exist\n";

foreach ([
    'audit-redaction regression test' => 'tests/Feature/Observers/AuditLogRedactionTest.php',
    'ExamControllerTest' => 'tests/Feature/Exam/ExamControllerTest.php',
    'StudentPolicy' => 'app/Policies/StudentPolicy.php',
    'create-admin command' => 'app/Console/Commands/CreateAdminUser.php',
    'CI workflow' => '.github/workflows/ci.yml',
] as $label => $path) {
    $check('round3', $label.' exists', is_file($root.'/'.$path), $path);
}

$deployment = (string) file_get_contents($root.'/DEPLOYMENT.md');
$heading6 = preg_match('/^## 6\..*/m', $deployment, $h) ? trim($h[0]) : 'no §6 heading';
$check('round3', 'DEPLOYMENT §6 is the browser/Puppeteer step', (bool) preg_match('/^## 6\.[^\n]*Puppeteer[^\n]*/mi', $deployment),
    'heading: '.$heading6);

$stampTables = ['students', 'admissions', 'enrollments', 'payments', 'studentassessments', 'studentclearances', 'clinicrecords', 'idrequests'];
$stamped = [];
$missingStamp = [];
foreach ($stampTables as $t) {
    $cols = DB::select('SELECT column_name FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name IN (?,?)',
        [$database, $t, 'created_at', 'updated_at']);
    if (count($cols) === 2) {
        $stamped[] = $t;
    } else {
        $missingStamp[] = $t;
    }
}
$check('round3', 'the 8 record-timestamp tables carry both columns', count($stamped) === 8,
    count($stamped).' of 8'.($missingStamp === [] ? '' : '; missing on '.implode(', ', $missingStamp)));

$withheld = DB::table('role_permissions')
    ->join('roles', 'roles.id', '=', 'role_permissions.roleId')
    ->join('permissions', 'permissions.id', '=', 'role_permissions.permissionId')
    ->where('permissions.name', 'students.view')
    ->whereIn('roles.name', ['Staff', 'Instructor'])
    ->pluck('roles.name');
$check('audit', 'students.view withheld from bare Staff/Instructor', $withheld->count() === 0,
    'pivot rows naming Staff or Instructor holding it: '.$withheld->count().($withheld->isEmpty() ? '' : ' ('.implode(', ', $withheld->all()).')'));

echo "\n=== Claims this checker cannot settle (printed, never failed)\n";

$hist('round3', 'commits reviewed: 71', 'a count of the repository on that day; `git log` today differs by design');
$hist('round3', 'npm run build / lint re-run fresh', 'assertions about a run the reviewer took, not about the tree as it stands');

// PHP's exec() runs through cmd.exe on Windows, where `2>/dev/null` is a path that does not exist and
// the whole command fails before npm is reached.
$redirect = PHP_OS_FAMILY === 'Windows' ? '2>nul' : '2>/dev/null';
exec('npm audit --json '.$redirect, $out, $code);
$json = json_decode(implode("\n", $out), true);
if (! is_array($json)) {
    $hist('round3', 'npm advisory: qs', 'npm audit produced no parseable JSON (exit '.(int) $code.') — a registry fact this run could not reach');
} else {
    $vulnerable = count($json['vulnerabilities'] ?? []);
    $namesQs = isset($json['vulnerabilities']['qs']);
    $hist('round3', 'npm advisory: qs', 'the report says one moderate in qs; npm audit today reports '.$vulnerable.' vulnerable package(s), qs '.($namesQs ? 'among them' : 'no longer listed').
        ' — a registry fact, not a repo one, so it is printed not judged');
}

printf("\n  %d claim(s) seen, %d resolved, %d mismatch(es).\n", $seen, $resolved, $problems);
echo "  UNBOUND lines mean the checker could not find its claim in the document — that is a broken detector, not a clean run.\n";

if ($seen !== $resolved) {
    printf("  FAIL %d claim(s) were seen but not resolved — refusing to report a clean run.\n", $seen - $resolved);
    $problems++;
}

exit($problems === 0 ? 0 : 1);
