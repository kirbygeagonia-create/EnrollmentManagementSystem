<?php

namespace Tests\Feature\Print;

use App\Models\Settings;
use App\Support\PrintAssets;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrintTemplateSelfContainmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Browsershot renders these views from a temporary file:// page, so one
     * absolute URL is enough to make headless Chrome call back into the app: under
     * a single-threaded server that request can never be served while the PDF
     * request is open, so every download dies on a navigation timeout. A layout or
     * Vite directive would pull the same external references in.
     */
    #[Test]
    public function test_print_templates_are_self_contained(): void
    {
        $templates = glob(resource_path('views/prints/*.blade.php'));

        $this->assertNotEmpty($templates, 'No print templates found — check the glob path.');

        $forbidden = ['asset(', 'url(', '://', '@extends', '@vite', '<link', 'background-image'];

        foreach ($templates as $path) {
            $html = file_get_contents($path);

            foreach ($forbidden as $needle) {
                $this->assertFalse(
                    str_contains($html, $needle),
                    basename($path)." must not reference external assets (found '{$needle}')."
                );
            }
        }
    }

    #[Test]
    public function test_logo_is_embedded_as_a_data_uri(): void
    {
        $uri = PrintAssets::logoDataUri();

        $this->assertStringStartsWith('data:image/png;base64,', $uri);
        $this->assertGreaterThan(4000, strlen($uri), 'The logo data URI looks truncated.');
        $this->assertSame($uri, PrintAssets::logoDataUri(), 'The data URI must be cached, not re-read per render.');
    }

    /**
     * Every `config('settings.X')` in a print view falls through to its own inline
     * default when no row named X exists, and a blank fall-through is silent on paper.
     * clearanceReplacementFee is the precedent: the slip printed a figure the fee sheet
     * did not charge (P-12), so a key a template reads without the seeder supplying it
     * is the same shape of defect and must not be re-introduced.
     */
    #[Test]
    public function test_the_print_views_read_only_settings_keys_the_seeder_supplies(): void
    {
        $read = [];
        foreach (glob(resource_path('views/prints/*.blade.php')) ?: [] as $path) {
            preg_match_all("/config\('settings\.([A-Za-z]+)'/", (string) file_get_contents($path), $m);
            $read = array_merge($read, $m[1]);
        }
        $read = array_unique($read);
        sort($read);

        $this->assertSame(
            ['schoolAddress', 'schoolName', 'schoolPhone'],
            $read,
            'The print views read '.implode(', ', $read).' — the letterhead keys are three, and no more.'
        );
    }

    /**
     * The other half of the same contract: a key the paper reads must arrive with a
     * value. schoolAddress and schoolPhone were seeded blank from 2026-10-01 on the
     * seeder's own assertion that this repository did not record them, while
     * SEAIT_Official_Information.md §1 had carried both since 2026-08-28 — so the
     * letterhead printed the school's name over an empty address line and no test,
     * screen or gate said so (P-7). A blank is invisible in code and visible on paper,
     * which is why this asserts against the source sheet rather than a remembered
     * string: the values may be re-ruled by the Registrar, but they may not be invented.
     */
    #[Test]
    public function test_the_letterhead_settings_are_seeded_from_the_school_sheet(): void
    {
        (new SettingsSeeder)->run();

        $sheet = (string) file_get_contents(base_path('Documentation/SEAIT_Official_Information.md'));

        foreach (['schoolName', 'schoolAddress', 'schoolPhone'] as $key) {
            $row = Settings::find($key);

            $this->assertNotNull($row, "SettingsSeeder no longer seeds {$key}, so the letterhead prints its inline default.");
            $value = trim((string) $row->settingValue);
            $this->assertNotSame('', $value, "settings.{$key} is blank, and a blank prints an empty letterhead line.");

            // The name is spelled one way on the sheet and another in the seeded value
            // (P-16), so only the two keys this file supplied are traced to their source.
            if ($key !== 'schoolName') {
                $this->assertStringContainsString(
                    $value,
                    $sheet,
                    "settings.{$key} carries “{$value}”, which SEAIT_Official_Information.md does not record."
                );
            }
        }
    }
}
