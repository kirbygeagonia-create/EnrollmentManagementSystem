<?php

/**
 * One definition of "what does this page actually read from the student", shared by the two lane-108 readers.
 *
 * `lane108-census-whole-student-loads.php` decides whether a site is a candidate, and
 * `lane108b-page-read-set.php` prints the evidence for that decision. They must not answer the question twice:
 * the census read ONLY the page file, and `Evaluation/Show` turned out to render `EnrollmentProfileForm`, which
 * reads gender, birthplace, citizenship, civilStatus, contactNumber, telephone, email, semestersCompleted and
 * yearsInInstitution — a detail screen that EDITS the record, which the census had filed as "reads nothing beyond
 * the name". Narrowing from that reading would have emptied the profile form on the desk's screen, the same class of
 * mistake as `id` in a column list, arriving from the other side.
 *
 * So both tools now call this walker: resolve the page's relative and `@/` imports recursively inside
 * `resources/js`, skip comment lines, and report which `students` columns the reachable code names.
 *
 * Every column named on a line is recorded, not just the first: the loop used to `break` after its first hit, so
 * `Registrar/Index.jsx:21` — `${row.student.lastName}, ${row.student.firstName}${row.student.middleName …}` —
 * reported `lastName` alone and a reader would have concluded the queue never paints a given name. That is
 * `AcademicYearLabelSurfaceTest`'s defect (a scanner that read only the FIRST `receiver.NAME` on a line) arriving in
 * this module, and in a read-set checker it is not cosmetic: the columns missed are exactly the ones a narrowed
 * payload could be missing.
 *
 * `lane108b` prints every matching LINE for a human to judge, because several of these names are also columns of
 * other records — `email` and `status` belong to staff and payments — and only reading says which record a hit
 * talks about. This module therefore answers "which names appear", never "which record is read".
 */

/**
 * @return string[] columns that belong to `students` and to nothing else a desk page renders, so a hit on one of
 *                  them is proof the screen reads the person's record rather than a fee sheet or a staff account
 */
function student_nondemic_columns(): array
{
    return [
        'birthdate', 'birthplace', 'citizenship', 'civilStatus', 'contactNumber', 'gender', 'passwordHash',
        'religionId', 'semestersCompleted', 'telephoneNumber', 'yearsInInstitution',
    ];
}

/**
 * @return string[] names that `students` holds but other records hold too, so a hit is a line to READ rather than a
 *                  classification. `email` is the trap: `AuthenticatedLayout.jsx` prints the signed-in STAFF's
 *                  email on every screen, and a reader that classified on it called all 25 pages sensitive in one
 *                  run of this module — the opposite error to the one it was written to fix.
 */
function student_ambiguous_columns(): array
{
    return ['email', 'username', 'status', 'remarks'];
}

/** A suffix is half of a NAME, not a demographic record. */
function student_name_columns(): array
{
    return ['studentId', 'schoolIdNumber', 'lastName', 'firstName', 'middleName', 'suffix'];
}

/**
 * Walk a page's import graph inside resources/js.
 *
 * @return array{files: array<string,string>, unresolved: string[]}
 */
function page_reachable_files(string $root, string $pageFile, int $depth = 3, int $maxFiles = 400): array
{
    $jsRoot = str_replace('\\', '/', $root).'/resources/js';
    $seen = [];
    $unresolved = [];
    $queue = [[str_replace('\\', '/', $pageFile), 0]];
    while ($queue !== [] && count($seen) < $maxFiles) {
        [$file, $d] = array_shift($queue);
        if (isset($seen[$file]) || ! is_file($file)) {
            continue;
        }
        $seen[$file] = (string) file_get_contents($file);
        if ($d >= $depth) {
            continue;
        }
        if (preg_match_all('/\bfrom\s*[\x27"]([^\x27"]+)[\x27"]/s', $seen[$file], $im)) {
            foreach ($im[1] as $spec) {
                if (! str_starts_with($spec, '.') && ! str_starts_with($spec, '@/')) {
                    continue; // a package
                }
                $next = resolve_js_import($file, $spec, $jsRoot);
                if ($next === null) {
                    $unresolved[] = $spec;

                    continue;
                }
                if (! isset($seen[$next])) {
                    $queue[] = [$next, $d + 1];
                }
            }
        }
    }

    return ['files' => $seen, 'unresolved' => array_values(array_unique($unresolved))];
}

/** Resolve one specifier, or null when it leaves resources/js. Separator style is normalised first. */
function resolve_js_import(string $fromFile, string $spec, string $jsRoot): ?string
{
    if (! str_starts_with($spec, '.') && ! str_starts_with($spec, '@/')) {
        return null;
    }
    $base = str_starts_with($spec, '@/') ? $jsRoot.'/'.substr($spec, 2) : dirname($fromFile).'/'.$spec;
    $base = str_replace('\\', '/', (string) preg_replace('#/+#', '/', $base));
    if (! str_starts_with($base, $jsRoot)) {
        return null;
    }
    foreach (['', '.jsx', '.js', '.tsx', '/index.jsx', '/index.js'] as $ext) {
        if (is_file($base.$ext)) {
            return str_replace('\\', '/', $base.$ext);
        }
    }

    return null;
}

/**
 * Which `students` column names appear in a set of reachable files, with the lines.
 *
 * @param  array<string,string> $files
 * @return array{nondemographic: string[], ambiguous: string[], names: string[], lines: array<int,string>}
 */
function page_student_column_reads(array $files): array
{
    $nondem = [];
    $ambiguous = [];
    $names = [];
    $lines = [];
    $classify = array_merge(student_nondemic_columns(), student_ambiguous_columns(), student_name_columns());
    foreach ($files as $path => $src) {
        foreach (explode("\n", $src) as $ln => $line) {
            if (preg_match('/^\s*(\/\/|\*|\/\*)/', $line)) {
                continue; // a comment is not a read
            }
            foreach ($classify as $col) {
                if (preg_match('/[.\?\]]'.$col.'\b/', $line) === 1) {
                    $short = str_replace('\\', '/', basename($path));
                    if (in_array($col, student_nondemic_columns(), true)) {
                        $nondem[$col] = $col;
                    } elseif (in_array($col, student_ambiguous_columns(), true)) {
                        $ambiguous[$col] = $col;
                    } else {
                        $names[$col] = $col;
                    }
                    $lines[] = sprintf('%s:%d %s', $short, $ln + 1, trim(preg_replace('/\s+/', ' ', $line) ?? ''));
                }
            }
        }
    }
    sort($lines);

    return [
        'nondemographic' => array_values($nondem),
        'ambiguous' => array_values($ambiguous),
        'names' => array_values($names),
        'lines' => $lines,
    ];
}
