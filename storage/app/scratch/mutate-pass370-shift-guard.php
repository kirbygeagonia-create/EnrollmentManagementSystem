<?php

// Falsification driver for refresh-docx-pass370.php: prepend ONE real line to the HEAD export so every cited line
// number shifts by one, run the pass, and require it to refuse. The first attempt did this with `php -r` and a
// "\n" inside a double-quoted string, and MSYS rewrote the escape to a literal `/n`, so the plant merged into the
// shebang line, moved nothing, and the guard passed for a reason that was not its own. A plant must be verified
// against the bytes the leg claims to have written — that is what this file does before it runs anything.
$root = dirname(__DIR__, 3);
$export = __DIR__.'/lane133-head-referencedatacontroller.php';
$saved = __DIR__.'/lane133-head-saved.php';

$original = (string) file_get_contents($export);
if ($original === '') {
    fwrite(STDERR, "REFUSED: the HEAD export is empty; nothing to perturb.\n");

    exit(1);
}
copy($export, $saved);

$plant = '// planted shift'.chr(10);
$written = file_put_contents($export, $plant.$original);
if ($written !== strlen($plant.$original)) {
    fwrite(STDERR, "REFUSED: the plant wrote {$written} bytes, expected ".strlen($plant.$original).".\n");
    copy($saved, $export);

    exit(1);
}

// Verify the plant actually moved the bytes the pass compares: HEAD's line 47 must now be what was line 46.
$lines = explode(chr(10), (string) file_get_contents($export));
$before = explode(chr(10), $original);
if ($lines[46] !== ($before[45] ?? null)) {
    fwrite(STDERR, "REFUSED: the plant did not shift line 47 (got [{$lines[46]}]). The leg cannot fail as written.\n");
    copy($saved, $export);

    exit(1);
}
printf("plant verified: HEAD line 47 now reads [%s] where the pass expects [%s]\n",
    trim($lines[46]), trim($before[46] ?? ''));

$code = 0;
$out = [];
// No escapeshellarg(): on this box exec() runs cmd.exe, which does not strip single quotes, so the quoted path
// arrives with the quotes inside it and php receives an argument that names no file. Neither path has a space.
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$pass = $root.'/storage/app/scratch/refresh-docx-pass370.php';
exec($php.' '.$pass.' 2>&1', $out, $code);
copy($saved, $export);
unlink($saved);

$body = implode(chr(10), $out);
echo substr($body, 0, 400).chr(10);

if ($code === 0) {
    fwrite(STDERR, "BAD: the pass accepted a HEAD export whose every cited line had shifted — the comparison is inert.\n");

    exit(1);
}
if (! str_contains($body, 'REFUSED: line 47')) {
    fwrite(STDERR, "BAD: the pass exited {$code} but did not name line 47; it refused for a different reason.\n");

    exit(1);
}

echo "ok  the byte-equality guard fires: a shifted HEAD export is refused by number, not by luck.\n";
