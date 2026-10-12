<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ems:ci:no-silent-skips exists because a skipped test is invisible in a green job.
 *
 * Measured on the live CI log 2026-10-12: both backend jobs print "5 skipped, 660 passed" and call that
 * success. Four of those skips are the E2E legs reaching for a database the container does not have, and
 * the fifth is the prints-folder row in a checkout that has no folder. Both are known and stated here.
 * What must never join them quietly is a check the runner image is supposed to satisfy — the browser
 * locator being the concrete case, since it skips rather than fails when no Chrome is found.
 *
 * Each test feeds the command a JUnit log built to match PHPUnit's real output
 * (`<testcase …><skipped/></testcase>`), so the rules are pinned without a browser, a database, or a run.
 */
class AssertNoSilentSkipsTest extends TestCase
{
    private string $lastOutput = '';

    /**
     * Hand the guard one JUnit log or several. CI now writes two — the main suite, and the walkthrough's
     * own run against a database it seeded just beforehand — and the command judges them as one body of
     * evidence, so a test present in either log counts as present.
     */
    private function runAgainst(string ...$logs): int
    {
        $paths = [];

        foreach ($logs as $i => $xml) {
            $path = tempnam(sys_get_temp_dir(), 'junit-').'-'.$i.'.xml';
            file_put_contents($path, $xml);
            $paths[] = $path;
        }

        try {
            $code = Artisan::call('ems:ci:no-silent-skips', ['files' => $paths]);
            $this->lastOutput = Artisan::output();
        } finally {
            foreach ($paths as $path) {
                @unlink($path);
            }
        }

        return $code;
    }

    private function log(array $suites): string
    {
        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<testsuites>\n";

        foreach ($suites as $class => $cases) {
            $skipped = count(array_filter($cases, fn ($c) => $c[1]));
            $out .= '  <testsuite name="'.$class.'" tests="'.count($cases).'" skipped="'.$skipped.'">'."\n";

            foreach ($cases as [$name, $isSkipped]) {
                $out .= '    <testcase name="'.$name.'" class="'.$class.'" time="0.1"'.
                    ($isSkipped ? '><skipped/></testcase' : '/').">\n";
            }

            $out .= "  </testsuite>\n";
        }

        return $out."</testsuites>\n";
    }

    /** The exact shape CI produces today: the four E2E legs skipped, the prints row skipped, nothing else. */
    private function ciShapedLog(): string
    {
        return $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year path walks the whole pipeline', true],
                ['Continuing path skips assessment', true],
                ['Transferee path credits a passing transfer', true],
                ['Shifter path grants without an examination', true],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['A server that meets the documented requirements passes', false],
                ['An absent print folder is not a fault because the print driver makes it', true],
            ],
            'Tests\Feature\Errors\PrintRendererTest' => [
                ['An unconfigured server still finds an installed browser', false],
                ['A server with no browser refuses before the document is built', false],
            ],
        ]);
    }

    #[Test]
    public function the_shape_ci_produces_today_is_accepted_and_each_allowed_skip_is_shown_with_its_reason(): void
    {
        $this->assertSame(0, $this->runAgainst($this->ciShapedLog()));

        $this->assertStringContainsString('allowed skip  An absent print folder is not a fault', $this->lastOutput);
        $this->assertStringContainsString('gitignored', $this->lastOutput);
        $this->assertStringContainsString('E2E_DB_DATABASE', $this->lastOutput);
        $this->assertStringContainsString('every one is on the allowlist', $this->lastOutput);
        $this->assertStringNotContainsString('unagreed', $this->lastOutput);
    }

    #[Test]
    public function a_browser_check_that_skipped_fails_the_run_by_name(): void
    {
        $xml = $this->log([
            'Tests\Feature\Errors\PrintRendererTest' => [
                ['An unconfigured server still finds an installed browser', true],
            ],
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year path walks the whole pipeline', true],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['An absent print folder is not a fault because the print driver makes it', true],
            ],
        ]);

        $this->assertSame(1, $this->runAgainst($xml));

        $this->assertStringContainsString('PrintRendererTest::An unconfigured server still finds an installed browser skipped', $this->lastOutput);
        $this->assertStringContainsString('no ruling allows it', $this->lastOutput);
    }

    #[Test]
    public function any_unexpected_skip_fails_even_when_it_is_not_a_browser(): void
    {
        $xml = $this->log([
            'Tests\Feature\SomeNewSuiteTest' => [
                ['A check nobody expected to be skipped', true],
            ],
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year path walks the whole pipeline', true],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['An absent print folder is not a fault because the print driver makes it', true],
            ],
        ]);

        $this->assertSame(1, $this->runAgainst($xml));
        $this->assertStringContainsString('SomeNewSuiteTest::A check nobody expected to be skipped', $this->lastOutput);
        $this->assertStringContainsString('1 unagreed skip(s)', $this->lastOutput);
    }

    #[Test]
    public function an_allowlisted_test_that_has_been_renamed_or_deleted_is_reported_loudly(): void
    {
        // Silence here would be the failure: a renamed check disappears from the log, produces no skip,
        // and the allowlist would then describe nothing while the job stayed green.
        $xml = $this->log([
            'Tests\Feature\Errors\PrintRendererTest' => [
                ['An unconfigured server still finds an installed browser', false],
            ],
        ]);

        $this->assertSame(1, $this->runAgainst($xml));
        $this->assertStringContainsString('EnrollmentWalkthroughTest is allowed to skip every test', $this->lastOutput);
        $this->assertStringContainsString('the logs have no such test', $this->lastOutput);
    }

    #[Test]
    public function a_missing_log_is_refused_rather_than_passing_for_want_of_evidence(): void
    {
        $this->assertSame(1, Artisan::call('ems:ci:no-silent-skips', ['files' => ['no-such-junit.xml']]));
        $this->assertStringContainsString('is missing', Artisan::output());
    }

    #[Test]
    public function an_allowlisted_test_seen_in_one_log_satisfies_the_rule_across_both(): void
    {
        // The second half of the CI shape: the walkthrough runs in its own step, so the main log still
        // carries its skip and the second log carries its passes. Presence must be judged over the union,
        // or the guard would cry wolf about a Preflight row that simply is not in the second file.
        $main = $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year with two stage entrance exam completes full pipeline', true],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['An absent print folder is not a fault because the print driver makes it', true],
            ],
        ]);

        $walkthrough = $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year with two stage entrance exam completes full pipeline', false],
                ['Shifter with credit transfer completes pipeline', false],
            ],
        ]);

        $this->assertSame(0, $this->runAgainst($main, $walkthrough));

        $this->assertStringContainsString('Skips observed: 2', $this->lastOutput);
        $this->assertStringContainsString('read ', $this->lastOutput);
    }

    #[Test]
    public function a_skip_in_the_second_log_is_refused_too(): void
    {
        $main = $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year with two stage entrance exam completes full pipeline', true],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['An absent print folder is not a fault because the print driver makes it', true],
            ],
        ]);

        $walkthrough = $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year with two stage entrance exam completes full pipeline', false],
            ],
            'Tests\Feature\Errors\PrintRendererTest' => [
                ['An unconfigured server still finds an installed browser', true],
            ],
        ]);

        $this->assertSame(1, $this->runAgainst($main, $walkthrough));
        $this->assertStringContainsString('PrintRendererTest::An unconfigured server still finds an installed browser skipped', $this->lastOutput);
    }

    #[Test]
    public function a_clean_log_with_no_skips_at_all_is_green(): void
    {
        $xml = $this->log([
            'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
                ['First year path walks the whole pipeline', false],
            ],
            'Tests\Feature\Console\PreflightTest' => [
                ['An absent print folder is not a fault because the print driver makes it', false],
            ],
        ]);

        $this->assertSame(0, $this->runAgainst($xml));
        $this->assertStringContainsString('Skips observed: 0', $this->lastOutput);
    }
}
