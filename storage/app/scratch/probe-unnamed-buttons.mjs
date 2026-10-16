#!/usr/bin/env node
/**
 * Icon-only buttons with no accessible name.
 *
 * ESLint here carries reactHooks and reactRefresh only, so nothing in the five gates can see an
 * unnamed control — a screen reader announces these as "button". A button counts as icon-only when
 * its content is SVG/spans-with-no-text and it carries no aria-label, title, aria-title or sr-only
 * text. Brace matching is naive on purpose: this is a candidate list to read, not a verdict.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

const files = [];
const walk = (dir) => {
    for (const e of readdirSync(dir)) {
        const p = join(dir, e);
        if (statSync(p).isDirectory()) walk(p);
        else if (/\.jsx$/.test(e)) files.push(p);
    }
};
walk('resources/js');

const hits = [];
for (const file of files) {
    const src = readFileSync(file, 'utf8');
    const re = /<button\b/g;
    let m;
    while ((m = re.exec(src)) !== null) {
        const start = m.index;
        const openEnd = src.indexOf('>', start);
        if (openEnd === -1) break;
        const tag = src.slice(start, openEnd + 1);
        // Find the matching close, tolerating nested <button> is unnecessary: JSX forbids it.
        const close = src.indexOf('</button>', openEnd);
        if (close === -1) continue;
        const body = src.slice(openEnd + 1, close);
        const text = body
            .replace(/<svg[\s\S]*?<\/svg>/g, '')
            .replace(/<path[\s\S]*?\/>/g, '')
            // A JSX expression is KEPT as possible text: `<button><Icon/><span>{label}</span></button>`
            // is named. Only a body that is nothing but markup-with-no-braces is truly bare.
            .replace(/\{[^{}]*\}/g, ' LABEL ')
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/[{}]/g, '')
            .replace(/<[^>]*>/g, '')
            .trim();
        const named = /aria-label=|title=|aria-labelledby=|sr-only/.test(tag) || /aria-label=|title=|sr-only/.test(body);
        const hasIcon = /<svg/.test(body);
        if (hasIcon && text === '' && !named) {
            const line = src.slice(0, start).split('\n').length;
            hits.push(`${file}:${line}  ${tag.replace(/\s+/g, ' ').slice(0, 96)}`);
        }
    }
}
console.log(hits.join('\n'));
console.log(`\n${hits.length} icon-only buttons with no accessible name, across ${files.length} components`);
