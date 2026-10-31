<?php

namespace Tests\Feature\Files;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The private uploads folder is not served by any route.
 *
 * Until 2026-10-31 `config/filesystems.php` gave the `local` disk `'serve' => true`, and Laravel therefore
 * registered `GET|HEAD /storage/{path}` and `PUT /storage/{path}` outside every middleware group — their gathered
 * middleware read `(NONE)`. Nothing in `app/` or `resources/` ever called `temporarySignedRoute` or `temporaryUrl`,
 * so no desk was handed a signature, and the only credential standing between a stranger and a named student's
 * papers was an HMAC of the path keyed by `APP_KEY`. That is §28.3 G-50, and the fix took the flag off rather than
 * trusting the door: a path with no route cannot be probed, and a blank key can no longer sign a write into the tree
 * that holds the uploads.
 *
 * What the bytes are reached through is `tests/Feature/Files/StoredFileAccessTest.php` — the applicant's paper and
 * the student's face photo, each authorized by the record it belongs to, each refused for a desk that may not view
 * it. Those cases are why this file may assert an absence without leaving the surface unmeasured.
 *
 * `storage/app/scratch/mutate-g50-served-folder-tests.php` proves all three: it puts `'serve' => true` back in the
 * config and requires the named case to go red, because a guard that only passes on the fixed tree proves nothing.
 */
class ServedUploadPathTest extends TestCase
{
    private const PROBE = 'lane24-signed-upload-probe.txt';

    #[Test]
    public function no_local_disk_asks_the_framework_to_serve_its_folder(): void
    {
        $disks = (array) config('filesystems.disks');
        $served = [];
        foreach ($disks as $name => $disk) {
            if (is_array($disk) && ($disk['driver'] ?? null) === 'local' && ($disk['serve'] ?? false)) {
                $served[] = (string) $name;
            }
        }

        $this->assertSame(
            [],
            $served,
            'disk(s) '.implode(', ', $served).' ask Laravel to serve their folder — the uploads live in one of them (§28.3 G-50)'
        );
    }

    #[Test]
    public function the_two_storage_routes_are_not_registered_at_all(): void
    {
        // The names Laravel gives the served-disk routes. Asserting the absence of the route *names* as well as the
        // URIs matters: a future registration under a different URI shape would pass a URI-only check.
        $this->assertFalse(Route::has('storage.local'), 'the read route for a served disk is registered');
        $this->assertFalse(Route::has('storage.local.upload'), 'the upload route for a served disk is registered');

        $uris = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route): string => (string) $route->uri())
            ->filter(fn (string $uri): bool => str_starts_with($uri, 'storage/'))
            ->values()
            ->all();

        $this->assertSame([], $uris, 'these paths are served straight from disk: '.implode(', ', $uris));
    }

    #[Test]
    public function a_stranger_cannot_put_a_file_into_the_uploads_folder_or_read_one_out_of_it(): void
    {
        Storage::fake('local');

        // Both verbs, each with the flag the write route used to require. Before the flag came off these were 403
        // (a route that answered and refused for want of a signature); now there is nothing to refuse, which is the
        // stronger claim and the one the register carries.
        $this->put('/storage/'.self::PROBE.'?upload=1')->assertNotFound();
        $this->get('/storage/'.self::PROBE)->assertNotFound();

        $this->assertFalse(
            Storage::disk('local')->exists(self::PROBE),
            'An upload reached the private folder without a session, a permission, or a route that exists.'
        );
    }
}
