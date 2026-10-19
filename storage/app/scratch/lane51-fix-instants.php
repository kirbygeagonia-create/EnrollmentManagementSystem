<?php

// Lane 51: an institutional instant is read at the school, so render it in the school's zone instead of the
// browser's. Same discipline as lane 50's fixer: every find-string must match exactly once (or the stated
// count), the whole run is dry by default, and the import line is rewritten from the names the file now uses.
//
//   php storage/app/scratch/lane51-fix-instants.php --dry
//   php storage/app/scratch/lane51-fix-instants.php

$root = dirname(__DIR__, 3);
$dry = in_array('--dry', array_slice($argv, 1), true);

$edits = [
    ['resources/js/Components/ui/WorkflowStepper.jsx',
        "new Date(meta.signedDate).toLocaleDateString('en-PH')",
        'formatSchoolDay(meta.signedDate)', 1],

    ['resources/js/Layouts/AuthenticatedLayout.jsx',
        "new Date(n.created_at).toLocaleString('en-PH')",
        'formatSchoolMoment(n.created_at)', 1],

    ['resources/js/Pages/Accounting/DailyReport.jsx',
        "new Date(row.refundedAt).toLocaleString('en-PH', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' })",
        "formatSchoolMoment(row.refundedAt, { hour: '2-digit', minute: '2-digit', day: '2-digit', month: 'short' })", 1],

    ['resources/js/Pages/Accounting/Show.jsx',
        "new Date(row.refundedAt).toLocaleDateString('en-PH')",
        'formatSchoolDay(row.refundedAt)', 1],

    ['resources/js/Pages/Admin/UserManagement/AuditLogs.jsx',
        "new Date(row.createdAt).toLocaleString('en-PH', { dateStyle: 'short', timeStyle: 'medium' })",
        "formatSchoolMoment(row.createdAt, { dateStyle: 'short', timeStyle: 'medium' })", 1],
    ['resources/js/Pages/Admin/UserManagement/AuditLogs.jsx',
        "new Date(viewingLog.createdAt).toLocaleString('en-PH', { dateStyle: 'medium', timeStyle: 'medium' })",
        "formatSchoolMoment(viewingLog.createdAt, { dateStyle: 'medium', timeStyle: 'medium' })", 1],

    ['resources/js/Pages/Admission/Index.jsx',
        "new Date(row.created_at).toLocaleDateString('en-PH')",
        'formatSchoolDay(row.created_at)', 1],

    ['resources/js/Pages/Clearance/PrintSlip.jsx',
        "new Date(clearance.receivedDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })",
        "formatSchoolDay(clearance.receivedDate, { year: 'numeric', month: 'long', day: 'numeric' })", 1],

    ['resources/js/Pages/Evaluation/Show.jsx',
        "new Date(passSlipConfirmedAt).toLocaleDateString('en-PH')",
        'formatSchoolDay(passSlipConfirmedAt)', 2],

    ['resources/js/Pages/Students/Show.jsx',
        "new Date(sc.receivedDate).toLocaleDateString('en-PH')",
        'formatSchoolDay(sc.receivedDate)', 1],

    // ID/Show's local formatDate read the viewer's zone; the one cell left on it is a real instant.
    ['resources/js/Pages/ID/Show.jsx',
        '{formatDate(idRequest.validatedDate)}',
        '{formatSchoolDay(idRequest.validatedDate)}', 1],
];

$byFile = [];

foreach ($edits as [$rel, $find, $replace, $expect]) {
    $byFile[$rel][] = [$find, $replace, $expect];
}

$changed = 0;

foreach ($byFile as $rel => $swaps) {
    $path = $root.'/'.$rel;
    $text = file_get_contents($path);
    $before = $text;

    foreach ($swaps as [$find, $replace, $expect]) {
        $n = substr_count($text, $find);

        if ($n !== $expect) {
            fwrite(STDERR, sprintf("REFUSE: %s — the find string matched %d times, expected %d:\n  %s\n", $rel, $n, $expect, mb_substr($find, 0, 120)));
            exit(1);
        }

        $text = str_replace($find, $replace, $text);
        printf("  %-3s %s  %d×  %s\n", $dry ? 'dry' : 'ok', $rel, $expect, mb_substr($find, 0, 88));
    }

    // The names this file now needs, in one import statement.
    $names = [];

    foreach (['formatCalendarDay', 'calendarYear', 'formatSchoolDay', 'formatSchoolMoment'] as $candidate) {
        if (str_contains($text, $candidate.'(')) {
            $names[] = $candidate;
        }
    }

    if ($names === []) {
        continue;
    }

    sort($names);
    $statement = 'import { '.implode(', ', $names)." } from '@/Components/ui/calendarDay';";

    if (preg_match("/^import \{[^}]*\} from '\@\/Components\/ui\/calendarDay';\$/m", $text, $old)) {
        if ($old[0] !== $statement) {
            $text = str_replace($old[0], $statement, $text);
            printf("  %-3s %s  import %s\n", $dry ? 'dry' : 'ok', $rel, implode(', ', $names));
        }
    } else {
        preg_match_all('/^import[^\n]*$/m', $text, $imports);

        if ($imports[0] === []) {
            fwrite(STDERR, "REFUSE: $rel has no import to anchor the formatter on\n");
            exit(1);
        }

        $last = $imports[0][count($imports[0]) - 1];

        if (substr_count($text, $last) !== 1) {
            fwrite(STDERR, "REFUSE: the import anchor in $rel is not unique\n");
            exit(1);
        }

        $text = str_replace($last, $last."\n".$statement, $text);
        printf("  %-3s %s  import %s\n", $dry ? 'dry' : 'ok', $rel, implode(', ', $names));
    }

    // ID/Show's helper is now dead: every cell it served goes through the shared formatters.
    if ($rel === 'resources/js/Pages/ID/Show.jsx' && ! preg_match('/formatDate\(/', str_replace("const formatDate = (dateStr) => {", '', $text))) {
        $dead = <<<'TXT'
        const formatDate = (dateStr) => {
            if (!dateStr) return '—';
            return new Date(dateStr).toLocaleDateString('en-PH');
        };

TXT;

        if (substr_count($text, $dead) === 1) {
            $text = str_replace($dead, '', $text);
            printf("  %-3s %s  removed the now-unused local formatDate\n", $dry ? 'dry' : 'ok', $rel);
        } else {
            printf("  note %s  local formatDate not matched for removal — check by hand\n", $rel);
        }
    }

    if ($text !== $before) {
        if (! $dry) {
            file_put_contents($path, $text);
        }

        $changed++;
    }
}

printf("\n%s %d file(s).\n", $dry ? 'would change' : 'changed', $changed);
