/**
 * Use-case figures for the Enrollment Management System, drawn from the model the guide already holds.
 *
 * Why a generator and not a drawing tool: `Documentation/Use-Case-Diagram-Guide.md` carries the system's use-case
 * graphs as mermaid blocks — one global boundary diagram and one per desk. A hand-drawn figure would be a second
 * definition of that model and would rot the day a desk changes, which is the defect this repository removes
 * everywhere else (year levels, tone maps, citation attribution, the ERD). So this script PARSES those blocks into
 * one model of actors, use cases, associations and «include»/«extend» dependencies, and renders four kinds of figure
 * from it:
 *
 *   1. one per desk (twelve),
 *   2. the global system boundary,
 *   3. one complete diagram over every desk, panels merged,
 *   4. one PER ACTOR — that actor's associations, the include and extend chains hanging off each of them, and the
 *      secondary actors that share those use cases.
 *
 * Two things are drawn properly rather than approximated, both measured before use: actors are UML stick figures
 * (an inline SVG in the label — the probe that proved it is `lane207-probe-actor-shape.mjs`), and the relationship
 * stereotypes are the real «include» / «extend» words, because the guide writes them as `"<<include>>"` and
 * mermaid's HTML labels swallow that pair of angle brackets into an empty box.
 *
 * Refusals, each named — a figure drawn from nothing still looks like a figure:
 *   - no block found, or a desk heading with no block beside it;
 *   - an edge whose endpoint was never declared;
 *   - a use case with no edge at all, or an actor with no association;
 *   - a mermaid parse error, a zero-sized box, or a PNG under the byte floor;
 *   - an unknown flag.
 *
 * Usage: node storage/app/scratch/lane208-use-case-figures.mjs [--out=<dir>] [--chrome=<path>] [--report-only]
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, unlinkSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..', '..');
const guide = join(root, 'Documentation', 'Use-Case-Diagram-Guide.md');

let outDir = join(root, 'Documentation', 'Diagrams');
let chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
let reportOnly = false;
for (const a of process.argv.slice(2)) {
  if (a.startsWith('--out=')) outDir = resolve(root, a.slice(6));
  else if (a.startsWith('--chrome=')) chrome = a.slice(9);
  else if (a === '--report-only') reportOnly = true;
  else fail(`this tool reads --out=, --chrome= and --report-only; it was given [${a}].`);
}
function fail(m) {
  console.error(`REFUSED: ${m}`);
  process.exit(1);
}
if (!existsSync(guide)) fail(`no use-case guide at ${guide}`);
if (!existsSync(chrome)) fail(`no Chrome at ${chrome} — name it with --chrome=, do not fall back to a guess`);

const STICK = encodeURIComponent(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 30" width="20" height="30">' +
  '<circle cx="10" cy="5" r="4" fill="none" stroke="#111" stroke-width="1.6"/>' +
  '<line x1="10" y1="9" x2="10" y2="19" stroke="#111" stroke-width="1.6"/>' +
  '<line x1="2" y1="13" x2="18" y2="13" stroke="#111" stroke-width="1.6"/>' +
  '<line x1="10" y1="19" x2="4" y2="29" stroke="#111" stroke-width="1.6"/>' +
  '<line x1="10" y1="19" x2="16" y2="29" stroke="#111" stroke-width="1.6"/></svg>'
);
const ACTOR_IMG = `<img src="data:image/svg+xml,${STICK}" width="18" height="27"/> `;

// One cleaning pass, used by the parser AND by every figure source, so a label cannot read one way in the model and
// another way on the page. Three things are fixed: the mojibake glyph the guide carries in front of actor names, a
// pictograph marker that headless Chrome renders as a tofu box, and the relationship stereotypes the guide writes
// as `"<<include>>"`, which mermaid's HTML labels swallow into an empty box — UML spells them «include» / «extend».
function clean(code) {
  return code
    .replace(/옷/g, '')
    .replace(/[\u{FE0F}\u{200D}]/gu, '')
    .replace(/(\[\s*")[\u{1F300}-\u{1FAFF}\u{2600}-\u{27BF}\u{2190}-\u{21FF}\u{FE0F}\u{200D}\s]+/gu, '$1')
    .replace(/<<\s*include\s*>>/gi, '«include»')
    .replace(/<<\s*extend\s*>>/gi, '«extend»');
}

// ---------------------------------------------------------------- 1. read the blocks
const lines = readFileSync(guide, 'utf8').split('\n');
const rawBlocks = [];
let section = null;
let inFence = false;
let buf = [];
let skipped = 0;
let skippedStyle = 0;
for (const line of lines) {
  if (!inFence && /^#{2,3}\s+/.test(line)) {
    const title = line.replace(/^#+\s*/, '').trim();
    section = /^(Desk \d+|3\.\s)/.test(title) ? title : null;
  }
  if (/^```mermaid\s*$/.test(line)) {
    if (inFence) fail('a mermaid fence opened inside another one');
    inFence = true;
    buf = [];
    continue;
  }
  if (inFence && /^```\s*$/.test(line)) {
    inFence = false;
    if (section === null) { skipped++; continue; }
    rawBlocks.push({ section, code: buf.join('\n') });
    continue;
  }
  if (inFence) buf.push(line);
}
if (inFence) fail('the guide ends inside an unclosed mermaid fence');
if (rawBlocks.length === 0) fail('no mermaid block was found');
if (skipped > 0) console.log(`note: ${skipped} block(s) outside the global and desk sections are notation examples, not figures`);

const deskHeadings = lines.filter((l) => /^###\s+Desk \d+/.test(l)).map((l) => l.replace(/^#+\s*/, '').trim());
const deskBlocks = rawBlocks.filter((b) => /^Desk \d+/.test(b.section));
for (const h of deskHeadings) if (!deskBlocks.some((b) => b.section === h)) fail(`desk heading [${h}] has no mermaid block beside it`);
const deskNumber = (s) => (/^Desk\s+(\d+)/.test(s) ? /^Desk\s+(\d+)/.exec(s)[1].padStart(2, '0') : '00');

// ---------------------------------------------------------------- 2. parse into ONE model
const nodes = new Map();   // key `${desk}|${id}` -> {desk,id,label,kind}
const edges = [];          // {desk, from, to, kind, label}  kind: association | include | extend | dependency
const actorLabels = new Map(); // label -> first key
for (const b of rawBlocks) {
  const desk = deskNumber(b.section);
  const code = clean(b.code);
  let depth = 0;
  const panels = [];
  for (const line of code.split('\n')) {
    const sg = /^\s*subgraph\s+([A-Za-z][\w]*)\s*(?:\["([^"]*)"\]|\[([^\]]*)\])?\s*$/.exec(line);
    if (sg) { depth++; panels.push(sg[2] ?? sg[3] ?? sg[1]); continue; }
    if (/^\s*end\s*$/.test(line)) { depth = Math.max(0, depth - 1); panels.pop(); continue; }
    if (/^\s*(flowchart|graph)\s+/.test(line) || line.trim() === '') continue;
    // Mermaid comments and styling carry no graph meaning; they are counted, not dropped quietly, because a parser
    // that skips an unrecognised line without saying so is how a model loses a node and still prints a census.
    if (/^\s*%%/.test(line)) continue;
    if (/^\s*(direction|classDef|class |style |linkStyle|click )/.test(line)) { skippedStyle++; continue; }

    const decl = /^\s*([A-Za-z][\w]*)\s*(\[\s*"([^"]*)"\s*\]|\(\[\s*"?([^"\]]*)"?\s*\]\))/m.exec(line);
    if (decl && !/(\-\-\-|\-\->|-\.->)/.test(line)) {
      const id = decl[1];
      const label = (decl[3] ?? decl[4] ?? id).trim();
      // Classified by SHAPE, not by nesting: the guide draws actors as ["..."] and use cases as (["..."]), and the
      // global block nests panels three deep, so "inside a subgraph" says nothing about what a node is.
      const isUseCase = /^\(\[/.test(decl[2]);
      const kind = isUseCase ? 'usecase' : 'actor';
      const key = `${desk}|${id}`;
      if (nodes.has(key)) fail(`${key} is declared twice in [${b.section}]`);
      nodes.set(key, { desk, id, label, kind, isDesk: /^Desk \d+/.test(b.section) });
      if (kind === 'actor') { if (!actorLabels.has(label)) actorLabels.set(label, key); }
      continue;
    }
    const e = /^\s*([A-Za-z][\w]*)\s*(---|-\.->|-->)(?:\s*\|\s*(?:"([^"]*)"|([^|"]+))\s*\|)?\s*([A-Za-z][\w]*)\s*$/.exec(line);
    if (!e) fail(`line in [${b.section}] is neither a declaration nor an edge: ${line.trim()}`);
    const [, from, arrow, qLabel, plainLabel, to] = e;
    const label = (qLabel ?? plainLabel ?? '').trim();
    let kind = 'association';
    if (arrow === '-.->') {
      if (/include/i.test(label)) kind = 'include';
      else if (/extend/i.test(label)) kind = 'extend';
      else kind = 'dependency';
    }
    if (!nodes.has(`${desk}|${from}`) || !nodes.has(`${desk}|${to}`)) {
      fail(`edge ${from} ${arrow} ${to} in [${b.section}] names a node that is never declared`);
    }
    edges.push({ desk, from, to, kind, label });
  }
}

// Integrity: nothing drawn may float free.
const connected = new Set();
for (const e of edges) { connected.add(`${e.desk}|${e.from}`); connected.add(`${e.desk}|${e.to}`); }
const freeUseCases = [...nodes.values()].filter((n) => n.kind === 'usecase' && !connected.has(`${n.desk}|${n.id}`));
if (freeUseCases.length > 0) {
  fail(`${freeUseCases.length} use case(s) have no edge at all, e.g. ` +
    freeUseCases.slice(0, 3).map((n) => `${n.desk}:${n.label}`).join(', '));
}
const assocKeys = new Set(edges.filter((e) => e.kind === 'association').map((e) => `${e.desk}|${e.from}`));
const actorsWithWork = new Set();
for (const [k, n] of nodes) if (n.kind === 'actor' && (assocKeys.has(k) || edges.some((e) => (e.from === n.id || e.to === n.id) && e.desk === n.desk))) actorsWithWork.add(k);
const idleActors = [...nodes.values()].filter((n) => n.kind === 'actor' && !actorsWithWork.has(`${n.desk}|${n.id}`));
if (idleActors.length > 0) fail(`actor(s) with no association: ${idleActors.map((n) => `${n.desk}:${n.label}`).join(', ')}`);

const count = (k) => edges.filter((e) => e.kind === k).length;
const useCases = [...nodes.values()].filter((n) => n.kind === 'usecase');
const subOnly = useCases.filter((n) => !edges.some((e) => e.desk === n.desk && e.from === n.id && e.kind === 'association')
  && !edges.some((e) => e.desk === n.desk && e.to === n.id && e.kind === 'association') === false ? false : false);
console.log(`model: ${nodes.size} nodes (${[...nodes.values()].filter((n) => n.kind === 'actor').length} actor declarations, ${useCases.length} use cases) over ${rawBlocks.length} block(s)`);
console.log(`edges: ${count('association')} associations, ${count('include')} «include», ${count('extend')} «extend», ${count('dependency')} unlabelled dependencies`);
if (skippedStyle > 0) console.log(`note: ${skippedStyle} styling/direction line(s) carried no graph meaning and were not parsed as edges`);
for (const h of deskHeadings) {
  const d = deskNumber(h);
  const uc = useCases.filter((n) => n.desk === d).length;
  const ac = edges.filter((e) => e.desk === d && e.kind === 'association').length;
  const inc = edges.filter((e) => e.desk === d && e.kind === 'include').length;
  const ext = edges.filter((e) => e.desk === d && e.kind === 'extend').length;
  console.log(`  desk ${d}: ${uc} use cases, ${ac} associations, ${inc} include, ${ext} extend`);
}
// The global block is an overview of the same model; say how much it restates rather than leaving a reader to
// wonder why the per-actor figures do not show those boxes twice.
const deskLabels = new Set([...nodes.values()].filter((n) => n.isDesk && n.kind === 'usecase').map((n) => n.label));
const globalCases = [...nodes.values()].filter((n) => !n.isDesk && n.kind === 'usecase');
const restated = globalCases.filter((n) => deskLabels.has(n.label)).length;
console.log(`global block: ${globalCases.length} use cases, ${restated} of them restated from a desk block (drawn once, in the overview figure)`);
if (reportOnly) process.exit(0);

// ---------------------------------------------------------------- 3. figure sources, all from the model
function nodeText(n) {
  return n.kind === 'actor'
    ? `${n.id}["${ACTOR_IMG}${n.label}"]`
    : `${n.id}(["${n.label}"])`;
}
function edgeText(e) {
  const arrow = e.kind === 'association' ? '---' : '-.->';
  const label = e.label === '' ? '' : `|"${e.label.replace(/<<\s*/g, '«').replace(/>>/g, '»')}"|`;
  return `    ${e.from} ${arrow}${label} ${e.to}`;
}
// One dedupe for every derived figure. The global boundary diagram restates many desk use cases for the overview;
// a derived figure must draw each of them ONCE, and the desk block is the canonical spelling because it is the one
// the desk's own specification table sits under. A node that exists ONLY in the overview (an actor such as
// Scholarship Officer, whose desk panel never repeats it) is kept rather than dropped, so no actor loses its figure.
function dedupeByLabel(list) {
  const byKey = new Map();
  const keep = [];
  for (const n of list) {
    const k = `${n.kind}|${n.label}`;
    const seen = byKey.get(k);
    if (seen === undefined) { byKey.set(k, n); keep.push(n); continue; }
    if (!seen.isDesk && n.isDesk) {
      byKey.set(k, n);
      keep[keep.indexOf(seen)] = n;
    }
  }
  // Map every original node to the survivor its label resolved to, so edges can be re-pointed after the merge.
  const remap = new Map();
  for (const n of list) {
    const survivor = byKey.get(`${n.kind}|${n.label}`);
    remap.set(`${n.desk}|${n.id}`, survivor);
  }
  return { keep, remap };
}

function emitGraph(nodesIn, edgesIn, panelPrefix, dir = 'LR') {
  const { keep, remap } = dedupeByLabel(nodesIn);
  const keptKeys = new Set(keep.map((n) => `${n.desk}|${n.id}`));
  const out = [`flowchart ${dir}`];
  const byDesk = new Map();
  for (const n of keep) {
    const key = `${n.desk}`;
    if (!byDesk.has(key)) byDesk.set(key, { actors: [], cases: [] });
    const g = byDesk.get(key);
    (n.kind === 'actor' ? g.actors : g.cases).push(n);
  }
  for (const [d, g] of byDesk) {
    for (const a of g.actors) out.push(`    ${nodeText({ ...a, id: `${a.id}_${a.desk}` })}`);
    if (g.cases.length === 0) continue;
    // The overview panel is not a desk. Naming it as one would make the complete figure look like a thirteenth
    // office, so it carries what it actually holds: use cases the guide draws only in the boundary diagram.
    const heading = d === '00'
      ? 'Cross-desk use cases (from the global boundary diagram)'
      : (rawBlocks.find((b) => deskNumber(b.section) === d)?.section ?? `Desk ${d}`);
    out.push(`    subgraph ${panelPrefix}${d}["${heading.replace(/"/g, "'")}"]`);
    for (const c of g.cases) out.push(`        ${nodeText({ ...c, id: `${c.id}_${c.desk}` })}`);
    out.push('    end');
  }
  const seenEdge = new Set();
  for (const e of edgesIn) {
    const a = remap.get(`${e.desk}|${e.from}`);
    const b = remap.get(`${e.desk}|${e.to}`);
    if (!a || !b) continue;
    if (!keptKeys.has(`${a.desk}|${a.id}`) || !keptKeys.has(`${b.desk}|${b.id}`)) continue;
    if (a.desk === b.desk && a.id === b.id) continue;
    const sig = `${a.desk}|${a.id}|${b.desk}|${b.id}|${e.kind}|${e.label}`;
    if (seenEdge.has(sig)) continue;
    seenEdge.add(sig);
    out.push(edgeText({ ...e, from: `${a.id}_${a.desk}`, to: `${b.id}_${b.desk}` }));
  }
  return { code: out.join('\n'), nodes: keep.length, edges: seenEdge.size };
}

function figureFor(predicate, title) {
  const sub = emitGraph([...nodes.values()].filter(predicate), edges, `SYS${title.slug}_`);
  if (sub.nodes === 0 || sub.edges === 0) return null;
  return sub.code;
}

const jobs = [];
// (a) per desk, (b) the global boundary: rendered from the guide's own block text, with the actor glyph and the
// real stereotypes injected — the words still come from the document, not from this script.
for (const b of rawBlocks) {
  const code = clean(b.code).replace(/^(\s*)([A-Za-z][\w]*)(\s*\[\s*")([^"]*)("\])/gm, (m, sp, id, open, label, close) =>
    /^UC/i.test(id) ? m : `${sp}${id}${open}${ACTOR_IMG}${label}${close}`);
  jobs.push({ name: `use-case-${/^Desk/.test(b.section) ? `desk-${deskNumber(b.section)}-${b.section.replace(/^Desk \d+:\s*/, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')}` : 'global-boundary'}`, heading: b.section, code });
}
// (c) the complete diagram over every desk: the whole model, deduped by label so an overview restatement is drawn
// once, with ids namespaced per desk so `UC01` in two panels stays two nodes.
{
  const built = emitGraph([...nodes.values()], edges, 'SYS');
  if (built.nodes === 0 || built.edges === 0) fail('the complete figure would be empty');
  console.log(`complete figure: ${built.nodes} nodes and ${built.edges} edges after the overview restatements were folded into the desk spelling`);
  jobs.push({ name: 'use-case-complete-all-offices', heading: 'Complete use-case diagram over every desk and office', code: built.code });
  // The same model in the other layout direction: a figure nine thousand pixels tall is complete but unusable on a
  // slide or a printed page, so both are drawn and the reader keeps whichever fits.
  const wide = emitGraph([...nodes.values()], edges, 'SYW', 'TB');
  jobs.push({ name: 'use-case-complete-wide', heading: 'Complete use-case diagram over every desk and office (wide layout)', code: wide.code });
}
// (d) one figure PER ACTOR.
const actorFigures = [];
for (const [label, firstKey] of actorLabels) {
  const mine = [...nodes.values()].filter((n) => n.kind === 'actor' && n.label === label);
  if (mine.length === 0) continue;
  const myIds = new Set(mine.map((n) => `${n.desk}|${n.id}`));
  const touched = new Set();
  for (const e of edges) {
    if (myIds.has(`${e.desk}|${e.from}`)) touched.add(`${e.desk}|${e.to}`);
    if (myIds.has(`${e.desk}|${e.to}`)) touched.add(`${e.desk}|${e.from}`);
  }
  // Include and extend chains hanging off the actor's own use cases, so the figure shows what the actor triggers.
  for (const e of edges) {
    if ((e.kind === 'include' || e.kind === 'extend' || e.kind === 'dependency') && touched.has(`${e.desk}|${e.from}`)) touched.add(`${e.desk}|${e.to}`);
  }
  // Secondary actors that share those use cases.
  for (const e of edges) {
    if (e.kind === 'association' && touched.has(`${e.desk}|${e.to}`)) touched.add(`${e.desk}|${e.from}`);
  }
  const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const code = figureFor((n) => touched.has(`${n.desk}|${n.id}`) || myIds.has(`${n.desk}|${n.id}`), { slug });
  if (code === null) { fail(`the per-actor figure for [${label}] would be empty`); }
  actorFigures.push({ name: `use-case-actor-${slug}`, heading: `Actor: ${label}`, code });
}
jobs.push(...actorFigures);
console.log(`figures queued: ${jobs.length} (${deskBlocks.length} desks, 1 global, 1 complete, ${actorFigures.length} per actor)`);

// ---------------------------------------------------------------- 4. render
mkdirSync(outDir, { recursive: true });
{
  const stale = readdirSync(outDir).filter((f) => /^use-case-.*\.(png|svg)$/.test(f) || /^_probe-/.test(f));
  for (const f of stale) unlinkSync(join(outDir, f));
  if (stale.length > 0) console.log(`removed ${stale.length} figure(s) from an earlier naming`);
}
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const page = await browser.newPage();
await page.setViewport({ width: 1600, height: 1200 });
await page.addScriptTag({ path: join(root, 'node_modules', 'mermaid', 'dist', 'mermaid.min.js') });
await page.evaluate(() => window.mermaid.initialize({ startOnLoad: false, securityLevel: 'loose', htmlLabels: true, flowchart: { htmlLabels: true, useMaxWidth: false } }));

const manifest = [];
for (const job of jobs) {
  const r = await page.evaluate(async ({ id, code }) => {
    try {
      const out = await window.mermaid.render(id, code);
      const holder = document.createElement('div');
      holder.innerHTML = out.svg;
      const svg = holder.querySelector('svg');
      if (!svg) return { error: 'no svg element came back' };
      document.body.style.margin = '0';
      document.body.style.background = '#fff';
      document.body.innerHTML = '';
      document.body.appendChild(holder);
      const b = svg.getBoundingClientRect();
      return { svg: out.svg, width: Math.ceil(b.width), height: Math.ceil(b.height) };
    } catch (e) { return { error: String(e && e.message ? e.message : e) }; }
  }, { id: `f${manifest.length}_${job.name.replace(/[^a-z0-9]/gi, '')}`, code: job.code });
  if (r.error) fail(`[${job.heading}] did not render: ${r.error.slice(0, 200)}`);
  if (!(r.width > 80 && r.height > 60)) fail(`[${job.heading}] drew ${r.width}x${r.height}, which is not a figure`);
  await page.setViewport({ width: Math.min(r.width + 40, 14000), height: Math.min(r.height + 40, 14000) });
  await page.setContent(`<html><head><style>html,body{margin:0;padding:0;background:#fff}svg{display:block}</style></head><body>${r.svg}</body></html>`);
  const png = await page.screenshot({ type: 'png', clip: { x: 0, y: 0, width: r.width, height: r.height } });
  if (!(png && png.length > 2000)) fail(`[${job.heading}] produced a ${png ? png.length : 0}-byte PNG`);
  writeFileSync(join(outDir, `${job.name}.png`), png);
  writeFileSync(join(outDir, `${job.name}.svg`), r.svg);
  manifest.push({ ...job, width: r.width, height: r.height, bytes: png.length });
  console.log(`  ok ${job.name.padEnd(52)} ${r.width}x${r.height} ${(png.length / 1024).toFixed(0)} KB`);
}
await browser.close();

const table = ['| figure | what it shows | pixels | PNG bytes |', '| :--- | :--- | ---: | ---: |'];
for (const m of manifest) table.push(`| \`${m.name}.png\` | ${m.heading} | ${m.width}×${m.height} | ${m.bytes} |`);
writeFileSync(join(outDir, 'manifest.md'), [
  '# Use-case figures',
  '',
  `Generated by \`node storage/app/scratch/lane208-use-case-figures.mjs\` from the use-case graphs in`,
  '`Documentation/Use-Case-Diagram-Guide.md`. Nothing here is retyped: the actors, use cases, associations and the',
  '«include» / «extend» dependencies are parsed out of that guide, and every figure is a projection of that one model.',
  '',
  `Model read this run: ${nodes.size} nodes, ${count('association')} associations, ${count('include')} «include», ${count('extend')} «extend».`,
  'Actors are drawn as UML stick figures; use cases as stadiums; associations as solid lines; include and extend as',
  'dashed arrows carrying their stereotype. One figure per actor shows that actor\'s associations, the include and',
  'extend chains hanging off each of them, and the secondary actors sharing those use cases.',
  '',
  ...table,
  '',
].join('\n'));
console.log(`\nwrote ${manifest.length} figure(s) to ${outDir}`);
