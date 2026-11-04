<?php

namespace App\Http\Controllers\Blocking;

use App\Console\Commands\ImportTimetable;
use App\Http\Controllers\Controller;
use App\Models\Academicterms;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Blocking desk's timetable import: upload a sheet, read what it would do, then ask for it.
 *
 * The rule lives in `ems:import:timetable` and nowhere else. This screen calls that command and reads the JSON
 * it writes with `--json=`, because a second implementation of "does this row collide" is the defect this
 * repository has refused in every lane that pairs a screen with a server: the console and the desk must answer
 * the same question with the same number, and a case pins that.
 *
 * Three decisions are stated rather than smoothed over.
 *
 * **The sheet is stored on the private disk, and only for the seconds it takes.** `local` roots at
 * `storage/app/private`, which no route serves (ruling 2026-10-31 took `serve` off that disk), so a department's
 * timetable file is never a public URL. Both the sheet and its preview JSON are deleted by the publish act, which
 * is also what makes the act single-use: a desk that clicks publish twice is answered by the second call finding
 * no sheet at all, and told that, rather than writing the week twice.
 *
 * **Nothing is written by the preview.** The command is called without `--commit`, so the screen shows the same
 * verdicts a person at a terminal would see.
 *
 * **The boundary is the one the schedule acts already ask.** `block.import` is held by the desks that hold
 * `block.schedules.manage`; no college scope is added here, because the single-row schedule path does not apply
 * one either — a bulk act scoped differently from its row-by-row twin is one rule with two answers.
 */
class TimetableImportController extends Controller
{
    use AuthorizesRequests;

    /** Where a sheet waits between the preview and the publish. */
    private const DISK = 'local';

    private const DIR = 'timetable-imports';

    public function index(Request $request): Response
    {
        $this->authorize('blocking.importSheet');

        $this->authorize('blocking.importSheet');

        // The flashed wrapper holds the key and the term beside the payload, because a screen must send back the
        // pair the preview was taken for rather than whatever the term list happens to show first.
        $stored = (array) session('timetableImport', []);
        $preview = $stored['preview'] ?? null;
        if (is_array($preview)) {
            $preview['key'] = $stored['key'] ?? null;
            $preview['termId'] = $stored['termId'] ?? null;
        }

        return Inertia::render('Blocking/TimetableImport', [
            'terms' => Academicterms::with('academicYear')
                ->orderByDesc('startDate')
                ->get()
                ->map(fn (Academicterms $t): array => [
                    // `academicterms` holds no name of its own: the label a desk reads is the year row's
                    // `yearLabel` beside the term's own semester, which is what every other screen composes.
                    'termId' => (int) $t->termId,
                    'label' => $t->academicYear->yearLabel.' '.$this->semester($t),
                ]),
            'columns' => ImportTimetable::headerLine(),
            'preview' => $preview,
        ]);
    }

    private function semester(Academicterms $term): string
    {
        return $term->semester->value;
    }

    public function preview(Request $request): RedirectResponse
    {
        $this->authorize('blocking.importSheet');

        $validated = $request->validate([
            'sheet' => ['required', 'file', 'max:5120'],
            'termId' => ['required', 'integer', 'exists:academicterms,termId'],
        ]);

        // The owner ruled the input a spreadsheet or a text sheet, so a picture of a timetable is refused rather
        // than OCR'd. The extension is checked here rather than through `mimes:` because that rule asks a mime
        // guesser what `csv` is, and a guess that answers nothing refuses every real sheet.
        $extension = strtolower((string) pathinfo($request->file('sheet')->getClientOriginalName(), PATHINFO_EXTENSION));
        if (! in_array($extension, ['csv', 'txt'], true)) {
            throw ValidationException::withMessages([
                'sheet' => 'A timetable sheet is a CSV or a text file. Save the spreadsheet as CSV and upload that; an image or a PDF is not read.',
            ]);
        }

        $key = bin2hex(random_bytes(16));
        Storage::disk(self::DISK)->putFileAs(self::DIR, $request->file('sheet'), $key.'.csv');

        $preview = $this->run($key, (int) $validated['termId'], commit: false, fillGaps: false);

        return redirect()->route('blocking.import')->with('timetableImport', [
            'key' => $key,
            'termId' => (int) $validated['termId'],
            'preview' => $preview,
        ]);
    }

    public function publish(Request $request): RedirectResponse
    {
        $this->authorize('blocking.importSheet');

        $validated = $request->validate([
            // A hex token this controller minted. The pattern is the guard: a key from a request is not
            // allowed to carry a path, however the rest of the code treats it.
            'key' => ['required', 'string', 'regex:/^[a-f0-9]{32}$/'],
            'termId' => ['required', 'integer', 'exists:academicterms,termId'],
        ]);

        $key = $validated['key'];
        $termId = (int) $validated['termId'];

        $result = $this->run($key, $termId, commit: true, fillGaps: true);

        // The act is spent either way. Leaving the sheet behind would let a resubmit publish a second copy of a
        // week that was already booked, which is the state the whole preview exists to prevent.
        foreach ([$key.'.csv', $key.'.json'] as $gone) {
            Storage::disk(self::DISK)->delete(self::DIR.'/'.$gone);
        }

        return redirect()->route('blocking.import')
            ->with('timetableImport', ['key' => null, 'termId' => $termId, 'preview' => $result])
            ->with($result['published'] ?? false ? 'success' : 'error', $result['message']);
    }

    /**
     * Ask the command, and read what it decided.
     *
     * @return array<string, mixed> the command's own payload, or a payload-shaped refusal when it wrote none
     */
    private function run(string $key, int $termId, bool $commit, bool $fillGaps): array
    {
        $csv = Storage::disk(self::DISK)->path(self::DIR.'/'.$key.'.csv');
        $json = Storage::disk(self::DISK)->path(self::DIR.'/'.$key.'.json');

        if (! is_readable($csv)) {
            return [
                'outcome' => 'missing',
                'published' => false,
                'message' => 'That sheet is no longer here, so nothing was written. Upload it again to preview the rows.',
                'rows' => [],
                'reasons' => [],
                'read' => 0,
                'refused' => 0,
                'schedules' => 0,
                'meetings' => 0,
                'skippedForCollision' => [],
                'advisories' => [],
            ];
        }

        $options = ['--csv' => $csv, '--term' => (string) $termId, '--json' => $json];
        if ($commit) {
            $options['--commit'] = true;
        }
        if ($fillGaps) {
            $options['--fill-gaps'] = true;
        }

        $status = Artisan::call('ems:import:timetable', $options);
        $console = trim(preg_replace('/\e\[[0-9;]*m/', '', Artisan::output()) ?? '');

        $payload = json_decode((string) @file_get_contents($json), true);
        if (! is_array($payload)) {
            // The command refused before it reached a row verdict (a missing term, a header it cannot map).
            // The screen shows its sentence rather than an empty table, which would read as a clean preview.
            $payload = [
                'outcome' => 'refused',
                'published' => false,
                'read' => 0,
                'refused' => 0,
                'schedules' => 0,
                'meetings' => 0,
                'skippedForCollision' => [],
                'advisories' => [],
                'rows' => [],
                'reasons' => [],
                'message' => $console === '' ? 'The import read nothing and gave no reason.' : $console,
            ];
        } else {
            $payload['published'] = $status === 0 && ($payload['outcome'] ?? '') === 'published';
            $payload['status'] = $status;
            $payload['message'] ??= $this->summary($payload, $status);
        }

        @unlink($json);

        return $payload;
    }

    /**
     * One sentence for the desk, built from the payload's own counts rather than from what the act intended.
     */
    private function summary(array $payload, int $status): string
    {
        $skipped = count($payload['skippedForCollision'] ?? []);

        if ($status !== 0) {
            return 'Nothing was published: '.($payload['refused'] ?? 0).' row(s) carry a reason the sheet cannot be written around.';
        }

        return $skipped === 0
            ? 'Published '.$payload['meetings'].' meeting line(s) over '.$payload['schedules'].' schedule(s).'
            : 'Published '.$payload['meetings'].' meeting line(s) over '.$payload['schedules'].' schedule(s); '
                .$skipped.' row(s) were skipped because their hour is already booked.';
    }
}
