/**
 * Probe: can mermaid draw a UML stick-figure actor inside a flowchart label, and does the «include» stereotype
 * survive as text? The answer decides how the real figures are built, so it is measured rather than assumed.
 * Writes Documentation/Diagrams/_probe-actor.png and prints what it found.
 */
import { writeFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..', '..');
const chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';

// A stick figure drawn as SVG paths, URL-encoded for a data URI.
const stick = encodeURIComponent(
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 30" width="20" height="30">` +
  `<circle cx="10" cy="5" r="4" fill="none" stroke="#111" stroke-width="1.6"/>` +
  `<line x1="10" y1="9" x2="10" y2="19" stroke="#111" stroke-width="1.6"/>` +
  `<line x1="2" y1="13" x2="18" y2="13" stroke="#111" stroke-width="1.6"/>` +
  `<line x1="10" y1="19" x2="4" y2="29" stroke="#111" stroke-width="1.6"/>` +
  `<line x1="10" y1="19" x2="16" y2="29" stroke="#111" stroke-width="1.6"/></svg>`
);

const variants = {
  imgtag: `flowchart LR\n  A1[<img src="data:image/svg+xml,${stick}" width="20" height="30"/> Admission Officer]\n  U1(["Approve Admission Application"])\n  A1 --- U1`,
  keyword: `flowchart LR\n  actor A2 "Admission Officer"\n  U2(["Approve Admission Application"])\n  A2 --- U2`,
  stereotype: `flowchart LR\n  U3(["Approve"])\n  U4(["Audit the act"])\n  U3 -.->|"«include»"| U4`,
};

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 900, height: 400 });
await page.addScriptTag({ path: join(root, 'node_modules', 'mermaid', 'dist', 'mermaid.min.js') });
await page.evaluate(() => window.mermaid.initialize({ startOnLoad: false, securityLevel: 'loose', htmlLabels: true }));

mkdirSync(join(root, 'Documentation', 'Diagrams'), { recursive: true });
for (const [name, code] of Object.entries(variants)) {
  const r = await page.evaluate(async ({ name, code }) => {
    try {
      const out = await window.mermaid.render(`p_${name}`, code);
      return { svg: out.svg, error: null };
    } catch (e) {
      return { svg: '', error: String(e && e.message ? e.message : e) };
    }
  }, { name, code });
  if (r.error) {
    console.log(`${name.padEnd(12)} PARSE FAILED :: ${r.error.slice(0, 120)}`);
    continue;
  }
  const hasImg = /<img\b/.test(r.svg);
  const hasText = /Admission Officer/.test(r.svg);
  const hasStereotype = /«include»/.test(r.svg);
  await page.setContent(`<html><body style="margin:0;background:#fff">${r.svg}</body></html>`);
  const box = await page.evaluate(() => {
    const s = document.querySelector('svg');
    if (!s) return { w: 0, h: 0 };
    const b = s.getBoundingClientRect();
    return { w: Math.ceil(b.width), h: Math.ceil(b.height) };
  });
  const png = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width: Math.max(box.w, 60), height: Math.max(box.h, 60) } });
  writeFileSync(join(root, 'Documentation', 'Diagrams', `_probe-${name}.png`), png);
  console.log(`${name.padEnd(12)} ok ${box.w}x${box.h} img=${hasImg} label=${hasText} stereotype=${hasStereotype}`);
}
await browser.close();
console.log('probe done');
