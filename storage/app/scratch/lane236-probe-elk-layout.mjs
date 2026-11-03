#!/usr/bin/env node
/**
 * Can the ELK layout engine be reached from the renderer at all?
 *
 * The owner's ruling on the complete use-case figure is that its connectors must not cross. Dagre draws what it
 * draws; ELK (`@mermaid-js/layout-elk`) does layered routing with orthogonal or diagonal edge shapes, which is the
 * family of algorithm that actually minimises crossings. Mermaid loads it by a dynamic import of a BARE specifier,
 * which a file:// page cannot resolve — so this probe answers the only question that matters before the generator is
 * touched: does an import map in the page make the bare specifier resolve to the local file, and does the resulting
 * SVG have fewer crossings than dagre's?
 *
 * It writes throwaway SVGs under storage/app/scratch and touches nothing in Documentation/.
 * Exit 1 with the cause named when ELK does not load, because a fallback that looks like a layout is the worst
 * possible outcome of an experiment.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve, join } from 'node:path';
import puppeteer from 'puppeteer';

const root = resolve(import.meta.dirname, '..', '..', '..');
const chrome = process.env.CHROME_PATH
  || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const sample = readFileSync(join(root, 'storage/app/scratch/lane236-elk-sample.mmd'), 'utf8');

// Chrome refuses to fetch an ES module over file:// as if it were cross-origin, which is what made the first run
// report "Failed to fetch dynamically imported module" about a file that is plainly on disk. The flag is the point
// of this probe, not a workaround to hide: without it no import map can reach a local layout engine.
const browser = await puppeteer.launch({
  executablePath: chrome,
  headless: true,
  args: ['--no-sandbox', '--disable-gpu', '--allow-file-access-from-files'],
});
const page = await browser.newPage();

const mermaidFile = resolve(root, 'node_modules/mermaid/dist/mermaid.esm.min.mjs').replace(/\\/g, '/');
const elkFile = resolve(root, 'node_modules/@mermaid-js/layout-elk/dist/mermaid-layout-elk.esm.min.mjs').replace(/\\/g, '/');

// `page.setContent()` leaves the document at `about:blank`, and a module map in that document cannot fetch a file://
// module however the flags are set — the first two runs reported "Failed to fetch dynamically imported module" about
// files that are plainly on disk. The document itself has to BE a file:// page, so the harness is written out and
// navigated to.
const html = `<!doctype html><html><head><meta charset="utf-8"><script type="importmap">
{ "imports": {
    "mermaid": "file:///${mermaidFile}",
    "@mermaid-js/layout-elk": "file:///${elkFile}"
} }
</script></head><body><div id="dst"></div></body></html>`;
const pageFile = resolve(root, 'storage/app/scratch/lane236-elk-page.html');
writeFileSync(pageFile, html);
await page.goto('file:///'+pageFile.replace(/\\/g, '/').replace(/^\/?/, ''), { waitUntil: 'load' });

const result = await page.evaluate(async (code) => {
  try {
    const mermaid = (await import('mermaid')).default;
    let elkModule = null;
    try {
      elkModule = await import('@mermaid-js/layout-elk');
    } catch (e) {
      return { ok: false, stage: 'elk-import', why: String(e).slice(0, 200) };
    }
    if (typeof mermaid.registerLayoutLoader === 'function' && elkModule.default) {
      mermaid.registerLayoutLoader([{ name: 'elk', detector: (b) => b === 'elk', loader: async () => elkModule.default }]);
    }
    mermaid.initialize({ startOnLoad: false, securityLevel: 'loose', htmlLabels: true, layout: 'elk', look: 'classic' });
    const out = await mermaid.render('g1', code);
    return { ok: true, svg: out.svg, length: out.svg.length };
  } catch (e) {
    return { ok: false, stage: 'render', why: String(e).slice(0, 300) };
  }
}, sample);

await browser.close();

if (!result.ok) {
  console.error(`ELK_UNREACHABLE at ${result.stage}: ${result.why}`);
  process.exit(1);
}
writeFileSync(resolve(root, 'storage/app/scratch/lane236-elk-sample.svg'), result.svg);
console.log(`rendered with the requested layout: ${result.length} bytes of SVG written to storage/app/scratch/lane236-elk-sample.svg`);
