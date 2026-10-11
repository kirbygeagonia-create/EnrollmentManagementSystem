<?php

namespace Tests\Feature\Errors;

use App\Exceptions\PrintRendererMissing;
use App\Services\ChromiumLocator;
use App\Services\PrintService;
use Illuminate\Support\Facades\Route as RouteFacade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What a desk sees when the server cannot draw a printed paper.
 *
 * Every PDF is rendered by shelling out to a real Chrome or Edge. On a server with
 * neither, the download used to end on the generic 500 page — which reads as the system
 * having broken rather than as one dependency an office head can install — and none of
 * the six download routes had a test, because a test that needs Chrome is green on a
 * dev machine and red on the runner. These pin the refusal instead, forced by naming a
 * browser binary that does not exist, which behaves the same on every machine.
 */
class PrintRendererTest extends TestCase
{
    private const MISSING_BINARY = 'C:\\ems-test\\no-such-browser.exe';

    protected function setUp(): void
    {
        parent::setUp();

        RouteFacade::middleware('web')->get('__test/no-browser', function () {
            throw new PrintRendererMissing('No Chrome or Edge binary is available to render the document.');
        });
    }

    #[Test]
    public function a_server_with_no_browser_refuses_before_the_document_is_built(): void
    {
        config(['services.chrome_path' => self::MISSING_BINARY]);

        $target = storage_path('app/prints/refusal-probe.pdf');

        try {
            // The template and data would both fail loudly if the render started, so
            // reaching the guard first is what this assertion actually measures.
            app(PrintService::class)->generatePdf('prints.clearance-slip', [], 'refusal-probe.pdf');
            $this->fail('The print service drew a document on a server with no browser.');
        } catch (PrintRendererMissing $e) {
            $this->assertStringContainsString('binary', $e->getMessage());
        }

        $this->assertFileDoesNotExist($target);
    }

    #[Test]
    public function a_configured_binary_is_the_one_used_and_a_missing_one_is_not_replaced(): void
    {
        config(['services.chrome_path' => base_path('artisan')]);
        $this->assertEquals(base_path('artisan'), ChromiumLocator::locate());

        config(['services.chrome_path' => self::MISSING_BINARY]);
        $this->assertNull(
            ChromiumLocator::locate(),
            'Falling back to a different browser would reflow a paper the Registrar signed off against one rendering.'
        );
    }

    #[Test]
    public function an_unconfigured_server_still_finds_an_installed_browser(): void
    {
        config(['services.chrome_path' => null]);

        $found = ChromiumLocator::locate();

        if ($found === null) {
            foreach (ChromiumLocator::candidates() as $path) {
                $this->assertFileDoesNotExist($path, 'A browser on the candidate list was not located.');
            }

            $this->markTestSkipped('No Chrome or Edge is installed on this machine.');
        }

        $this->assertContains($found, ChromiumLocator::candidates());
        // A user-scope Chrome install is the other half of what school machines run, and
        // the Debian package puts Chrome outside /usr/bin entirely.
        $this->assertContains('/opt/google/chrome/chrome', ChromiumLocator::candidates());
    }

    #[Test]
    public function a_desk_that_cannot_get_its_paper_is_sent_back_to_the_screen_it_was_on(): void
    {
        $this->withHeaders(['Referer' => '/clearance/12/slip'])->get('/__test/no-browser')
            ->assertRedirect('/clearance/12/slip');

        $this->assertNotNull(session('error'));
    }

    #[Test]
    public function the_refusal_says_nothing_was_produced_and_names_both_ways_to_fix_it(): void
    {
        $this->get('/__test/no-browser', ['Referer' => '/registrar/517/print/enrollment-form']);

        $error = session('error');

        // A person who is told "an error occurred" re-clicks the button. This message has to
        // say no file exists yet, that the record on screen is untouched, and what to ask for.
        $this->assertStringContainsString('no file was produced', $error);
        $this->assertStringContainsString('Nothing on the screen changed', $error);
        $this->assertStringContainsString('Chrome or Edge', $error);
        $this->assertStringContainsString('EMS_CHROME_PATH', $error);
    }

    #[Test]
    public function a_request_with_no_page_to_return_to_falls_back_to_the_dashboard(): void
    {
        $this->get('/__test/no-browser')->assertRedirect('/dashboard');
    }

    #[Test]
    public function a_json_caller_gets_a_status_code_rather_than_a_redirect(): void
    {
        // A browser download is a GET navigation, so a caller asking for JSON asks the
        // same question and must get an answer a script can read rather than a redirect.
        $this->getJson('/__test/no-browser')
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }
}
