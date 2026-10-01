<?php

namespace Tests\Feature\Print;

use App\Support\PrintAssets;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PrintTemplateSelfContainmentTest extends TestCase
{
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
}
