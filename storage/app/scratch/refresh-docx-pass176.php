<?php

/**
 * Pass 176 — P-7 closes on the paper, and the sheet that supplied it opens P-16.
 *
 * Measured 2026-10-21 before a word was written:
 *  - live `ems` settings holds 3 rows and none is blank: schoolName, the Tupi campus address, the Registrar landline.
 *  - the rendered certificate PDF (storage/app/prints/fidelity, printed from live `ems` by ems:print-fidelity) carries
 *    the address and the number, so the paper — not the query — is the evidence, which is lane 62's rule.
 *  - Documentation/SEAIT_Official_Information.md §1 has carried both since e35fe3d on 2026-08-28, while SettingsSeeder
 *    has seeded both blank and declared them unknowable since 29b5532 on 2026-10-01: twenty days, and the declaration
 *    was false the day it was committed.
 *  - the six print views read exactly three keys (schoolName, schoolAddress, schoolPhone) and nothing else, so a fourth
 *    key a template invented would print its own inline default in silence.
 *  - reading the sheet's own first line found the thing this repository disagrees with itself about: the paper prints
 *    "Southeast Asian Institute of Technology" while the sheet, this document's title line, GuestLayout.jsx:50 and :68
 *    and four Documentation markdown files carry "South East Asian Institute of Technology, Inc." That is P-16, and it
 *    is the school's to state, so the seeded name is NOT touched here.
 *
 * Two stages, the reason being pass 173's own defect: docxApply() re-reads the file, so a cloned row staged in a
 * string never reaches disk unless this file writes it. Stage 1 clones P-16, moves every place that counts the print
 * register, and removes the paragraph pass 173's row clone duplicated into two §1.1 revision rows — its $setText
 * replaces a cell's FIRST run, and a 25k-character narrative cell has more than one. Stage 2 goes through the engine
 * so its guards run.
 *
 * DRY RUN unless --apply.
 */

require __DIR__.'/docx-edit-engine.php';

$root = dirname(__DIR__, 3);
$src = $root.'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$fails = 0;
$guard = function (bool $ok, string $what) use (&$fails): void {
    printf("  %s %s\n", $ok ? 'ok  ' : 'FAIL', $what);
    if (! $ok) {
        $fails++;
    }
};
$assertPlain = function (string $text, string $what) use (&$fails): void {
    $bad = [];
    foreach (['&', '<', '>'] as $c) {
        if (str_contains($text, $c)) {
            $bad[] = $c;
        }
    }
    if ($bad !== []) {
        printf("  FAIL %s carries %s — it would be escaped twice or corrupt the part\n", $what, implode(' ', $bad));
        $fails++;
    }
};
/**
 * The whole cell reads the new text and nothing else. Pass 173's version replaced only the first <w:t> and left any
 * later run in place, which is how a cloned revision row inherited its template's closing paragraph. Here every
 * other run is emptied, and the result is verified by equality rather than by containment.
 */
$setCellText = function (string $cell, string $text) use ($guard): string {
    $encoded = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $first = true;
    $out = preg_replace_callback('/<w:t(?:\s[^>]*)?>.*?<\/w:t>/s', function (array $x) use (&$first, $encoded, $text): string {
        if ($first) {
            $first = false;

            return preg_replace('/>.*?<\/w:t>/s', '>'.$encoded.'</w:t>', $x[0], 1);
        }

        return preg_replace('/>.*?<\/w:t>/s', '></w:t>', $x[0], 1);
    }, $cell);

    preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $out, $t);
    $flat = html_entity_decode(implode('', $t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $guard($flat === $text, 'the written cell reads exactly the pass’s own text, not merely contains it');
    $guard(str_contains($out, $encoded), 'the cell carries the encoded text');

    return $out;
};

// ---------------------------------------------------------------- the four measurements the prose rests on

$address = 'National Highway, Purok 7, Crossing Rubber, Tupi, South Cotabato, Philippines, 9505';
$phone = '(083) 226-1602';
$name = 'Southeast Asian Institute of Technology';

$sheet = (string) file_get_contents($root.'/Documentation/SEAIT_Official_Information.md');
$guard(str_contains($sheet, $address), 'the campus address is in SEAIT_Official_Information.md §1');
$guard(str_contains($sheet, $phone), 'the Registrar landline is in SEAIT_Official_Information.md §1');
$guard(str_contains($sheet, 'South East Asian Institute of Technology, Inc.'), 'the sheet spells the name the two-word way');
$guard(! str_contains($sheet, $name), 'and the one-word spelling the paper prints is not what the sheet says');

$views = glob($root.'/resources/views/prints/*.blade.php') ?: [];
$guard(count($views) === 6, 'six print views exist (read '.count($views).')');
$missing = [];
foreach ($views as $f) {
    foreach (['schoolName', 'schoolAddress', 'schoolPhone'] as $k) {
        if (! str_contains((string) file_get_contents($f), "config('settings.".$k)) {
            $missing[] = basename($f).' → '.$k;
        }
    }
}
$guard($missing === [], $missing === []
    ? 'all six print views read all three letterhead keys'
    : 'views that do not read a key this pass names: '.implode(', ', $missing));

$test = (string) file_get_contents($root.'/tests/Feature/Print/PrintTemplateSelfContainmentTest.php');
$guard(str_contains($test, 'test_the_letterhead_settings_are_seeded_from_the_school_sheet'),
    'the guard this pass cites exists in the suite');
$guard(str_contains($test, 'test_the_print_views_read_only_settings_keys_the_seeder_supplies'),
    'and so does the dead-key half');

// The prose below says what the PAPER shows, so the paper is read before the sentence is written — lane 62's rule.
$pdftotext = getenv('PDFTOTEXT') ?: 'C:\Users\ADMIN\acli\Git\mingw64\bin\pdftotext.exe';
$pdf = $root.'/storage/app/prints/fidelity/enrollment-certificate.pdf';
if (! is_file($pdf) || ! is_file($pdftotext)) {
    printf("REFUSED: this pass would claim what the letterhead prints, and it cannot read the paper (pdf %s, pdftotext %s).\n"
        ."Run php artisan ems:print-fidelity, or pass PDFTOTEXT=<path to pdftotext.exe>.\n",
        is_file($pdf) ? 'present' : 'absent', is_file($pdftotext) ? 'present' : 'absent');
    exit(1);
}
$txt = tempnam(sys_get_temp_dir(), 'p176-').'.txt';
exec(escapeshellarg($pdftotext).' -layout '.escapeshellarg($pdf).' '.escapeshellarg($txt).' 2>&1');
$body = is_file($txt) ? (string) file_get_contents($txt) : '';
@unlink($txt);
$guard(trim($body) !== '', 'the rendered certificate yielded text ('.strlen($body).' bytes)');
$guard(str_contains($body, $address), 'the paper prints the campus address the seeder now supplies');
$guard(str_contains($body, $phone), 'the paper prints the Registrar landline');
$guard(str_contains($body, 'SOUTHEAST ASIAN INSTITUTE OF TECHNOLOGY'),
    'and the paper prints the one-word name — which is why P-16 is opened, not closed');
$guard(! str_contains($body, 'SOUTH EAST ASIAN'), 'the sheet’s two-word spelling does not reach this paper');

require_once $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$live = App\Models\Settings::orderBy('settingKey')->pluck('settingValue', 'settingKey')->all();
$guard(array_keys($live) === ['schoolAddress', 'schoolName', 'schoolPhone'],
    'live `ems` holds exactly the three letterhead keys (read '.implode(', ', array_keys($live)).')');
foreach (['schoolAddress' => $address, 'schoolPhone' => $phone, 'schoolName' => $name] as $k => $want) {
    $guard(trim((string) ($live[$k] ?? '')) !== '', 'settings.'.$k.' is not blank on live `ems`');
    $guard(($live[$k] ?? null) === $want, 'settings.'.$k.' holds the value this pass names');
}

// ---------------------------------------------------------------- stage 1: clone the row, move the counts, un-duplicate §1.1

$zip = new ZipArchive();
if ($zip->open($src) !== true) {
    exit("REFUSED: cannot open the docx.\n");
}
$parts = $zip->numFiles;
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();
$new = $xml;

// 1. the duplicated lane-57 paragraph: byte-for-byte identical in two revision rows, and it belongs to 2026-10-16–20.
$dupe = 'Lane 57 then asked the drawer a question the register had never put';
$guard(substr_count($xml, $dupe) === 2, 'the lane-57 paragraph is found exactly twice (a duplicate, not a summary)');
$at1 = strpos($xml, $dupe);
$at2 = $at1 === false ? false : strpos($xml, $dupe, $at1 + 1);
$row21 = strpos($xml, '2026-10-21 (this issue)');
$guard($at2 !== false && $row21 !== false && $at2 > $row21 && $at2 < (int) strpos($xml, '</w:tr>', (int) $row21),
    'the second copy sits inside the 2026-10-21 revision row');
if ($at2 !== false) {
    // '<w:r' alone would land on the run's own <w:rPr>; this document opens every run as exactly "<w:r>".
    $runOpen = (int) strrpos(substr($xml, 0, (int) $at2), '<w:r>');
    $runClose = (int) strpos($xml, '</w:r>', (int) $at2) + 6;
    $theRun = substr($xml, $runOpen, $runClose - $runOpen);
    $guard(substr_count($xml, $theRun) === 2, 'the whole run to delete occurs exactly twice (so deleting one leaves one)');
    $guard(substr_count($theRun, '<w:r>') === 1 && substr_count($theRun, '</w:r>') === 1, 'the span is exactly one run');
    $guard(substr_count($theRun, $dupe) === 1, 'and that run is the paragraph this pass is removing');
    $before = substr_count($new, $dupe);
    $new = substr_replace($new, '', $runOpen, $runClose - $runOpen);
    $guard(substr_count($new, $dupe) === $before - 1, 'exactly one copy of the paragraph is left in the document');
    $guard(substr_count($new, '<w:r>') === substr_count($xml, '<w:r>') - 1, 'one run element removed, no other container moved');
    $guard(substr_count($new, '</w:tr>') === substr_count($xml, '</w:tr>'), 'no row was lost');
}

// 2. every place that states the print register's range.
$guard(substr_count($new, 'P-1 … P-15') === 4, 'four sentences name the print register as P-1 … P-15');
$new = str_replace('P-1 … P-15', 'P-1 … P-16', $new, $cRange);
$guard($cRange === 4, 'all four ranges now reach P-16');
$guard(substr_count($new, 'P-1 … P-15') === 0, 'no range still stops at P-15');

// 3. §29.4's family count. Three places say "Printed-document findings" (§28.4's index row, §28.4's own heading and
// §29.4's family row), and 15 is a digit other cells hold, so the row is chosen by the count cell it carries.
$want = null;
$off = 0;
while (($at = strpos($new, 'Printed-document findings', $off)) !== false) {
    $rs = (int) strrpos(substr($new, 0, (int) $at), '<w:tr');
    $re = (int) strpos($new, '</w:tr>', (int) $at) + 7;
    if (substr_count(substr($new, $rs, $re - $rs), '>15<') === 1) {
        $guard($want === null, 'at most one row carries a 15 cell, so the count is addressable');
        $want = [$rs, $re];
    }
    $off = $at + 1;
}
$guard($want !== null, 'the §29.4 family row holding the print register’s count was found');
if ($want !== null) {
    $fam = substr($new, $want[0], $want[1] - $want[0]);
    $guard(str_contains($fam, 'P-1 … P-16'), 'that row is the one whose range this pass just moved');
    $new = substr_replace($new, str_replace('>15<', '>16<', $fam), $want[0], $want[1] - $want[0]);
    $guard(substr_count($new, '>15<') === 3, 'the count moved and the other three 15 cells are untouched');
}

// 4. clone P-15's row into P-16.
$p16 = [
    'P-16',
    'Open, and the school owns the answer, not the code. P-7 closed by putting a value in every letterhead row, and the '
        .'name row is the one this repository disagrees with itself about: the settings table seeds and the six print '
        .'views fall back to “Southeast Asian Institute of Technology”, while SEAIT_Official_Information.md §1 records '
        .'the Official Name as “South East Asian Institute of Technology, Inc.” — a space inside the word and the '
        .'corporate suffix. Measured on the rendered certificate 2026-10-21: the paper carries the one-word form, while '
        .'the login screen carries the sheet’s, so the screen and the document name the school differently.',
    'SettingsSeeder.php:28 seeds the one-word form and each of the six print views passes it as its own fallback '
        .'(clearance-slip.blade.php:31); PrintLayout.jsx:36 and PrintCertificate.jsx:74 hard-code it; '
        .'GuestLayout.jsx:50 and :68, this document’s own title line and four Documentation markdown files use the '
        .'sheet’s spelling. No test pins either: '
        .'tests/Feature/Print/PrintTemplateSelfContainmentTest.php requires the value to be present and the address and '
        .'phone to be traceable to the sheet, and deliberately does not assert the name, because the name is what is in '
        .'question.',
    'Nothing printed is misaddressed — the seal, the logo, the street and the telephone all belong to one institution — '
        .'but every document the school issues spells its name other than its own record in this repository does. '
        .'Nothing in code decides it: the line comes from a settings row, so a ruling is one field on Admin → User '
        .'Management → Settings. The seeder’s literal and the six fallbacks must then be changed to match, because a '
        .'fresh install prints the seeder and not the edit. The scans that could settle it are the school’s paper, and '
        .'Documentation/Images is gitignored and absent from this box, so this is recorded as a question rather than '
        .'answered with a guess.',
    'Data + institutional decision',
];
$at = strpos($new, '>P-15<');
$guard($at !== false, 'P-15’s row is findable as the clone template');
$rowStart = $at === false ? 0 : (int) strrpos(substr($new, 0, (int) $at), '<w:tr');
$rowEnd = (int) strpos($new, '</w:tr>', (int) $at) + 7;
$template = substr($new, $rowStart, $rowEnd - $rowStart);
preg_match_all('/<w:tc>.*?<\/w:tc>/s', $template, $cm);
$guard(count($cm[0]) === 5, 'the template row is a five-cell §25.4 row ('.count($cm[0]).')');
$clone = $template;
foreach ($cm[0] as $i => $cell) {
    $assertPlain($p16[$i], 'P-16 cell '.($i + 1));
    $clone = str_replace($cell, $setCellText($cell, $p16[$i]), $clone);
}
foreach (['<w:tr', '</w:tr>', '<w:tc>', '</w:p>', '<w:r', '</w:r>'] as $n) {
    $guard(substr_count($clone, $n) === substr_count($template, $n), 'P-16 container '.$n.': one row’s worth');
}
$guard(substr_count($clone, '>P-16<') === 1, 'P-16 is addressed by its own id cell');
$new = substr_replace($new, $clone, $rowEnd, 0);

// 5. recount the print register from the edited bytes. §25.4 is written as two adjacent <w:tbl> elements (P-1 … P-8,
// then P-9 onward — Word merges consecutive tables, so it reads as one), which is why the ids are counted by their own
// cells across the span rather than by one table's row list.
$from = (int) strpos($new, '>P-1<');
$p16At = (int) strpos($new, '>P-16<');
$rowEndAfter = (int) strpos($new, '</w:tr>', $p16At) + 7;
$span = substr($new, $from, $rowEndAfter - $from);
preg_match_all('/>P-(\d+)</', $span, $ids);
$expect = array_map('strval', range(1, 16));
$guard($ids[1] === $expect, 'the print register runs P-1 … P-16 in order, once each (read '.implode(' ', $ids[1]).')');
$guard(substr_count($new, '>P-16<') === 1, 'P-16 exists exactly once');
$guard(substr_count($new, '>16<') === substr_count($xml, '>16<') + 1,
    'one 16 cell was added — §29.4’s count; P-16’s id cell reads >P-16<, not >16<');
$guard(substr_count($new, '<w:tbl>') === substr_count($xml, '<w:tbl>')
    && substr_count($new, '</w:tbl>') === substr_count($xml, '</w:tbl>'), 'no table element was added or lost');
$guard(@(new DOMDocument)->loadXML($new) !== false, 'the staged document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed in stage 1 — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

// ---------------------------------------------------------------- stage 2: the prose, through the engine

$textEdits = [
    [
        'Key-value system settings (currentTermId, schoolName, schoolAddress, schoolPhone, clearanceReplacementFee).',
        'Key-value system settings. The table holds three rows — schoolName, schoolAddress and schoolPhone — and they '
            .'are the only keys any print template reads: currentTermId was never seeded, and clearanceReplacementFee '
            .'was dropped by 2026_10_03_1000000004, so the key list this line carried from the previous edition named '
            .'two rows that do not exist (P-12).',
    ],
    [
        'and the address and phone print empty because those two rows are blank, not because the lookup missed.',
        'and the two rows beside it answer with the campus address and the Registrar’s published landline, so no line '
            .'of the letterhead falls through to the default typed into the call.',
    ],
    [
        'and schoolAddress and schoolPhone are seeded blank, each with its own description naming the Registrar as the '
            .'office that owes it.',
        'schoolAddress the Tupi campus street address and schoolPhone the Registrar landline, each row’s own description '
            .'naming SEAIT_Official_Information.md §1 as where it was read from.',
    ],
    [
        'So the letterhead prints the maintained name and prints nothing where the school has not supplied a value',
        'So every line of the letterhead prints what the table holds',
    ],
    [
        'requiring the row on disk to answer again.',
        'requiring the row on disk to answer again. The two rows that were blank were supplied on 2026-10-21 from this '
            .'repository’s own copy of the school’s contact sheet, which has been in the tree since e35fe3d on '
            .'2026-08-28 — before 29b5532 wrote the seeder on 2026-10-01, whose header declared the address and the '
            .'landline to be institutional facts this repository does not record. That declaration was false the day it '
            .'was committed and stood for twenty days; P-7 is closed on the strength of the paper, and '
            .'tests/Feature/Print/PrintTemplateSelfContainmentTest.php now refuses a seeder that hands the letterhead a '
            .'blank or an invented value, or a print view that reads a key no row supplies.',
    ],
    [
        'Two print findings sit outside this table because it counts only 28.2 and 28.3: P-7, whose letterhead still '
            .'prints no address and no phone, and P-2,',
        'Three print findings sit outside this table because it counts only 28.2 and 28.3: P-7, whose letterhead printed '
            .'no address and no phone until 2026-10-21; P-16, the spelling of the name those rows now print, which is '
            .'the school’s to rule; and P-2,',
    ],
    [
        'it is the two blank letterhead rows (P-7), the program and term the enrollment form cannot print (P-2), and the '
            .'proxy an operator must name before HSTS means anything (G-13).',
        'it is the program and term the enrollment form cannot print (P-2), the spelling of the name the letterhead '
            .'prints (P-16), and the proxy an operator must name before HSTS means anything (G-13). The two blank '
            .'letterhead rows this sentence named until today are not owed after all: SEAIT_Official_Information.md §1 '
            .'has carried both values since 2026-08-28 and the seeder now seeds them (P-7).',
    ],
    [
        'because an assertion total is a property of a run and a living sentence may not name two runs at once.',
        'because an assertion total is a property of a run and a living sentence may not name two runs at once. Lane 62 '
            .'then asked what the paper itself says, and extracting the text of the PDFs ems:print-fidelity renders '
            .'found §28.4’s row 5 claiming a reading that had never been done — the enrollment form carries no identity '
            .'token at all, which is P-2 stated with a verdict word at last. P-7 closed the same day, and not because '
            .'the Registrar answered: SEAIT_Official_Information.md §1 has carried the campus address and the landline '
            .'since 2026-08-28, while SettingsSeeder seeded both blank and declared the repository did not record them '
            .'from 2026-10-01. Reading that sheet for the two values found the one this repository cannot settle '
            .'internally — the paper prints Southeast Asian Institute of Technology and the sheet, the login screen and '
            .'this document’s own title print South East Asian Institute of Technology, Inc. — and that question is '
            .'P-16, recorded as a question because the school’s paper, not a developer’s preference, decides a name.',
    ],
];

$cellEdits = [
    // P-7's row: the finding, the evidence, and what the paper owes now.
    ['>P-7<', 'Partly closed, and the remainder is narrower',
        'Closed 2026-10-21, on the paper. The letterhead image is no longer a path to a file that does not exist: it is '
            .'embedded as a data URI, which also removed the round trip into the dev server that once deadlocked the '
            .'single-threaded print. Nor is the school name a code default: SettingsServiceProvider.php:20-24 hydrates '
            .'config(‘settings.*’) from the settings table at boot, so what an admin edits does reach the printed '
            .'document, and StaffAccountManagementTest.php:509-514 pins it. The last two blanks were the Registrar’s to '
            .'supply — except that they were never missing from this repository: SEAIT_Official_Information.md §1 has '
            .'carried the campus address and the Registrar’s landline since e35fe3d on 2026-08-28, and SettingsSeeder '
            .'has seeded them empty and declared them unknowable since 29b5532 on 2026-10-01. Measured on the rendered '
            .'certificate: the letterhead now prints the name, the address and the number.'],
    ['>P-7<', 'clearance-slip.blade.php:30 (PrintAssets::logoDataUri',
        'clearance-slip.blade.php:30 (PrintAssets::logoDataUri, with onerror hiding) and :31 (config(…schoolName, '
            .'default)); SettingsSeeder.php:27-38 seeds the three letterhead rows; SEAIT_Official_Information.md §1 '
            .'names them Main Campus Address and Registrar Landline; '
            .'tests/Feature/Print/PrintTemplateSelfContainmentTest.php pins both the values and the key list.'],
    ['>P-7<', 'Open, and it is a data question with its owner',
        'Nothing outstanding for the mechanism or for the values. Admin → User Management → Settings still writes all '
            .'three (UserManagementController.php:471-491) under the settings.manage right, which live `ems` grants to '
            .'one role, SysAdmin, seated on one account, and the field validates required, so a value once supplied '
            .'cannot be blanked again from the screen. The two new tests refuse the two ways this row could break again: '
            .'a letterhead value seeded blank, and a print view reading a key no row supplies. What the same sheet '
            .'raises and this row does not settle is the spelling of the school’s name — P-16.'],
    // §28.4's two group rows.
    ['A one-line change to a template or page', 'P-1, P-5, P-6 and P-11 were closed',
        'All five are closed. P-1, P-5, P-6 and P-11 were closed 2026-10-02: the field was already in the data the print '
            .'receives, and closing meant rendering it. P-7 closed 2026-10-21, when the two blank letterhead rows were '
            .'supplied from the school’s own contact sheet and read back off the rendered certificate.'],
    ['A decision before any code', 'P-12, P-4, P-9', 'P-12, P-4, P-9, P-16'],
    ['A decision before any code', 'All three are settled.',
        'Three are settled: the fee is Reference Data’s alone (ruling 8), office 8 is retired in data (ruling 12), and '
            .'the subject load has its own template, so the act this row waited for was taken and built. The fourth is '
            .'open and is not a code decision at all: which spelling of the institution’s name the paper carries '
            .'(P-16).'],
    // G-29's record, so the row that justified the correction does not itself go stale.
    ['>G-29<', 'Corrected 2026-10-21 by passes 171',
        'Corrected 2026-10-21 by passes 171, 171b, 172 and 173: §25.5 states the provider, the six templates and the '
            .'two rows that were blank when it was written; §30’s census, unique-key and open-item figures follow '
            .'§23.6.1, §24.1 and the registers; §28.7 is written as the order the build took; §28.8 names the eight rows '
            .'that still owe an answer. Read by storage/app/scratch/lane61-open-claims.php and '
            .'storage/app/scratch/lane61-who-owes.php. Pass 176 supplied those two rows from '
            .'SEAIT_Official_Information.md §1, so P-7 is closed and the letterhead question that remains is the name '
            .'they print (P-16).'],
    // §30.5's row, keeping the figure lane 60 binds ("the table is populated — 3 rows, one of them the name").
    ['The settings table, with two rows left blank', 'The settings table, with two rows left blank',
        'The settings table, whose three rows all carry a value'],
    ['The settings table, with two rows left blank', 'Its two blank rows are why a letterhead prints',
        'It stays because the letterhead is the school’s rather than the template’s: the table is populated — 3 rows, '
            .'one of them the name — and a provider carries every row into config(‘settings.*’) at boot, so the paper '
            .'reads what an admin maintains. Two of those rows stood blank until 2026-10-21 and P-7 records where their '
            .'values were found; the spelling of the name is what the school still has to state, and P-16 carries it.'],
    ['The settings table, with two rows left blank', 'P-7 and P-12 in §25.4',
        'P-7, P-12 and P-16 in §25.4, §25.5'],
];

// ---------------------------------------------------------------- pre-flight: resolve stage 2 against the staged bytes
//
// The engine re-reads the file, so a needle that only resolves after stage 1 has been written must be proved against
// $new here — otherwise --apply writes stage 1, fails in stage 2, and leaves the document between two passes.

$preflight = function (string $hay, array $textEdits, array $cellEdits) use ($guard, $assertPlain): void {
    foreach ($textEdits as $i => $e) {
        $n = substr_count($hay, $e[0]);
        $guard($n === ($e[3] ?? 1), 'text '.$i.' resolves '.($e[3] ?? 1).' time in the staged bytes (read '.$n
            .') [' .mb_substr($e[0], 0, 40).']');
        $at = strpos($hay, $e[0]);
        $tOpen = $at === false ? false : strrpos(substr($hay, 0, (int) $at), '<w:t');
        $inner = $tOpen === false ? '' : substr($hay, (int) $tOpen, $at - (int) $tOpen);
        $guard($tOpen !== false && strpos($inner, '</w:t>') === false, 'text '.$i.' lives inside one run');
        $assertPlain($e[1], 'text '.$i.' replacement');
    }
    foreach ($cellEdits as $i => $e) {
        [$rowNeedle, $prefix, $to] = $e;
        $want = $e[3] ?? 1;
        $anchors = [];
        $scan = 0;
        while (($a = strpos($hay, $rowNeedle, $scan)) !== false) {
            $anchors[] = $a;
            $scan = $a + 1;
        }
        $guard(count($anchors) === $want, 'cell '.$i.' row anchor seen '.count($anchors).' time(s), wanted '.$want
            .' ['.mb_substr($rowNeedle, 0, 36).']');
        foreach ($anchors as $rp) {
            $rs = strrpos(substr($hay, 0, (int) $rp), '<w:tr');
            $re = (int) strpos($hay, '</w:tr>', (int) $rp) + 7;
            $guard($rs !== false && strpos(substr($hay, (int) $rs, $rp - (int) $rs), '</w:tr>') === false,
                'cell '.$i.' anchor is inside one row');
            preg_match_all('/<w:tc>.*?<\/w:tc>/s', substr($hay, (int) $rs, $re - (int) $rs), $cm);
            $hits = [];
            foreach ($cm[0] as $j => $cell) {
                preg_match_all('/<w:t(?:\s[^>]*)?>(.*?)<\/w:t>/s', $cell, $tm);
                $flat = html_entity_decode(implode('', $tm[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                if (strncmp(ltrim($flat), $prefix, min(strlen($prefix), 60)) === 0) {
                    $hits[] = $j;
                }
            }
            $guard(count($hits) === 1, 'cell '.$i.' prefix ['.mb_substr($prefix, 0, 32).'] matched '.count($hits).' cell(s)');
            if (count($hits) === 1) {
                $guard(substr_count($cm[0][$hits[0]], '<w:p>') === 1, 'cell '.$i.' target cell holds one paragraph');
            }
        }
        $assertPlain($to, 'cell '.$i.' replacement');
    }
};
$preflight($new, $textEdits, $cellEdits);

if ($fails > 0) {
    printf("\n%d guard(s) failed before any write — NOTHING WRITTEN.\n", $fails);
    exit(1);
}

if (! $apply) {
    echo "\nStage 1 would clone P-16 into §25.4, move four P-1 … P-15 ranges and §29.4’s count to 16, and delete the\n"
        ."paragraph pass 173’s row clone duplicated into the 2026-10-21 revision row.\n"
        ."Stage 2 would rewrite P-7’s three cells, §25.5’s four clauses, §24’s key list, §28.4’s two group rows,\n"
        ."§28.8’s closing sentence, G-29’s record, §30.5’s row and §30.6’s list of what is still owed.\n"
        ."All ".count($textEdits)." text and ".count($cellEdits)." cell targets above resolved against the staged bytes.\n\n"
        .'DRY RUN. Re-run with --apply.'."\n";
    exit(0);
}

copy($src, $root.'/storage/app/scratch/ems-docx-pre-pass176.docx');
$rewrite = function (string $name, string $content) use ($src): void {
    $z = new ZipArchive;
    if ($z->open($src) !== true) {
        throw new RuntimeException('cannot reopen for write');
    }
    $z->addFromString($name, $content);
    $z->close();
};
$rewrite('word/document.xml', $new);
file_put_contents($root.'/storage/app/scratch/pass176-stage1.xml', $new);

$z = new ZipArchive;
if ($z->open($src) !== true) {
    exit("REFUSED: stage 1 wrote a file the zip will not open.\n");
}
$roundParts = $z->numFiles;
$round = (string) $z->getFromName('word/document.xml');
$z->close();
$guard($roundParts === $parts, 'the archive still holds '.$parts.' members');
$guard($round === $new, 'the bytes read back are the bytes staged');
$guard(substr_count($round, $dupe) === 1, 'one lane-57 paragraph survives, in the row that owns it');
$guard(substr_count($round, 'P-1 … P-16') === 4, 'four ranges reach P-16 on disk');
$guard(substr_count($round, '>P-16<') === 1, 'P-16 is on disk');
$guard(substr_count($round, '>15<') === 3, 'the §29.4 count moved and the other three 15 cells are untouched');
$guard(@(new DOMDocument)->loadXML($round) !== false, 'the written document.xml parses');

if ($fails > 0) {
    printf("\n%d guard(s) failed after the stage 1 write — the file is on disk; restore it from\nstorage/app/scratch/ems-docx-pre-pass176.docx before doing anything else.\n", $fails);
    exit(1);
}
echo "     stage 1 written and re-read\n";

docxApply($src, $root.'/storage/app/scratch/pass176-backup.docx', $textEdits, $cellEdits, $apply);
