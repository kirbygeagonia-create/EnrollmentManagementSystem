<?php

namespace Tests\Feature;

use App\Support\YearLevel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The school spells a year level as an ordinal: its own clearance slip reads
 * "(BSIT) Bachelor of Science in Information Technology - 2nd Year", and App\Enums\Semester
 * already spells the term "1st" / "2nd" on the same paper. Ruled 2026-10-24 by the owner:
 * "the proper format is 2nd year, not year 2" — which reverses the vocabulary the earlier
 * single-formatter lane had chosen, not the fact that there is one formatter.
 *
 * The pairing is what needs a guard rather than a convention. Two definitions must agree because a
 * browser cannot call PHP: App\Support\YearLevel for the papers rendered by Browsershot, and
 * formatYearLevel in resources/js/Components/ui/statusLabel.js for every desk screen. The first test
 * reads the JavaScript rule out of its own source and re-derives each answer, so a change on either
 * side goes red here; the second refuses any surface that spells a level by hand, which is how five
 * pick-lists came to offer "Year 2" while three sibling tables already printed "2nd Year".
 */
class YearLevelVocabularyTest extends TestCase
{
    private const JS_FORMATTER = 'resources/js/Components/ui/statusLabel.js';

    #[Test]
    public function test_the_helper_answers_the_words_the_school_writes(): void
    {
        $this->assertSame('1st Year', YearLevel::format(1), 'A first-year student is written "1st Year" on the school paper, so the helper must not answer "Year 1".');
        $this->assertSame('2nd Year', YearLevel::format(2));
        $this->assertSame('3rd Year', YearLevel::format(3));
        $this->assertSame('4th Year', YearLevel::format(4));
        $this->assertSame('5th Year', YearLevel::format(5), 'A five-year program is on the books, and its level is an ordinal like the rest.');

        // The English exceptions, so a re-measure cannot mistake the rule for "append nd to 2".
        foreach ([11 => '11th', 12 => '12th', 13 => '13th', 21 => '21st', 22 => '22nd', 23 => '23rd', 111 => '111th'] as $level => $expected) {
            $this->assertSame($expected.' Year', YearLevel::format($level), "{$level} is spelled by the exception rule, not by its last digit.");
        }
    }

    /**
     * A level the record cannot answer is stated as an absence by each paper in its own words
     * ("N/A", an em dash), so the helper answers null rather than inventing "0th Year".
     */
    #[Test]
    public function test_a_level_that_was_never_recorded_answers_nothing(): void
    {
        $this->assertNull(YearLevel::format(null), 'The helper invented a level where the record holds none, so a print view that only dashes on null would paint it.');

        foreach ([0, -1] as $level) {
            $this->assertNull(
                YearLevel::format($level),
                "yearLevel {$level} is not a year anyone sits; answering \"{$level}th Year\" would put a phantom level on paper."
            );
        }
    }

    /**
     * The browser's formatter, judged from the text it is built from: the suffix map and the exception
     * bounds are read out of statusLabel.js and used to compute the answers, which must equal the PHP
     * helper's. This is the only way to test a pairing across two languages without a JS runtime —
     * resources/js has no test suite.
     */
    #[Test]
    public function test_the_javascript_formatter_and_the_php_helper_agree_level_by_level(): void
    {
        $js = (string) file_get_contents(base_path(self::JS_FORMATTER));

        $this->assertMatchesRegularExpression(
            '/return `\$\{ordinalSuffix\(level\)\} Year`;/',
            $js,
            'formatYearLevel no longer builds "<ordinal> Year", so the desk screens and the papers spell the same number differently — re-rule the vocabulary and update §25.6, or restore the ordinal.'
        );

        preg_match('/\{\s*1:\s*\'(\w+)\'\s*,\s*2:\s*\'(\w+)\'\s*,\s*3:\s*\'(\w+)\'\s*\}/', $js, $map);
        $this->assertNotEmpty($map, 'The suffix map that formatYearLevel depends on is gone or reworded, so this test could no longer read the JavaScript rule.');
        [$st, $nd, $rd] = [$map[1], $map[2], $map[3]];

        preg_match('/level % 100 >= (\d+) && level % 100 <= (\d+)/', $js, $bounds);
        $this->assertNotEmpty($bounds, 'The 11th–13th exception is no longer expressed as a bound, so the two definitions cannot be compared.');
        [$low, $high] = [(int) $bounds[1], (int) $bounds[2]];

        foreach (range(1, 125) as $level) {
            $fromJs = ($level % 100 >= $low && $level % 100 <= $high)
                ? $level.'th'
                : $level.(match ($level % 10) {
                    1 => $st, 2 => $nd, 3 => $rd, default => 'th'
                });

            $this->assertSame(
                $fromJs.' Year',
                YearLevel::format($level),
                "Level {$level}: the desk screen would print \"{$fromJs} Year\" and the paper prints something else."
            );
        }
    }

    /**
     * The pick-list is where a level gets *recorded*, so its labels must come from the same call the
     * screens use. The scan for hand-spelled levels cannot see a drift to some other wording ("Level 2"),
     * so this pins the construction itself: yearLevelChoices() answers a label by calling formatYearLevel().
     */
    #[Test]
    public function test_the_pick_list_builds_its_labels_by_calling_the_one_formatter(): void
    {
        $js = (string) file_get_contents(base_path(self::JS_FORMATTER));

        $this->assertMatchesRegularExpression(
            '/export function yearLevelChoices\([^)]*\)\s*\{\s*return levels\.map\(\(level\) => \(\{ value: level, label: formatYearLevel\(level\) \}\)\);/s',
            $js,
            'yearLevelChoices() no longer answers label: formatYearLevel(level), so the option a desk picks can drift from the word the paper prints — and a scan for a hand-spelled "Year 2" would not see it.'
        );

        $this->assertMatchesRegularExpression(
            '/export const YEAR_LEVELS = \[1, 2, 3, 4, 5\];/',
            $js,
            'YEAR_LEVELS changed, so a pick-list that calls yearLevelChoices() with no argument now offers different levels than the desk may record — check the validation rule `yearLevel` in EvaluationController and BlockingController before editing this list.'
        );
    }

    /**
     * The half that keeps the vocabulary single. Every desk surface and every print view must read a
     * level through its side's formatter; a hand-spelled "Year 2" survives the JS gates (eslint cannot
     * see it) and the PHP gates (no blade test renders it), so this is the only instrument that looks.
     * tools/ is deliberately out of scope: its reports are prose about the column, not a surface a
     * student or a desk reads.
     */
    #[Test]
    public function test_no_surface_spells_a_year_level_by_hand(): void
    {
        $handSpelled = '/Year\s+(?:[0-9]|\$\{)|[\'"`]Year [\'"`]\s*\.|\bYear\s*\{[A-Za-z_$]/';
        $needleFound = [];
        $base = str_replace('\\', '/', base_path()).'/';

        foreach (['app', 'routes', 'resources/views', 'resources/js'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($dir), \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|blade\.php|jsx|js)$/i', $file->getFilename())) {
                    continue;
                }
                $path = str_replace('\\', '/', $file->getPathname());
                if (str_ends_with($path, self::JS_FORMATTER) || str_ends_with($path, 'app/Support/YearLevel.php')) {
                    continue; // the two definitions
                }
                $lines = explode("\n", (string) file_get_contents($path));
                foreach ($lines as $i => $line) {
                    if (preg_match($handSpelled, $line)) {
                        $needleFound[] = str_replace($base, '', $path).':'.($i + 1).'  '.trim($line);
                    }
                }
            }
        }

        $this->assertSame([], $needleFound, "These lines spell a year level without the formatter, so one number is on two vocabularies:\n".implode("\n", $needleFound));
    }

    /**
     * A print view that stopped calling the helper would print a bare integer on paper, and the sweep
     * above only refuses a hand-spelled "Year N". Each of the six papers that names a level has to read
     * it from the one definition.
     */
    #[Test]
    public function test_every_print_view_that_names_a_level_reads_the_one_helper(): void
    {
        $views = [];
        foreach (glob(resource_path('views/prints/*.blade.php')) ?: [] as $path) {
            if (str_contains((string) file_get_contents($path), 'yearLevel')) {
                $views[] = basename($path);
                $this->assertStringContainsString(
                    'YearLevel::format(',
                    (string) file_get_contents($path),
                    basename($path).' renders a year level without App\Support\YearLevel, so this paper can drift from the desk screen that shows the same record.'
                );
            }
        }

        $this->assertGreaterThanOrEqual(6, count($views), 'Only '.count($views).' print views name a level; the six papers (slip, form, certificate, subject load, class card, block schedule) are what this test exists to cover.');
    }
}
