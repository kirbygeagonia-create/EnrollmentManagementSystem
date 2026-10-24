<?php
/**
 * Lane 62 probe (read-only): what is actually ON the paper?
 *
 * §28.6's row 5 says the clearance slip and the enrollment form "both print the schoolIdNumber they already hold,
 * verified by reprinting and reading the paper". Measured 2026-10-21 with pdftotext on the PDFs `ems:print-fidelity`
 * rendered minutes earlier from live `ems`: the certificate carries "Student ID: DEMO-2026-017" and the enrollment
 * form carries that string ZERO times. So a claim whose stated evidence is "we read the paper" was written without
 * reading it — and nothing downstream can see that, because the citation lands on a live blade line, the
 * §-reference resolves, and every figure beside it is correct.
 *
 * The record is read OUT OF THE PAPER, not guessed: `ems:print-fidelity` chooses whom to render, so a probe that
 * asked the database for "a DEMO student" would test one student against another student's PDFs. The certificate
 * prints its own subject's School ID, so that token selects the record whose program and year level are then
 * looked for in every document.
 *
 * Usage: php storage/app/scratch/lane62-print-paper-probe.php [--dir=storage/app/prints/fidelity]
 *        PDFTOTEXT=<windows path to pdftotext.exe> when it is not at the default.
 */

$root = dirname(__DIR__, 3);
chdir($root);
$dir = 'storage/app/prints/fidelity';
foreach ($argv as $a) {
    if (str_starts_with((string) $a, '--dir=')) {
        $dir = substr((string) $a, 6);
    }
}
// exec() here runs cmd.exe, which cannot resolve a Git-Bash path like /mingw64/bin/pdftotext.
$pdftotext = getenv('PDFTOTEXT') ?: 'C:\Users\ADMIN\acli\Git\mingw64\bin\pdftotext.exe';
if (! is_file($pdftotext)) {
    printf("REFUSED: no pdftotext at %s. Pass PDFTOTEXT=<path to pdftotext.exe>.\n", $pdftotext);
    exit(1);
}
$files = glob($dir.'/*.pdf') ?: [];
if ($files === []) {
    printf("REFUSED: %s holds no PDF — an empty set is not a clean verdict. Run php artisan ems:print-fidelity.\n", $dir);
    exit(1);
}

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$texts = [];
foreach ($files as $f) {
    $tmp = tempnam(sys_get_temp_dir(), 'ems-pdf-').'.txt';
    exec(escapeshellarg($pdftotext).' -layout '.escapeshellarg($f).' '.escapeshellarg($tmp).' 2>&1');
    $body = is_file($tmp) ? (string) file_get_contents($tmp) : '';
    @unlink($tmp);
    $texts[basename($f)] = $body;
}
$readable = array_filter($texts, static fn (string $t): bool => trim($t) !== '');
if ($readable === []) {
    exit("REFUSED: not one PDF yielded extractable text, so this run measured nothing.\n");
}

// Whom did the command render? The certificate states it on its face. An ID must contain a digit:
// the slip's two-column layout puts "Student ID:" beside the program word, and a first version of this
// probe read "Bachelor" as the subject and had to refuse for want of such a student.
$order = array_merge(
    // array_union (+) keys by integer index, and both lists are 0-based, so it would silently drop
    // every document after the first — merge, do not union.
    array_values(array_filter(array_keys($texts), static fn (string $n): bool => str_contains($n, 'certificate'))),
    array_keys($texts)
);
$onPaper = null;
$seenWhere = '';
foreach ($order as $name) {
    if (preg_match('/Student ID:\s*([A-Z0-9\-]*\d[A-Z0-9\-]{2,})/i', $texts[$name], $m) === 1) {
        $onPaper = $m[1];
        $seenWhere = $name;
        break;
    }
}
if ($onPaper !== null) {
    printf("the paper names its own subject: %s prints Student ID %s\n", $seenWhere, $onPaper);
}
if ($onPaper === null) {
    printf("REFUSED: no rendered document states a School ID, so the probe cannot tell which record the paper is about.\n");
    exit(1);
}
$student = App\Models\Students::where('schoolIdNumber', $onPaper)->first();
if ($student === null) {
    printf("REFUSED: School ID %s is on the paper but not in this database — the set is from another box.\n", $onPaper);
    exit(1);
}
$enrollment = App\Models\Enrollments::where('studentId', $student->studentId)
    ->orderByDesc('enrollmentId')->first();
$program = (string) (optional($enrollment?->course)->courseName ?? '');
// The probe asks the same definition the papers use. It used to build this token by hand as
// 'Year '.$enrollment->yearLevel, which is a second spelling living in a scratch file — and the day the
// level became an ordinal it stopped matching anything, so the "year level" column read NO on all eight
// documents while every one of them printed the level. A blind detector prints the table a finding would.
$level = $enrollment === null ? '' : (string) App\Support\YearLevel::format((int) $enrollment->yearLevel);
printf("record behind it: student %d, enrollment %s, program %s, level %s\n\n",
    $student->studentId, (string) ($enrollment?->enrollmentId ?? '(none)'),
    $program !== '' ? $program : '(none)', $level !== '' ? $level : '(none)');

// Two different questions, and conflating them invents a defect: the fidelity set renders a DIFFERENT
// student per document (the slip is Marco Villanueva's, the certificate Santos's), so "this record's ID
// is absent" is not the same claim as "no School ID reaches this paper at all".
$terms = ['own ID' => $onPaper, 'program' => $program, 'year level' => $level];
printf("%-30s %-10s %-11s %-11s %-11s  %s\n", 'document', 'any ID', 'own ID', 'program', 'year level', 'period on the paper');
$allFlat = '';
foreach ($texts as $name => $body) {
    if (trim($body) === '') {
        printf("%-30s %s\n", $name, '(no extractable text — not evidence of absence)');
        continue;
    }
    $anyId = preg_match('/[A-Z]{3,}-\d{4}-\d{3}/', $body, $m) === 1 ? $m[0] : 'NO';
    // Two-column layouts wrap a value across lines ("Bachelor of Science in Criminology - 1st" / "Year"),
    // so the needle is searched in the text with its runs of whitespace folded. Without this the probe
    // reports absence wherever the paper merely broke a line.
    $flat = preg_replace('/\s+/u', ' ', $body);
    $allFlat .= $flat;
    $cells = [];
    foreach ($terms as $needle) {
        $cells[] = $needle !== '' && mb_stripos($flat, $needle) !== false ? 'yes' : 'NO';
    }
    $period = preg_match('/20\d\d[-\x{2013} ]20\d\d/u', $body, $mm) ? $mm[0] : '—';
    printf("%-30s %-10s %-11s %-11s %-11s  %s\n", $name, $anyId, $cells[0], $cells[1], $cells[2], $period);
}

// A needle the set never shows is this instrument's failure, not the school's: refuse rather than let a
// column of NOs be read as a finding about the paper.
$unseen = [];
foreach ($terms as $what => $needle) {
    if ($needle !== '' && mb_stripos($allFlat, $needle) === false) {
        $unseen[] = $what.' (“'.$needle.'”)';
    }
}
if ($unseen !== []) {
    printf("\nREFUSED: no rendered document contains %s, so this probe cannot say whether the paper carries it.\n"
        ."Ask the build where that value is written before reading any column of this table as a finding.\n",
        implode('; ', $unseen));
    exit(1);
}

echo "\n\"NO\" under own ID only means the document belongs to somebody else. \"NO\" under any ID means the paper\n"
    ."carries no student identity at all — that is the finding §28.6 and P-2 have to agree with.\n";
