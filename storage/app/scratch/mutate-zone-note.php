<?php
/**
 * Make Preflight::zoneNote() refuse to warn and require that the new test dies.
 * A guard that cannot fail guards nothing, and these three tests were written by the same hand as the code.
 */
$root = dirname(__DIR__, 3);
$php = 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';
$f = $root.'/app/Console/Commands/Preflight.php';
$base = file_get_contents($f);
$find = "        if (\$session === '' || \$global === '' || \$session === \$global) {";
$fix  = "        if (true) { // MUTANT: the note can never be written";
if (substr_count($base, $find) !== 1) {
    printf("NOT USABLE: anchor occurs %d time(s)\n", substr_count($base, $find));
    exit(1);
}
file_put_contents($f, str_replace($find, $fix, $base));
$out = []; $code = 0;
exec(sprintf('cd "%s" && "%s" artisan test tests/Feature/Console/PreflightTest.php 2>&1', $root, $php), $out, $code);
file_put_contents($f, $base);
$body = implode("\n", $out);
preg_match_all('/⨯[^\n]*/u', $body, $m);
printf("exit=%d\nfailing tests: %s\n", $code, implode(' | ', $m[0] ?: ['(none)']));
$ok = $code !== 0 && (bool) preg_match('/carries the zone/u', $body);
clearstatcache(true, $f);
$restored = file_get_contents($f) === $base;
printf("killed by the intended test: %s\nfile restored byte-for-byte: %s\n", $ok ? 'yes' : 'NO', $restored ? 'yes' : 'NO');
exit($ok && $restored ? 0 : 1);
