// Lane 69: execute the shipped JavaScript formatter and compare it with the PHP helper, level by level.
//
// YearLevelVocabularyTest pins the two by reading statusLabel.js's rule out of its source, which is the only
// option in a repository with no JS test suite — but reading a rule is not running it. This does run it, so
// a syntax slip, an ESM export that no longer exists, or an ordinal table that disagrees in execution
// (rather than in text) is caught here rather than on a desk screen.
//
//   node storage/app/scratch/lane69-prove-js-formatter.mjs
//
// It exits 1 naming the level on which the two sides disagree.

// Resolved from this file's own directory: a static `../resources/…` import is relative to the module's
// location, and from storage/app/scratch that is storage/app/resources — a path that does not exist.
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..', '..', '..');
const { formatYearLevel, yearLevelChoices, YEAR_LEVELS } = await import(
    new URL('file:///' + join(root, 'resources', 'js', 'Components', 'ui', 'statusLabel.js').replace(/\\/g, '/'))
);
const php = process.env.PHP_BIN ?? 'C:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe';

const expected = [1, 2, 3, 4, 5, 11, 12, 13, 21, 22, 23, 111];
const js = expected.map((n) => formatYearLevel(n));

// The PHP side, asked once rather than per level. `php -r` takes code WITHOUT the `<?php` tag — including
// it is a parse error, which is how this line's first version failed.
const script = `require '${join(root, 'vendor', 'autoload.php')}';`
    +`use App\\Support\\YearLevel;`
    +`$out=[];foreach(${JSON.stringify(expected)} as $l){$out[]=$l.':'.(YearLevel::format($l) ?? 'null');}`
    +`echo implode('|',$out);`;
const phpOut = execFileSync(php, ['-r', script], { encoding: 'utf8' }).trim();

const pairs = phpOut.split('|');
let bad = 0;
console.log(`levels asked: ${expected.length}`);
console.log(`  js  : ${js.join('  ')}`);
console.log(`  php : ${pairs.map((p) => p.split(':')[1]).join('  ')}`);

for (const [i, level] of expected.entries()) {
    const [, fromPhp] = pairs[i].split(':');
    if (fromPhp !== js[i]) {
        console.error(`  DISAGREE at ${level}: js "${js[i]}" vs php "${fromPhp}"`);
        bad++;
    }
}

// The two absence spellings are deliberately different — a screen dashes, a paper says N/A — so this
// checks only that the screen does not invent a level.
if (formatYearLevel(0) !== '—' || formatYearLevel(null) !== '—' || formatYearLevel(undefined) !== '—') {
    console.error(`  ABSENCE WRONG: 0/null/undefined must all dash, got ${[0, null, undefined].map(formatYearLevel).join(' / ')}`);
    bad++;
}

// Every pick-list in the app offers these labels, so the option a desk chooses is the word the paper prints.
const choiceText = yearLevelChoices().map((c) => c.label).join(', ');
if (choiceText !== '1st Year, 2nd Year, 3rd Year, 4th Year, 5th Year') {
    console.error(`  PICK-LIST WRONG: yearLevelChoices() answers ${choiceText}`);
    bad++;
}
if (YEAR_LEVELS.join(',') !== '1,2,3,4,5') {
    console.error(`  YEAR_LEVELS changed: ${YEAR_LEVELS.join(',')} — five pick-lists depend on this being the levels a desk may record`);
    bad++;
}

console.log(bad === 0 ? 'OK — the shipped module answers the school\'s ordinal, and equals the PHP helper.' : `FAIL — ${bad} disagreement(s).`);
process.exit(bad === 0 ? 0 : 1);
