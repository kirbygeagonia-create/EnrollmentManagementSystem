<?php

/**
 * Docx pass 246: G-13's proxy answer becomes a setting, and every sentence that described the old wiring follows.
 *
 * The owner ruled on 2026-10-26 that the install-time half of G-13 should be an env value rather than a patch, so
 * `config/trustedproxy.php` now reads `TRUSTED_PROXIES` and `ems:preflight`'s Forwarded scheme row reports the state
 * it finds. That makes four mechanism sentences in §28.2's row, one in §30.6 and the living §1.1 row's date false as
 * written — the class lane 70 names: a present-tense claim about how code behaves today, carrying no status word,
 * no figure and no dead line number, so every bounds and status checker clears it while the build moves under it.
 * Lane 70's operating rule was applied rather than recalled: the whole document was grepped for the sentence shape
 * (`trustProxies`, `trustedproxy`, `X-Forwarded`, `Strict-Transport`, `HSTS`, `proxy`), and it answers one register
 * row and one §30.6 paragraph — not four copies the way N-1 did.
 *
 * Ten `text` edits, no cell rewrite, so the styled runs inside each cell survive. Every needle is asserted to be
 * unique in `word/document.xml` before anything is written, and the pass refuses if any is not.
 *
 *   php storage/app/scratch/refresh-docx-pass246.php          # dry run
 *   php storage/app/scratch/refresh-docx-pass246.php --apply
 */
$root = dirname(__DIR__, 3);
$docx = $root.'/Documentation/EMS Complete Documentation.docx';
$backup = $root.'/storage/app/scratch/backup-docx-pre-pass246.docx';
$apply = in_array('--apply', $argv, true);
require_once $root.'/storage/app/scratch/docx-edit-engine.php';

if (! is_file($docx)) {
    exit("REFUSED: the authority document is not where this pass expects it.\n");
}

$zip = new ZipArchive;
if ($zip->open($docx) !== true) {
    exit("REFUSED: the document cannot be opened as an archive.\n");
}
$xml = (string) $zip->getFromName('word/document.xml');
$zip->close();

$edits = [
    // §28.2 G-13, the mechanism sentence. It used to end "config('trustedproxy.proxies') is unset"; the key now has
    // a file answering it, so the sentence names the file, the env var and the state an installer has to be in.
    [
        "Illuminate\\Http\\Middleware\\TrustProxies leaves \$proxies null, and config('trustedproxy.proxies') is unset, so Symfony discards",
        'Illuminate\Http\Middleware\TrustProxies therefore falls back to config(\'trustedproxy.proxies\'), which since '
            .'2026-10-26 config/trustedproxy.php answers from the TRUSTED_PROXIES env var, and which names nothing on '
            .'an install that has not set it, so Symfony discards',
    ],
    // §28.2 G-13, the "what remains" clause.
    [
        'What remains is the install-time decision of which proxy address to trust, which belongs to whoever hosts the campus install — a machine that does not exist yet.',
        'What remains is the install-time decision of which proxy address to trust, and since 2026-10-26 it has exactly '
            .'one place to be written: TRUSTED_PROXIES in `.env`, one address or several separated by a comma, with no '
            .'PHP edit left for the installer to make. The address itself still belongs to whoever hosts the campus '
            .'install — a machine that does not exist yet.',
    ],
    // §28.2 G-13, the ruling clause: the refusal of the wildcard is unchanged, its form is.
    [
        "Ruled, not fixed in code: the answer must not be trustProxies(at: '*')",
        "Ruled, and ruled again on 2026-10-26 as a setting rather than a patch: the answer must not be trustProxies(at: '*')",
    ],
    // §28.2 G-13, the "Who must act" cell — the actor is the same, the instruction is now a line in .env.
    [
        'name the proxy in trustProxies(at: …), never',
        'name the proxy\'s address in TRUSTED_PROXIES, never',
    ],
    // §28.2 G-13, the remedy cell: the test file is now read in both directions.
    [
        'pinned by tests/Feature/SecurityHeadersTest.php',
        'pinned both ways by tests/Feature/SecurityHeadersTest.php — naming the peer address makes the header appear, '
            .'naming a different address does not',
    ],
    // §30.6, the sentence that counts G-13 as an act rather than an answer. Still true; it now names the place.
    [
        'the proxy an operator must name before HSTS means anything (G-13)',
        'the proxy address an operator must type into TRUSTED_PROXIES before HSTS means anything (G-13)',
    ],
    // §1.1's living row: the date range this issue now spans.
    ['2026-10-22–23 (this issue)', '2026-10-22–26 (this issue)'],
    // §1.1's living row, appended record.
    [
        'take a run-split citation through the cell kind rather than a bare tail.',
        'take a run-split citation through the cell kind rather than a bare tail. Pass 246 (2026-10-26) ruled the '
            .'register\'s last install-time slot into a setting: G-13\'s proxy answer now has one place to be written — '
            .'config/trustedproxy.php reads the TRUSTED_PROXIES env var, so an installer types an address instead of '
            .'editing bootstrap/app.php — and ems:preflight\'s Forwarded scheme row reports which state the install is '
            .'in and refuses the spellings that install nothing: a bare star, a star inside a list, a hostname, a mask '
            .'past its family, and a range keyword. Each shape was measured through the real middleware before the row '
            .'was written to describe it (storage/app/scratch/lane73b-probe-proxy-shapes.php), both directions are '
            .'pinned in tests/Feature/SecurityHeadersTest.php, and storage/app/scratch/mutate-lane73-preflight-proxy.php '
            .'plants a defect in every branch of the row and in both lines of the config file, each of which must make '
            .'the case that owns it fail. The same pass re-took five Preflight.php spans in §18 that pass 245’s own '
            .'insertion had invalidated — the two rules it names had both moved, and every pointer still landed on '
            .'live lines of the same file. One gap is recorded rather than left silent: the passes between the last '
            .'one named above and this one — the rest of lane 70’s pointer queue, lanes 71 to 73’s instruments, and '
            .'the Registrar-window packet — moved text and instruments without adding a note to this cell.',
    ],
    // §18's preflight paragraph: five spans, all written against the numbering before pass 245 inserted a method
    // above them and before this batch rewrote it. Re-taken by reading each construct's declaration and closing
    // brace in the working file, not by arithmetic: checks() 86-104, applicationKeyIsReal() 228-243,
    // servedFolderStaysPrivate() 258-285, the host-zone family 408-484, uploadDiskIsPrivate() 605-628, and the new
    // forwardedSchemeIsDecided() 133-188 reading config/trustedproxy.php:34.
    [
        '(Preflight.php:86-103, the two new rules at :121-136 and :151-178, the disk rule at :498-521, and since lane 54 a host-zone row at :301-422;',
        '(Preflight.php:86-104, the application-key rule at :228-243 and the served-folder rule at :258-285, the disk '
            .'rule at :605-628, and since lane 54 a host-zone row at :408-484; and since 2026-10-26 the list answers '
            .'one more question at :133-188, whether a forwarded scheme can be believed, reading the value it judges '
            .'from config/trustedproxy.php:34;',
    ],
    // §28.2 G-12's remedy cell cites the CSP line that carries img-src; this batch's docblock added three lines
    // above it, so the pointer moved with them (measured against the HEAD export, not by arithmetic).
    ['app/Http/Middleware/SecurityHeaders.php:39', 'app/Http/Middleware/SecurityHeaders.php:42'],
];

$fails = 0;
foreach ($edits as [$from, $to]) {
    $count = substr_count($xml, $from);
    if ($count !== 1) {
        printf("BAD  expected exactly 1 copy of %s, the archive holds %d\n", substr($from, 0, 70), $count);
        $fails++;

        continue;
    }
    if ($from === $to) {
        printf("BAD  the replacement is identical to the needle\n");
        $fails++;

        continue;
    }
    printf("ok   %s\n", substr($from, 0, 64));
}

if ($fails > 0) {
    exit("NOTHING WRITTEN: an assertion above failed, so the document was not touched.\n");
}

$pair = static fn (array $e): array => [$e[0], $e[1]];
$out = docxApply($docx, $backup, array_map($pair, $edits), [], $apply);
printf("%s\n", $apply ? 'WROTE: '.($out ?? 'see the engine report') : 'DRY RUN: the ten needles are unique and the edits are ready');
