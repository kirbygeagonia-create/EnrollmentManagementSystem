<?php

// Lane 50: route every calendar-day cell through the shared day formatter, and add the import each file needs.
// Every swap asserts exactly one match before writing, prints what it changed, and refuses the run otherwise —
// a bulk edit that silently missed is worse than no edit, because the checker would then read a clean tree.
//
//   php storage/app/scratch/lane50-fix-day-cells.php --dry
//   php storage/app/scratch/lane50-fix-day-cells.php

$root = dirname(__DIR__, 3);
$dry = in_array('--dry', array_slice($argv, 1), true);

/** [file, find, replace] */
$edits = [
    ['resources/js/Components/ui/calendarDay.js', null, null], // touched only to prove it exists

    ['resources/js/Pages/Accounting/DailyReport.jsx',
        "const formattedDate = date ? new Date(date).toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '—';",
        'const formattedDate = formatCalendarDay(date, { weekday: \'long\', year: \'numeric\', month: \'long\', day: \'numeric\' });'],
    ['resources/js/Pages/Accounting/DailyReport.jsx',
        "{ key: 'paymentDate', label: 'Time', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }) : '—' },",
        "{ key: 'paymentDate', label: 'Paid on', render: (row) => formatCalendarDay(row.paymentDate) },"],

    ['resources/js/Pages/Accounting/Index.jsx',
        "{ key: 'createdAt', label: 'Assessed Date', render: (row) => row.assessmentDate ? new Date(row.assessmentDate).toLocaleDateString('en-PH') : '—' },",
        "{ key: 'createdAt', label: 'Assessed Date', render: (row) => formatCalendarDay(row.assessmentDate) },"],

    ['resources/js/Pages/Accounting/Show.jsx',
        "{ key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },",
        '{ key: \'paymentDate\', label: \'Paid on\', render: (row) => formatCalendarDay(row.paymentDate) },'],

    ['resources/js/Pages/Admission/Show.jsx',
        '{student?.birthdate ? new Date(student.birthdate).toLocaleDateString(\'en-PH\') : \'—\'}',
        '{formatCalendarDay(student?.birthdate)}'],

    ['resources/js/Pages/Assessment/Show.jsx',
        "{ key: 'paymentDate', label: 'Date', render: (row) => row.paymentDate ? new Date(row.paymentDate).toLocaleDateString('en-PH') : '—' },",
        "{ key: 'paymentDate', label: 'Paid on', render: (row) => formatCalendarDay(row.paymentDate) },"],

    ['resources/js/Pages/Clearance/Periods.jsx',
        "const fmtDate = (d) => (d ? new Date(d).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }) : '—');",
        "const fmtDate = (d) => formatCalendarDay(d, { year: 'numeric', month: 'short', day: '2-digit' });"],

    ['resources/js/Pages/Clinic/Show.jsx',
        "        return new Date(dateStr).toLocaleDateString('en-PH');",
        '        return formatCalendarDay(dateStr);'],

    ['resources/js/Pages/Evaluation/Show.jsx',
        "{retentionExam.examDate ? new Date(retentionExam.examDate).toLocaleDateString('en-PH') : '—'}",
        '{formatCalendarDay(retentionExam.examDate)}'],
    ['resources/js/Pages/Evaluation/Show.jsx',
        '{curriculum.effectiveYear ? ` · AY ${new Date(curriculum.effectiveYear).getFullYear()}` : \'\'}',
        '{curriculum.effectiveYear ? ` · AY ${calendarYear(curriculum.effectiveYear)}` : \'\'}'],

    ['resources/js/Pages/Exam/Index.jsx',
        "{ key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },",
        "{ key: 'examDate', label: 'Date', render: (row) => formatCalendarDay(row.examDate) },"],

    ['resources/js/Pages/Exam/Results.jsx',
        "{ key: 'examDate', label: 'Date', render: (row) => row.examDate ? new Date(row.examDate).toLocaleDateString('en-PH') : '—' },",
        "{ key: 'examDate', label: 'Date', render: (row) => formatCalendarDay(row.examDate) },"],

    ['resources/js/Pages/ID/Show.jsx',
        'value={formatDate(idRequest?.requestDate)}',
        'value={formatCalendarDay(idRequest?.requestDate)}'],
    ['resources/js/Pages/ID/Show.jsx',
        '<span className="font-bold text-slate-800">{formatDate(idRequest.requestDate)}</span>',
        '<span className="font-bold text-slate-800">{formatCalendarDay(idRequest.requestDate)}</span>'],

    ['resources/js/Pages/Registrar/PrintCertificate.jsx',
        "        ? new Date(enrollment.enrolledDate).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })",
        "        ? formatCalendarDay(enrollment.enrolledDate, { year: 'numeric', month: 'long', day: 'numeric' })"],

    ['resources/js/Pages/Registrar/Show.jsx',
        "{enrollment.enrolledDate ? new Date(enrollment.enrolledDate).toLocaleDateString('en-PH') : 'Recent'}",
        "{enrollment.enrolledDate ? formatCalendarDay(enrollment.enrolledDate) : 'Recent'}"],

    ['resources/js/Pages/Students/Show.jsx',
        '{student.birthdate ? new Date(student.birthdate).toLocaleDateString(\'en-PH\') : \'—\'}',
        '{student.birthdate ? formatCalendarDay(student.birthdate) : \'—\'}'],
    ['resources/js/Pages/Students/Show.jsx',
        '{ad.submittedDate ? new Date(ad.submittedDate).toLocaleDateString(\'en-PH\') : \'\'}',
        '{ad.submittedDate ? formatCalendarDay(ad.submittedDate) : \'\'}'],
    ['resources/js/Pages/Students/Show.jsx',
        '{er.examDate ? new Date(er.examDate).toLocaleDateString(\'en-PH\') : \'\'}',
        '{er.examDate ? formatCalendarDay(er.examDate) : \'\'}'],
    ['resources/js/Pages/Students/Show.jsx',
        '{id.requestDate ? new Date(id.requestDate).toLocaleDateString(\'en-PH\') : \'—\'}',
        '{id.requestDate ? formatCalendarDay(id.requestDate) : \'—\'}'],
    ['resources/js/Pages/Students/Show.jsx',
        '{cr.assessmentDate ? new Date(cr.assessmentDate).toLocaleDateString(\'en-PH\') : \'—\'}',
        '{cr.assessmentDate ? formatCalendarDay(cr.assessmentDate) : \'—\'}'],

    ['resources/js/Pages/Admin/ReferenceData/Terms.jsx',
        "{ key: 'startDate', label: 'Start Date', render: (row) => row.startDate ? new Date(row.startDate).toLocaleDateString('en-PH') : '—', className: 'text-center' },",
        "{ key: 'startDate', label: 'Start Date', render: (row) => formatCalendarDay(row.startDate), className: 'text-center' },"],
    ['resources/js/Pages/Admin/ReferenceData/Terms.jsx',
        "{ key: 'endDate', label: 'End Date', render: (row) => row.endDate ? new Date(row.endDate).toLocaleDateString('en-PH') : '—', className: 'text-center' },",
        "{ key: 'endDate', label: 'End Date', render: (row) => formatCalendarDay(row.endDate), className: 'text-center' },"],

    ['resources/js/Pages/Admin/ReferenceData/Curriculums.jsx',
        "{ key: 'effectiveYear', label: 'Effective Year', render: (row) => row.effectiveYear ? new Date(row.effectiveYear).getFullYear() : '—' },",
        "{ key: 'effectiveYear', label: 'Effective Year', render: (row) => calendarYear(row.effectiveYear) ?? '—' },"],
];

$byFile = [];

foreach ($edits as [$rel, $find, $replace]) {
    if ($find === null) {
        if (! is_file($root.'/'.$rel)) {
            fwrite(STDERR, "REFUSE: the shared formatter is missing — $rel\n");
            exit(1);
        }

        continue;
    }

    $byFile[$rel][] = [$find, $replace];
}

$changed = 0;

foreach ($byFile as $rel => $swaps) {
    $path = $root.'/'.$rel;
    $text = file_get_contents($path);
    $before = $text;

    foreach ($swaps as [$find, $replace]) {
        $n = substr_count($text, $find);

        if ($n !== 1) {
            fwrite(STDERR, sprintf("REFUSE: %s — the find string matched %d times, expected exactly 1:\n  %s\n", $rel, $n, mb_substr($find, 0, 120)));
            exit(1);
        }

        $text = str_replace($find, $replace, $text);
        printf("  %-3s %s  %s\n", $dry ? 'dry' : 'ok', $rel, mb_substr(trim($find), 0, 96));
    }

    // One import, carrying exactly the names the file now uses. The repo addresses components through the
    // `@/` alias, and a separate statement beats widening an existing '@/Components/ui' import.
    $names = [];

    if (str_contains($text, 'formatCalendarDay(')) {
        $names[] = 'formatCalendarDay';
    }

    if (str_contains($text, 'calendarYear(')) {
        $names[] = 'calendarYear';
    }

    if ($names !== [] && ! str_contains($text, '@/Components/ui/calendarDay')) {
        $statement = 'import { '.implode(', ', $names)." } from '@/Components/ui/calendarDay';";
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

    if ($text !== $before) {
        if (! $dry) {
            file_put_contents($path, $text);
        }

        $changed++;
    }
}

printf("\n%s %d file(s).\n", $dry ? 'would change' : 'changed', $changed);
