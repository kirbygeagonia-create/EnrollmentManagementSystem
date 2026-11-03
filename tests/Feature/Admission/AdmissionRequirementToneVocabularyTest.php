<?php

namespace Tests\Feature\Admission;

use App\Enums\AppliesTo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * One vocabulary, one map — the intake requirement catalogue's badge (prompt §11's labelled controls, §28.3 G-39's
 * class reached from `appliesTo`).
 *
 * `AdmissionRequirements.jsx` carried `{ firstYear: 'info', … }` beside the `Select` that filters the same column.
 * Everything rendered, so the suite, ESLint and a person at the desk each saw nothing: the defect is that the day
 * `App\Enums\AppliesTo` gains a value, the badge has no tone for it and the fix lives in a page file rather than in
 * the one module that owns presentation vocabulary. There is no JavaScript test suite in this repository, so the
 * pairing is pinned by READING the sources — the method established for the year-level vocabulary and repeated for
 * the account-status tones.
 *
 * The far end of the pairing is what the first run of the falsifier proved missing: renaming the export inside
 * `statusLabel.js` leaves every page's import text untouched, so a guard that only reads the pages cannot see the
 * module move. Case 1 therefore asserts the export AND the barrel line.
 */
class AdmissionRequirementToneVocabularyTest extends TestCase
{
    private const MODULE = 'resources/js/Components/ui/statusLabel.js';

    private const BARREL = 'resources/js/Components/ui/index.js';

    private const PAGE = 'resources/js/Pages/Admin/ReferenceData/AdmissionRequirements.jsx';

    /** @return array<string, string> the map as the module actually writes it */
    private function moduleMap(): array
    {
        $src = (string) file_get_contents(base_path(self::MODULE));
        if (preg_match('/export const appliesToTone = \{([^}]*)\}/s', $src, $m) !== 1) {
            $this->fail('statusLabel.js no longer exports `appliesToTone` as one object literal — the map moved or was re-split');
        }
        $entries = [];
        preg_match_all('/([A-Za-z]+):\s*\'([a-z-]+)\'/', $m[1], $pairs, PREG_SET_ORDER);
        foreach ($pairs as $p) {
            $entries[$p[1]] = $p[2];
        }
        if ($entries === []) {
            $this->fail('the appliesToTone map parsed empty, so this case would be asserting nothing');
        }

        return $entries;
    }

    #[Test]
    public function the_module_and_its_barrel_both_answer_the_one_map(): void
    {
        $module = (string) file_get_contents(base_path(self::MODULE));
        $barrel = (string) file_get_contents(base_path(self::BARREL));

        $this->assertStringContainsString('export const appliesToTone = {', $module,
            'the shared module stopped exporting the map, so the page reads an undefined name and every badge falls to its own default');
        $this->assertStringContainsString('appliesToTone', $barrel,
            'the barrel no longer re-exports appliesToTone, which is the half a page-only guard cannot see');
    }

    #[Test]
    public function the_map_holds_exactly_the_values_the_enum_declares(): void
    {
        $map = $this->moduleMap();
        $enum = array_map(static fn (AppliesTo $c): string => $c->value, AppliesTo::cases());
        sort($enum);
        $keys = array_keys($map);
        sort($keys);

        // Not a subset: a key the enum no longer holds is a tone waiting for a value that cannot arrive, and a value
        // with no key is the badge the desk sees as colourless.
        $this->assertSame($enum, $keys,
            'the tone map and App\Enums\AppliesTo answer different lists: map ['.implode(',', $keys).'] enum ['.implode(',', $enum).']');
        foreach ($map as $key => $tone) {
            $this->assertNotEmpty($tone, "[$key] maps to no tone");
        }
    }

    #[Test]
    public function the_page_reads_the_shared_map_and_holds_no_copy_of_its_own(): void
    {
        $jsx = (string) file_get_contents(base_path(self::PAGE));

        $this->assertStringNotContainsString('appliesToToneMap', $jsx,
            'the page declared its own tone map again, which is the two-answers-for-one-word defect this class exists to refuse');
        $this->assertMatchesRegularExpression('/import \{[^}]*\bappliesToTone\b[^}]*\} from \'@\/Components\/ui\'/', $jsx,
            'the page no longer imports the shared map through the barrel every other surface uses');
        $this->assertStringContainsString('appliesToTone[row.appliesTo]', $jsx,
            'the badge is not reading the shared map for the row\'s own value');
    }

    #[Test]
    public function no_second_surface_declares_an_applies_to_tone_map(): void
    {
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/js'), \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'js' && $file->getExtension() !== 'jsx') {
                continue;
            }
            $body = (string) file_get_contents($file->getPathname());
            if (preg_match('/(const|var|let)\s+appliesTo[A-Za-z]*Tone[A-Za-z]*\s*=\s*\{/', $body) === 1) {
                // Iterators hand back platform separators, so the path is normalised before the prefix is stripped —
                // otherwise this case compares a Windows path against a POSIX one and reports its own reader as a defect.
                $normal = str_replace('\\', '/', $file->getPathname());
                $found[] = str_replace(str_replace('\\', '/', base_path()).'/', '', $normal);
            }
        }

        // The shared module is allowed exactly this one literal; a second file holding one is the drift. Naming the
        // owner rather than asserting an empty list is what lets a new page-local copy be reported as itself.
        $this->assertSame(['resources/js/Components/ui/statusLabel.js'], $found,
            'an appliesTo tone map is declared outside the shared module: '.implode(', ', $found));
    }
}
