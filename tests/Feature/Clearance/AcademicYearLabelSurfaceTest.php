<?php

namespace Tests\Feature\Clearance;

use App\Models\Academicyears;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * A page may read an academic year only by a name the schema or the model actually supplies.
 *
 * Found by opening the running app rather than by a gate: the clearance queue's Academic Term column printed
 * `undefined-undefined` for every row, because six sites across two clearance pages built the label as
 * `${academicYear.yearStart}-${academicYear.yearEnd}` and `academicyears` has no such columns — it holds
 * `yearLabel`, which already spells the span ("2025-2026"). PHPStan does not read JSX, there is no JavaScript
 * suite, and no test asserted a rendered string, so the word `undefined` reached a desk window and stayed there.
 *
 * The legal names are DERIVED, not listed: the table's own columns, plus whatever `$fillable`, the casts and any
 * `get…Attribute` accessor add. A remembered list would drift the day a migration adds a column, which is the
 * defect shape this repository keeps paying to remove.
 */
class AcademicYearLabelSurfaceTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function legalNames(): array
    {
        $columns = Schema::getColumnListing('academicyears');
        $reflect = new ReflectionClass(Academicyears::class);

        // `getCasts()` is protected, so the declaration is read as the class wrote it — the same reason the
        // year-level and status-tone guards read the .jsx sources rather than asking a runtime that is not there.
        $casts = array_keys((array) ($reflect->getDefaultProperties()['casts'] ?? []));
        $fillable = (array) ($reflect->getDefaultProperties()['fillable'] ?? []);

        $names = array_merge($columns, $fillable, $casts, ['id', 'created_at', 'updated_at']);

        foreach ($reflect->getMethods() as $method) {
            if (preg_match('/^get(.+)Attribute$/', $method->getName(), $m)) {
                $names[] = lcfirst($m[1]);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Every JSX file under resources/js, with the aliases each one binds to an academic year resolved.
     *
     * @return array<string, array<string, list<string>>> path => [receiver => [line, expression]]
     */
    private function academicYearReads(): array
    {
        $found = [];
        $paths = [];
        $dir = new \RecursiveDirectoryIterator(base_path('resources/js/Pages'));
        foreach (new \RecursiveIteratorIterator($dir) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.jsx')) {
                $paths[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        foreach (array_unique($paths) as $path) {
            $lines = explode("\n", (string) file_get_contents($path));
            $aliases = [];
            foreach ($lines as $i => $text) {
                // `const ay = cp.term?.academicYear` — an alias is a second name for the same row.
                if (preg_match('/const\s+(\w+)\s*=\s*[^;]*\bacademicYear\??\s*;/', $text, $m)) {
                    $aliases[] = $m[1];
                }
                $receivers = array_merge(['academicYear'], $aliases);
                foreach ($receivers as $receiver) {
                    // Every read on the line, not the first: a guard clause like `ay?.yearLabel ?` opens most of
                    // these expressions, so a matcher that stopped at the first name would clear a line whose very
                    // next token invents a field — which is the shape a planted mutant exposed on the first run.
                    if (preg_match_all('/\b'.$receiver.'\??\.(\w+)/', $text, $m)) {
                        foreach ($m[1] as $name) {
                            $found[$path][$name][] = ($i + 1).' '.$m[0][0];
                        }
                    }
                }
            }
        }

        return $found;
    }

    #[Test]
    public function no_page_reads_an_academic_year_field_the_schema_does_not_have(): void
    {
        $legal = $this->legalNames();
        $offenders = [];

        foreach ($this->academicYearReads() as $path => $reads) {
            foreach ($reads as $name => $where) {
                if (! in_array($name, $legal, true)) {
                    $offenders[] = basename($path).' reads -> '.$name.' at '.implode(' | ', $where);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "a page reads a field no column, cast or accessor supplies, so the desk sees the word undefined: \n"
                .implode("\n", $offenders)
        );
    }

    #[Test]
    public function the_clearance_pages_spell_an_academic_year_from_the_label_the_model_holds(): void
    {
        // Each page names the academic year in more than one place — a dropdown, a cell, and the guard clause that
        // protects it — so a fix that reached only one of them cannot leave this green. Both numbers were counted
        // off the files today rather than derived: `ay?.yearLabel ? \`${ay.yearLabel} …\`` carries two occurrences on
        // one line, which is why the count is above the number of lines that mention it.
        $expect = ['Index.jsx' => 5, 'Periods.jsx' => 3];

        foreach ($expect as $page => $sites) {
            $text = (string) file_get_contents(base_path('resources/js/Pages/Clearance/'.$page));

            $this->assertStringNotContainsString('yearStart', $text, "{$page} invents a year field again");
            $this->assertStringNotContainsString('yearEnd', $text, "{$page} invents a year field again");
            $this->assertSame(
                $sites,
                substr_count($text, 'yearLabel'),
                "{$page} names the academic year in {$sites} place(s); a different count means one of them stopped spelling it"
            );
        }
    }

    #[Test]
    public function the_year_label_is_a_real_value_so_the_guard_could_not_be_satisfied_by_nothing(): void
    {
        // A guard that compares against an empty legal set would pass while forbidding everything. This asserts the
        // derivation found the column it is meant to allow, read from the migrated schema the suite runs on.
        $legal = $this->legalNames();
        $this->assertContains('yearLabel', $legal);
        $this->assertContains('startDate', $legal);
        $this->assertNotContains('yearStart', $legal);
        $this->assertNotContains('yearEnd', $legal);
    }
}
