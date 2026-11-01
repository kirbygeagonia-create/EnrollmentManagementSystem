<?php

namespace Tests\Feature\Desk;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Prompt §14 asks for loading states, and the desk's wait is the visit that refetches a list — not the form submit
 * that saved a record. `storage/app/scratch/lane142-measure-console-states.php` measured that 28 of the 28 pages
 * which repaint rows from their own router call signalled nothing on that refresh: 24 of them had been passing a
 * review because a SAVE button's `form.processing` sits in the same file, on a different control, waiting for a
 * different answer.
 *
 * The fix is one subscription at boot and one caption in the shared table, so there is nothing per-page to forget.
 * These cases read the sources because this repository has no JavaScript test suite — the method the year-level and
 * tone-map pairing already uses — and the last case is the census: it fails the day someone adds a page that
 * refreshes rows outside the shared control without reading the hook, which is precisely the shape that would make
 * the whole mechanism a claim rather than a property.
 */
class DeskRefreshPendingStateTest extends TestCase
{
    private function js(string $rel): string
    {
        $path = base_path($rel);
        $this->assertFileExists($rel);

        return (string) file_get_contents($path);
    }

    #[Test]
    public function the_shared_table_reads_the_router_pending_state_and_says_it_in_words(): void
    {
        $src = $this->js('resources/js/Components/ui/DataTable.jsx');

        $this->assertStringContainsString('useRouterPending', $src,
            'the shared control no longer reads the router pending state, so no desk list reports a wait');
        $this->assertStringContainsString('role="status"', $src,
            'the pending caption is not announced — a dimmed table is nothing to a screen reader (prompt §11)');
        $this->assertStringContainsString('aria-busy', $src,
            'the table region no longer carries the busy state');
        $this->assertMatchesRegularExpression('/Updating[^\n]*rows/', $src,
            'the caption no longer names what is updating; a bare ellipsis is not a labelled state');
    }

    #[Test]
    public function the_watcher_is_armed_once_at_boot_because_the_store_is_empty_otherwise(): void
    {
        $src = $this->js('resources/js/app.jsx');
        $this->assertMatchesRegularExpression('/watchRouterPending\(\);/', $src,
            'boot never registers the listeners, so every page reads a pending value that can only ever be false');

        $hook = $this->js('resources/js/Components/ui/useRouterPending.js');
        $this->assertStringContainsString('if (subscribed)', $hook,
            'the watcher lost its idempotence guard, so a second call double-registers and the state flickers');
    }

    #[Test]
    public function the_pending_store_subscribes_to_the_events_that_actually_settle_a_visit(): void
    {
        $hook = $this->js('resources/js/Components/ui/useRouterPending.js');

        $this->assertStringContainsString("router.on('start'", $hook, 'the store no longer observes the start of a visit');
        $this->assertStringContainsString("'finish'", $hook,
            'a finished visit would leave the desk reading "Updating" forever');
        $this->assertStringContainsString("'cancel'", $hook,
            'a visit superseded by a newer one never reports a plain finish, so the caption would stick');

        // One app-level subscription is the whole design, and it survives only while no reader unsubscribes: this
        // library's unsubscribe drops every listener registered for the event name, so a page holding two tables and
        // unmounting one would silence the other. The test is the guard, not a comment.
        $this->assertStringNotContainsString('router.off', $hook,
            'a per-component unsubscribe would remove every listener for that event, including a sibling table');
    }

    #[Test]
    public function a_page_that_builds_its_own_table_and_refetches_it_reads_the_same_hook(): void
    {
        $src = $this->js('resources/js/Pages/Registrar/Show.jsx');

        $this->assertStringContainsString('useRouterPending', $src,
            'the certificate screen builds its tables by hand, so the shared control cannot cover it and its term '
            .'picker would repaint with no signal at all');
        $this->assertStringContainsString('role="status"', $src,
            'that screen no longer announces its own wait');
    }

    #[Test]
    public function no_page_refreshes_rows_from_the_router_without_a_pending_signal_anywhere_on_its_path(): void
    {
        $control = $this->js('resources/js/Components/ui/DataTable.jsx');
        $booted = str_contains($this->js('resources/js/app.jsx'), 'watchRouterPending(');
        $controlSignals = str_contains($control, 'useRouterPending') && $booted;

        $offenders = [];
        // Not `Pages/**/*.jsx`: PHP's glob has no recursive double star, so that pattern matches nothing and the
        // census would pass by reading no file at all — the failure this repository has already paid for twice.
        $pages = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
            base_path('resources/js/Pages'), \FilesystemIterator::SKIP_DOTS
        ));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
                $pages[] = $f->getPathname();
            }
        }
        $this->assertNotEmpty($pages, 'the walk of resources/js/Pages found no page file, so this census judged nothing');

        foreach ($pages as $file) {
            $src = (string) file_get_contents($file);

            // Only a refresh that repaints THIS screen. `router.get(route('admission.index'))` behind a Cancel
            // button leaves the page — Admission/Create and Exam/Create were both named by the first draft of this
            // case, and neither has a list that goes stale, because the desk is already gone. The marker of an
            // in-place visit is a query object or preserveState, which is what a filter, a sort or a picker sends.
            $inPlace = preg_match('/\brouter\s*\.\s*(?:get|visit)\s*\(\s*route\([^)]*\)\s*,/', $src) === 1
                || str_contains($src, 'preserveState');
            if (! $inPlace) {
                continue;
            }
            if (! preg_match('/\brouter\s*\.\s*(?:get|visit|reload)\s*\(/', $src)) {
                continue;
            }
            $usesControl = preg_match('/<DataTable\b/', $src) === 1;
            $ownSignal = str_contains($src, 'useRouterPending')
                || preg_match('/\brouter\s*\.\s*on\s*\(/', $src) === 1
                || preg_match('/<DataTable\b[^>]*\b(?:busy|loading)=/s', $src) === 1;
            if ($ownSignal || ($usesControl && $controlSignals)) {
                continue;
            }
            $offenders[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
        }

        $this->assertSame([], $offenders, implode(', ', $offenders).' refresh list rows with nothing telling the '
            .'desk the answer is on its way (prompt §14). Render the shared DataTable, or read useRouterPending.');
    }
}
