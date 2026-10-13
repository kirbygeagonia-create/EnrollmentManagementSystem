<?php

namespace Tests\Feature\Files;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The two routes that answer with no login at all.
 *
 * `config/filesystems.php` gives the `local` disk `'serve' => true`, so Laravel registers
 * `GET|HEAD /storage/{path}` and `PUT /storage/{path}` for it outside every middleware group —
 * `audit-24-routes` printed their gathered middleware as `(NONE)`. Neither route is linked from
 * the application: nothing in `app/` or `resources/` ever calls `temporarySignedRoute` or
 * `temporaryUrl`, so no desk is handed a signature. What these two tests pin is the gate itself,
 * because DEPLOYMENT.md §2 used to claim `storage/app/private` is a folder "which no web path
 * reaches" — that sentence is wrong, and the truth is narrower: a web path reaches it and a
 * signature is what turns the handle.
 *
 * The fake disk keeps the probe out of the folder holding real signed forms; the gate logic runs
 * against the real config, since `ServeFile`/`ReceiveFile` captured their disk array when the
 * routes were registered and only resolve bytes through `Storage::disk('local')`.
 */
class ServedUploadPathTest extends TestCase
{
    private const PROBE = 'lane24-signed-upload-probe.txt';

    #[Test]
    public function a_stranger_with_no_signature_cannot_put_a_file_into_the_served_folder(): void
    {
        Storage::fake('local');

        // `upload=1` is the flag ReceiveFile requires of every write; without a signature behind
        // it the request is refused before a byte is written.
        $this->put('/storage/'.self::PROBE.'?upload=1')->assertForbidden();

        $this->assertFalse(
            Storage::disk('local')->exists(self::PROBE),
            'An unsigned upload wrote into the served folder, so the application key is the only thing left.'
        );
    }

    #[Test]
    public function a_request_signed_with_the_application_key_is_enough_on_its_own(): void
    {
        Storage::fake('local');

        // The counterpart that makes the refusal above mean something: the route does answer, and
        // the sole credential is an HMAC under APP_KEY. No session, no permission, no CSRF token —
        // which is exactly why `ems:preflight` fails a server whose key was never generated, and
        // why a leaked key is a leaked upload folder. Measured the same way in
        // storage/app/scratch/audit-24b-storage-routes.php (204 signed, 403 unsigned).
        $signed = URL::temporarySignedRoute('storage.local.upload', now()->addMinutes(5), [
            'path' => self::PROBE,
            'upload' => 1,
        ], false);

        $this->put($signed, [], ['Content' => 'signed upload probe'])->assertNoContent();

        $this->assertTrue(Storage::disk('local')->exists(self::PROBE));
    }

    #[Test]
    public function reading_the_served_folder_also_needs_a_signature(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put(self::PROBE, 'an applicant paper');

        $this->get('/storage/'.self::PROBE)->assertForbidden();

        $signed = URL::temporarySignedRoute('storage.local', now()->addMinutes(5), [
            'path' => self::PROBE,
        ], false);

        $this->get($signed)->assertOk()->assertStreamed();
    }
}
