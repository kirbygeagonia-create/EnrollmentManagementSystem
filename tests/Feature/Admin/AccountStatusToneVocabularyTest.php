<?php

namespace Tests\Feature\Admin;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One account status, one tone map (§28.3 G-39's class, in the vocabulary its guard did not cover).
 *
 * `EnrollmentToneVocabularyTest` refuses a page that re-declares the ENROLLMENT tones, and deliberately leaves
 * `proposed / confirmed / dropped` out of its reach — those are the enrolled-subject vocabulary. The account status
 * was the other word two screens each spelled to themselves: the staff directory and the student directory both
 * carried their own `statusToneMap = {active:'success', inactive:'neutral'}`, byte-identical, so an edit to one copy
 * would make the same word read as a hold on one desk and silence on another. Green screens and a green suite cannot
 * see that, because both pages rendered correctly the whole time.
 *
 * There is no JavaScript test suite in this repository, so the pair is pinned by reading the sources — the method
 * established for the year-level pairing and reused for the enrollment tones.
 */
class AccountStatusToneVocabularyTest extends TestCase
{
    /** Every page under resources/js/Pages, as the enrollment-tone guard reads them. @return array<string, string> */
    private function pageFiles(): array
    {
        $root = base_path('resources/js/Pages');
        $files = [];
        $walk = static function (string $dir) use (&$walk, &$files): void {
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir.'/'.$entry;
                if (is_dir($path)) {
                    $walk($path);

                    continue;
                }
                if (str_ends_with($path, '.jsx')) {
                    $files[$path] = (string) file_get_contents($path);
                }
            }
        };
        $walk($root);

        $this->assertNotEmpty($files, 'the scan read no page at all, which is not the same as a clean tree');

        return $files;
    }

    #[Test]
    public function no_desk_page_declares_its_own_map_of_account_status_tones(): void
    {
        foreach ($this->pageFiles() as $path => $text) {
            // A local object literal whose first two keys are the account words, in either order.
            $declared = preg_match(
                '/const\s+[A-Za-z0-9_]*[Tt]one[A-Za-z0-9_]*\s*=\s*\{[^{}]*active\s*:[^{}]*inactive\s*:[^{}]*\}/s',
                $text
            ) === 1 || preg_match(
                '/const\s+[A-Za-z0-9_]*[Tt]one[A-Za-z0-9_]*\s*=\s*\{[^{}]*inactive\s*:[^{}]*active\s*:[^{}]*\}/s',
                $text
            ) === 1;

            $this->assertFalse(
                $declared,
                basename($path).' re-declares an active/inactive tone map; the vocabulary lives in Components/ui/statusLabel.js'
            );
        }
    }

    #[Test]
    public function every_surface_that_paints_an_account_status_reads_the_shared_map(): void
    {
        foreach ($this->pageFiles() as $path => $text) {
            if (! str_contains($text, 'accountStatusTone[')) {
                continue;
            }

            $this->assertMatchesRegularExpression(
                '/import\s*\{[^}]*\baccountStatusTone\b[^}]*\}\s*from\s*[\'"]@\/Components\/ui[\'"]/',
                $text,
                basename($path).' paints the badge from a map it never imported'
            );
        }

        // The other half of the pairing: a page may import the name, but the module has to export it. Checked here
        // rather than only in the definition case, because a rename inside `statusLabel.js` leaves every page's own
        // text untouched — the import still reads correctly, and only the far end of it has moved.
        $module = (string) file_get_contents(base_path('resources/js/Components/ui/statusLabel.js'));
        $this->assertMatchesRegularExpression(
            '/export const accountStatusTone\b/',
            $module,
            'the map the pages import is no longer exported by the module that owns it'
        );

        // The three screens that read it today: the staff directory, the student directory and the Student 360.
        // The count is not the claim — the claim is that each of them reads the shared symbol rather than its own.
        $this->assertGreaterThan(2, array_reduce(
            $this->pageFiles(),
            fn (int $n, string $t): int => $n + (str_contains($t, 'accountStatusTone[') ? 1 : 0),
            0
        ), 'the shared map should be read by more than one desk');
    }

    #[Test]
    public function the_name_of_the_retired_local_map_is_gone_from_the_tree(): void
    {
        foreach ($this->pageFiles() as $path => $text) {
            foreach (['statusToneMap', 'studentStatusToneMap'] as $retired) {
                $this->assertStringNotContainsString(
                    $retired,
                    $text,
                    basename($path).' still names the local map this batch retired'
                );
            }
        }
    }

    #[Test]
    public function the_shared_map_keeps_the_colours_the_two_screens_already_painted(): void
    {
        $text = (string) file_get_contents(base_path('resources/js/Components/ui/statusLabel.js'));

        // The repair moved the definition, not the appearance: an account that is active is a success badge and an
        // inactive one is neutral, exactly as both pages drew it while each held its own copy.
        $this->assertMatchesRegularExpression(
            '/export const accountStatusTone = \{\s*active: \'success\',\s*inactive: \'neutral\',?\s*\}/',
            $text
        );

        $barrel = (string) file_get_contents(base_path('resources/js/Components/ui/index.js'));
        $this->assertMatchesRegularExpression(
            '/export \{[^}]*\baccountStatusTone\b[^}]*\} from \'.\/statusLabel\'/',
            $barrel,
            'the map the pages import through the barrel must really be exported by it'
        );
    }
}
