<?php

namespace Tests\Feature\Sorting;

use App\Models\Students;
use App\Support\ListSort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The guard on the ONE sort helper (§28.3 G-40, task #61).
 *
 * `apply()` tests the asked key against the handler's allow-list and then reads `$allowed[$column]` — where
 * `$column` may be the HANDLER's own fallback string, which was never tested against that list. A fallback naming
 * no key there answered `Undefined array key`, which is a 500 at the desk, and no request can cause it: the asked
 * key is checked first, so the only way to reach it is to mis-declare the default. That is why it is a refusal
 * rather than a fallback to something else — silently ordering by a different column than the screen named would be
 * the second definition of "the list's own default", which this file has refused for year levels, tones and
 * citations.
 *
 * The case that matters most is the third one: a guard which also refuses an unLISTED request would satisfy the
 * first two cases and break every wired screen, because falling back is the normal answer to a column the desk
 * never lists.
 */
class ListSortGuardTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{column: string}> */
    private function allowList(): array
    {
        return [
            'schoolIdNumber' => ['column' => 'schoolIdNumber'],
            'emailAddress' => ['column' => 'emailAddress'],
        ];
    }

    #[Test]
    public function a_fallback_that_names_no_key_is_refused_naming_both_the_value_and_the_keys_that_exist(): void
    {
        try {
            ListSort::apply(
                Students::query(),
                null,
                null,
                $this->allowList(),
                'passwordHash',
                'students.studentId'
            );
            $this->fail('a fallback naming no key in its own allow-list was accepted, so the next request would meet '
                .'`Undefined array key` at the desk instead of a reason');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            // Three readings, because a refusal that prints only a verdict is the same cost as a guard that fires
            // silently: which value was settled on, which keys really exist, and which of the two arguments is wrong.
            $this->assertStringContainsString('[passwordHash]', $message, 'the refusal did not name the value it refused');
            $this->assertStringContainsString('the keys are schoolIdNumber, emailAddress', $message,
                'the refusal did not print the keys that do exist');
            $this->assertStringContainsString("handler's fallback was [passwordHash]", $message,
                'the refusal did not say which of the two arguments to fix');
        }
    }

    #[Test]
    public function an_empty_allow_list_is_refused_rather_than_falling_back_into_nothing(): void
    {
        // With no keys, `$allowed[$column]` can only ever miss, so this is the same defect reached from the other
        // side — and an empty list is exactly what a screen that forgot to declare its columns looks like.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty allow-list');

        ListSort::apply(Students::query(), 'schoolIdNumber', 'asc', [], 'schoolIdNumber', null);
    }

    #[Test]
    public function a_column_the_desk_never_lists_still_falls_back_rather_than_being_refused(): void
    {
        // The guard must not widen into refusing the NORMAL case: an unlisted request is answered by the handler's
        // own default, and every one of the wired screens depends on that. A guard that refuses everything satisfies
        // the two cases above and breaks the desk.
        $applied = ListSort::apply(
            Students::query(),
            'somethingTheDeskNeverLists',
            'asc',
            $this->allowList(),
            'schoolIdNumber',
            'students.studentId'
        );

        $this->assertSame('schoolIdNumber', $applied['column']);
        $this->assertSame('asc', $applied['direction']);
    }

    #[Test]
    public function no_screen_hands_the_helper_an_empty_allow_list(): void
    {
        // The empty-list refusal is a statement about a mistake a future batch can make; this case is the census
        // that says none is made today, so the refusal is never the only thing standing between a desk and a 500.
        $offenders = [];
        foreach ($this->phpFilesUnderApp() as $file) {
            $body = (string) file_get_contents($file);
            if (! str_contains($body, 'ListSort::apply(')) {
                continue;
            }
            if (preg_match('/\$(?:allowed|sortable)\s*=\s*\[\s*\]/', $body) === 1) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders,
            'a screen that calls ListSort declares an empty allow-list: '.implode(', ', $offenders));
    }

    #[Test]
    public function the_refusal_lives_in_the_shared_helper_and_nowhere_else(): void
    {
        // Two answers to "is this column obeyed" is this repository's oldest defect; the guard belongs to the one
        // helper every wired screen already calls.
        $sites = [];
        foreach ($this->phpFilesUnderApp() as $file) {
            $body = (string) file_get_contents($file);
            $hits = preg_match_all('/array_key_exists\(\$column,\s*\$(?:allowed|sortable)\)/', $body);
            if ($hits > 0) {
                $sites[] = basename($file).' x'.$hits;
            }
        }

        $this->assertSame(['ListSort.php x1'], $sites,
            'the column-membership test is not defined once: '.implode(', ', $sites)
            .' — a second answer to "is this column obeyed" is the defect this repository removes on sight');
    }

    /** @return list<string> */
    private function phpFilesUnderApp(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
