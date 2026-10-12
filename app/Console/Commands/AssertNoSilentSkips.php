<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use SimpleXMLElement;

/**
 * Refuse a CI run in which a check did not execute.
 *
 * A skipped test is not a passing test, and a suite that prints "660 passed, 5 skipped" reads green to
 * everybody. The one that mattered was measured on 2026-10-12: `PrintRendererTest` locates a browser on
 * the runner image and asserts it is found — and it is written to SKIP when no browser exists. An image
 * that drops or relocates Chrome would therefore have made CI green while quietly subtracting proof. This
 * command turns that silence into a red job.
 *
 * It reads the JUnit log(s) the test steps already write. It decides nothing about the code: it compares
 * the skips that happened against the short list below, each with the reason it is allowed, and fails on
 * anything else — including an allowed test that has gone missing entirely, because a renamed or deleted
 * check would otherwise pass by being absent.
 *
 * Several files may be given, because CI runs the end-to-end walkthrough in its own step against a
 * database it seeded just beforehand — a `RefreshDatabase` test elsewhere in the suite migrates fresh and
 * would otherwise have wiped the reference rows those legs read. Skips are judged per file and presence
 * across the union, so an allowlisted test satisfied in either log is enough.
 */
class AssertNoSilentSkips extends Command
{
    protected $signature = 'ems:ci:no-silent-skips {files?* : the JUnit log(s) written by the test steps}';

    protected $description = 'Fail the run if a test skipped for a reason this project has not agreed to';

    /**
     * The skips CI is allowed to have, keyed by test class.
     *
     * `*` means every test in that class may skip for the stated reason. Anything added here must say
     * why, because the point of the list is that no other skip is normal.
     */
    private const ALLOWED = [
        'Tests\Feature\E2E\EnrollmentWalkthroughTest' => [
            'tests' => '*',
            'reason' => 'it needs a live seeded MySQL database — CI supplies it as E2E_DB_DATABASE, and the default is `ems`',
        ],
        'Tests\Feature\Console\PreflightTest' => [
            'tests' => ['An absent print folder is not a fault because the print driver makes it'],
            'reason' => 'storage/app/prints is gitignored, so a fresh checkout has nothing to move aside',
        ],
    ];

    public function handle(): int
    {
        $files = (array) $this->argument('files');

        if ($files === []) {
            $files = ['junit.xml'];
        }

        $skipped = [];
        $present = [];

        foreach ($files as $file) {
            if ($this->readLog((string) $file, $skipped, $present) === self::FAILURE) {
                return self::FAILURE;
            }
        }

        $unexpected = 0;

        foreach ($skipped as $case) {
            $allowed = $this->allowedFor($case['class'], $case['name']);

            if ($allowed === null) {
                $unexpected++;
                $this->error($case['class'].'::'.$case['name'].' skipped, and no ruling allows it.');
                $this->line('        A check that did not run cannot vouch for anything. Install what it needs, or record why this machine is allowed to be without it.');

                continue;
            }

            $this->line('  allowed skip  '.$case['name'].'  — '.$allowed);
        }

        $problems = $this->missingAllowed($present);

        foreach ($problems as $problem) {
            $this->error($problem);
        }

        if ($unexpected > 0 || $problems !== []) {
            $this->newLine();
            $this->error('The suite is not what it claims: '.$unexpected.' unagreed skip(s) and '.count($problems).' allowlist problem(s).');

            return self::FAILURE;
        }

        $this->info('Skips observed: '.count($skipped).'; every one is on the allowlist with a reason, and no allowlisted test is missing.');

        return self::SUCCESS;
    }

    /**
     * Fold one JUnit log into the running sets. Takes the skip list and the present-testcase map by
     * reference so several logs are judged as one body of evidence.
     *
     * @param  array<int, array{class: string, name: string}>  $skipped
     * @param  array<string, bool>  $present
     */
    private function readLog(string $path, array &$skipped, array &$present): int
    {
        if (! is_file($path)) {
            $this->error($path.' is missing, so nothing can be said about what skipped.');

            return self::FAILURE;
        }

        $xml = simplexml_load_file($path);

        if (! $xml instanceof SimpleXMLElement) {
            $this->error($path.' could not be parsed as XML.');

            return self::FAILURE;
        }

        $found = $this->skippedCases($xml);
        $count = 0;

        foreach ($xml->xpath('//testcase') ?: [] as $case) {
            $present[(string) $case['class'].'::'.(string) $case['name']] = true;
            $count++;
        }

        foreach ($found as $case) {
            $skipped[] = $case;
        }

        $this->line('  read '.$path.': '.$count.' testcase(s), '.count($found).' skipped');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{class: string, name: string}>
     */
    private function skippedCases(SimpleXMLElement $xml): array
    {
        $cases = [];

        foreach ($xml->xpath('//testcase[skipped]') ?: [] as $case) {
            $cases[] = [
                'class' => (string) $case['class'],
                'name' => (string) $case['name'],
            ];
        }

        return $cases;
    }

    /** The reason a skip is permitted, or null when nothing allows it. */
    private function allowedFor(string $class, string $name): ?string
    {
        $rule = self::ALLOWED[$class] ?? null;

        if ($rule === null) {
            return null;
        }

        if ($rule['tests'] === '*') {
            return $rule['reason'];
        }

        return in_array($name, $rule['tests'], true) ? $rule['reason'] : null;
    }

    /**
     * Every allowlisted test must still appear in the evidence. A rename or a deletion would satisfy the
     * rule by producing no skip at all — the same subtraction of proof, arriving the other way.
     *
     * @param  array<string, bool>  $present
     * @return array<int, string>
     */
    private function missingAllowed(array $present): array
    {
        $problems = [];

        foreach (self::ALLOWED as $class => $rule) {
            if ($rule['tests'] === '*') {
                $anyOfThisClass = false;

                foreach (array_keys($present) as $key) {
                    if (str_starts_with($key, $class.'::')) {
                        $anyOfThisClass = true;

                        break;
                    }
                }

                if (! $anyOfThisClass) {
                    $problems[] = $class.' is allowed to skip every test, yet no test of that class appears in the logs.';
                }

                continue;
            }

            foreach ($rule['tests'] as $name) {
                if (! isset($present[$class.'::'.$name])) {
                    $problems[] = 'The allowlist expects '.$name.' to exist in '.$class.', and the logs have no such test.';
                }
            }
        }

        return $problems;
    }
}
