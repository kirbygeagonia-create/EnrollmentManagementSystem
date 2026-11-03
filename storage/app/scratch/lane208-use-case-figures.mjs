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
 * Usage: node storage/app/scratch/lane208-use-case-figures.mjs [--out=<dir>] [--chrome=<path>] [--report-only] [--audit-actor-edges]
 */
import { readFileSync, writeFileSync, mkdirSync, existsSync, readdirSync, unlinkSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import puppeteer from 'puppeteer';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '..', '..', '..');
let guide = join(root, 'Documentation', 'Use-Case-Diagram-Guide.md');

let outDir = join(root, 'Documentation', 'Diagrams');
let chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
let reportOnly = false;
for (const a of process.argv.slice(2)) {
  if (a.startsWith('--out=')) outDir = resolve(root, a.slice(6));
  else if (a.startsWith('--chrome=')) chrome = a.slice(9);
  else if (a.startsWith('--guide=')) guide = resolve(root, a.slice(8));
  else if (a === '--report-only') reportOnly = true;
  else if (a === '--audit-actor-edges' || a === '--audit-complete-figure') { /* read by the audits below; they stop before any render */ }
  else fail(`this tool reads --out=, --guide=, --chrome=, --report-only, --audit-actor-edges and --audit-complete-figure; it was given [${a}].`);
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

function emitGraph(nodesIn, edgesIn, panelPrefix, dir = 'LR', splitActors = false) {
  const { keep, remap } = dedupeByLabel(nodesIn);
  const keptKeys = new Set(keep.map((n) => `${n.desk}|${n.id}`));
  const eid = (n) => `${n.id}_${n.desk}`;

  // Every edge is resolved BEFORE anything is written, because the actor degrees and the connector order below are
  // both read from this list — and the disappearance ledger the audits weigh is exactly this pass, unchanged.
  const seenEdge = new Set();
  const dropped = [];
  const resolved = [];
  for (const e of edgesIn) {
    const a = remap.get(`${e.desk}|${e.from}`);
    const b = remap.get(`${e.desk}|${e.to}`);
    if (!a || !b) {
      dropped.push({ e, why: !a ? 'FROM-ABSENT' : 'TO-ABSENT' });
      continue;
    }
    if (!keptKeys.has(`${a.desk}|${a.id}`) || !keptKeys.has(`${b.desk}|${b.id}`)) {
      dropped.push({ e, why: 'NOT-KEPT' });
      continue;
    }
    if (a.desk === b.desk && a.id === b.id) {
      dropped.push({ e, why: 'SELF' });
      continue;
    }
    const sig = `${a.desk}|${a.id}|${b.desk}|${b.id}|${e.kind}|${e.label}`;
    if (seenEdge.has(sig)) {
      dropped.push({ e, why: 'DEDUPE' });
      continue;
    }
    seenEdge.add(sig);
    resolved.push({ a, b, e });
  }

  const byDesk = new Map();
  for (const n of keep) {
    const key = `${n.desk}`;
    if (!byDesk.has(key)) byDesk.set(key, { actors: [], cases: [] });
    const g = byDesk.get(key);
    (n.kind === 'actor' ? g.actors : g.cases).push(n);
  }
  const panelOrder = new Map();
  [...byDesk.keys()].forEach((d, i) => panelOrder.set(d, i));

  const degree = new Map();
for (const { a, b } of resolved) {
    for (const n of [a, b]) {
      if (n.kind !== 'actor') continue;
      const k = `${n.desk}|${n.id}`;
      degree.set(k, (degree.get(k) ?? 0) + 1);
    }
  }
  const deg = (n) => degree.get(`${n.desk}|${n.id}`) ?? 0;
  const actors = [...keep.filter((n) => n.kind === 'actor')]
    .sort((x, y) => deg(y) - deg(x) || String(x.label).localeCompare(String(y.label)));

  // SIDES. The owner's ruling of 2026-11-03: the major actors stand on the right of the figure and the minor on the
  // left, instead of every actor piled in one column so each connector has to cross the whole drawing. "Major" is
  // not a taste call here: it is the half of the actors that carry the most associations, counted from this same
  // resolved list, so the split moves when the model moves rather than when a reader does.
  const major = new Set();
  let minor = actors;
  if (splitActors && actors.length > 1) {
    const half = Math.ceil(actors.length / 2);
    actors.slice(0, half).forEach((a) => major.add(`${a.desk}|${a.id}`));
    minor = actors.slice(half);
  }
  const isMajor = (n) => major.has(`${n.desk}|${n.id}`);

  const out = [`flowchart ${dir}`];
  const caseRank = new Map();
  const actorIndex = new Map(minor.concat([...actors].filter(isMajor)).map((a, i) => [`${a.desk}|${a.id}`, i]));
  const caseOf = (r) => (r.a.kind === 'actor' ? r.b : r.a);
  const actorOf = (r) => (r.a.kind === 'actor' ? r.a : r.b);

  // PANEL SEQUENCE. Dagre lays out ranks by the order nodes are DECLARED in, so a desk band that sits far from the
  // actor column makes every one of that actor's connectors travel across the whole drawing and cut through other
  // bands. Panels are therefore emitted in the order of the actors they answer to — the mean index of the actors
  // adjacent to that panel's cases — which is what actually moved the crossing count when measured (140 before any
  // of this, 94 after the sides alone).
  const panelAdjacent = new Map();
  for (const r of resolved) {
    const act = actorOf(r);
    const cse = caseOf(r);
    if (act === undefined || cse === undefined) continue;
    const k = cse.desk;
    const list = panelAdjacent.get(k) ?? [];
    list.push(actorIndex.get(`${act.desk}|${act.id}`) ?? 0);
    panelAdjacent.set(k, list);
  }
  const meanAdj = (d) => {
    const l = panelAdjacent.get(d);
    if (!l || l.length === 0) return Number.MAX_SAFE_INTEGER;
    return l.reduce((s, v) => s + v, 0) / l.length;
  };
  const panelSequence = [...byDesk.keys()].sort((x, y) => meanAdj(x) - meanAdj(y) || String(x).localeCompare(String(y)));
  // The edge sort below ranks a target by its panel, so that ranking has to be the ORDER THE PANELS ARE DRAWN IN,
  // not the order the model happened to declare them in. Two definitions of "which band is first" is the defect this
  // file refuses everywhere else.
  panelOrder.clear();
  panelSequence.forEach((d, i) => panelOrder.set(d, i));

  for (const a of minor) out.push(`    ${nodeText({ ...a, id: eid(a) })}`);
  for (const d of panelSequence) {
    const g = byDesk.get(d);
    if (g.cases.length === 0) continue;
    // The overview panel is not a desk. Naming it as one would make the complete figure look like a thirteenth
    // office, so it carries what it actually holds: use cases the guide draws only in the boundary diagram.
    const heading = d === '00'
      ? 'Cross-desk use cases (from the global boundary diagram)'
      : (rawBlocks.find((b) => deskNumber(b.section) === d)?.section ?? `Desk ${d}`);
    out.push(`    subgraph ${panelPrefix}${d}["${heading.replace(/"/g, "'")}"]`);
    // Cases are declared in the model's own order. Their labels were sorted alphabetically here on 2026-11-03 and
    // the measured effect was the OPPOSITE of the reason for doing it: the complete figure went from 92 crossing
    // pairs to 98, because dagre's rank assignment follows declaration order and the connectors were already being
    // emitted by panel. The experiment is recorded rather than repeated.
    for (const c of g.cases) out.push(`        ${nodeText({ ...c, id: eid(c) })}`);
    out.push('    end');
  }
  for (const a of [...actors].filter(isMajor)) out.push(`    ${nodeText({ ...a, id: eid(a) })}`);

  // ORDER OF CONNECTORS. A fan drawn in the order its spokes were declared crosses itself; a fan drawn in the order
  // the targets actually sit in the drawing does not. So each actor's edges are emitted together, and inside that
  // group the targets are sorted by the panel they live in. An association is an undirected link, so writing the
  // right-hand actor LAST (`case --- actor`) is a layout choice and changes no relationship: includes and extends
  // keep the direction the guide spells, and they are emitted after the associations, sorted by both endpoints.
  const assoc = resolved.filter((r) => r.e.kind === 'association' && (actorOf(r) !== undefined));
  const deps = resolved.filter((r) => !(r.e.kind === 'association' && actorOf(r) !== undefined));
  assoc.sort((x, y) => {
    const ax = actorOf(x);
    const ay = actorOf(y);
    const side = (isMajor(ax) === isMajor(ay)) ? 0 : (isMajor(ax) ? 1 : -1);
    if (side !== 0) return side;
    const byActor = (actorIndex.get(`${ax.desk}|${ax.id}`) ?? 0) - (actorIndex.get(`${ay.desk}|${ay.id}`) ?? 0);
    if (byActor !== 0) return byActor;
    const cx = caseOf(x);
    const cy = caseOf(y);
    return (panelOrder.get(cx.desk) ?? 0) - (panelOrder.get(cy.desk) ?? 0)
      || String(cx.label).localeCompare(String(cy.label));
  });
  deps.sort((x, y) => (panelOrder.get(x.a.desk) ?? 0) - (panelOrder.get(y.a.desk) ?? 0)
    || (panelOrder.get(x.b.desk) ?? 0) - (panelOrder.get(y.b.desk) ?? 0));

  const write = (r) => {
    const flip = splitActors && r.e.kind === 'association' && actorOf(r) !== undefined && isMajor(actorOf(r));
    const from = flip ? r.b : r.a;
    const to = flip ? r.a : r.b;
    out.push(edgeText({ ...r.e, from: eid(from), to: eid(to) }));
  };
  for (const r of assoc.concat(deps)) write(r);

  return {
    code: out.join('\n'),
    nodes: keep.length,
    edges: seenEdge.size,
    dropped,
    shown: [...seenEdge],
    actorsMajor: [...major].length,
    actorsMinor: minor.length,
  };
}

function figureFor(predicate, title) {
  const sub = emitGraph([...nodes.values()].filter(predicate), edges, `SYS${title.slug}_`);
  if (sub.nodes === 0 || sub.edges === 0) return null;
  return sub;
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
let completeReport = null;
{
  const built = emitGraph([...nodes.values()], edges, 'SYS', 'LR', true);
  if (built.nodes === 0 || built.edges === 0) fail('the complete figure would be empty');
  console.log(`complete figure: ${built.nodes} nodes and ${built.edges} edges after the overview restatements were folded into the desk spelling`
    + `; ${built.actorsMajor} major actor(s) drawn on the right and ${built.actorsMinor} minor on the left`);
  completeReport = built;
  jobs.push({ name: 'use-case-complete-all-offices', heading: 'Complete use-case diagram over every desk and office', code: built.code });
  // The same model in the other layout direction: a figure nine thousand pixels tall is complete but unusable on a
  // slide or a printed page, so both are drawn and the reader keeps whichever fits.
  const wide = emitGraph([...nodes.values()], edges, 'SYW', 'TB', true);
  jobs.push({ name: 'use-case-complete-wide', heading: 'Complete use-case diagram over every desk and office (wide layout)', code: wide.code });
}
// (cc) The actor↔desk overview. The complete sheet carries all 121 associations, and the owner's reading of it was
// "too many lines, and they cross". This is the SAME model at the altitude a person can hold: one node per desk band,
// one node per folded actor, and ONE line per role that works at that desk. Nothing here is a second definition —
// the pairs are read out of the same `edges` list the audits weigh, so a band gains a line only when a real
// association exists in the guide, and an actor with no association to a desk cannot appear connected to it.
{
  const deskIds = [...new Set([...nodes.values()].filter((n) => n.kind !== 'actor').map((n) => n.desk))]
    .filter((d) => d !== '00').sort();
  const casesPerDesk = new Map();
  for (const n of nodes.values()) {
    if (n.kind === 'actor' || n.desk === '00') continue;
    casesPerDesk.set(n.desk, (casesPerDesk.get(n.desk) ?? 0) + 1);
  }
  // actorKey|desk -> number of associations. Counting is per actor LABEL, because the model folds the roles to one
  // plate per role and the figure must show that same folded actor once.
  const links = new Map();
  for (const e of edges) {
    if (e.kind !== 'association') continue;
    const a = nodes.get(`${e.desk}|${e.from}`);
    const b = nodes.get(`${e.desk}|${e.to}`);
    if (!a || !b || a.kind === b.kind) continue;
    const actor = a.kind === 'actor' ? a : b;
    const other = a.kind === 'actor' ? b : a;
    if (other.desk === '00') continue;
    // Folded by LABEL, not by the declaring block's key: a role the guide declares in several blocks is one plate
    // here, and requiring this occurrence to be the first one dropped 15 of the 19 roles on the floor.
    const k = `${actor.label}|${other.desk}`;
    links.set(k, (links.get(k) ?? 0) + 1);
  }
  const degreeByLabel = new Map();
  for (const [k, v] of links) {
    const label = k.split('|')[0];
    degreeByLabel.set(label, (degreeByLabel.get(label) ?? 0) + v);
  }
  const labelsByDegree = [...degreeByLabel.entries()].sort((x, y) => y[1] - x[1] || x[0].localeCompare(y[0]));
  const half = Math.ceil(labelsByDegree.length / 2);
  const majorLabels = new Set(labelsByDegree.slice(0, half).map(([l]) => l));
  const minorLabels = labelsByDegree.slice(half).map(([l]) => l);
  const bandId = (d) => `BAND${d}`;
  // The band column stays in desk-number order because a person reads desks 1 to 12, so the only free variable is the
  // order of the actors in each column — and the bipartite crossing count is a function of exactly that order. Two
  // orders are weighed and the better one is declared: the barycentre sweep, and an adjacent-swap descent from it.
  // The count is the exact straight-line one (edges (a,b),(a',b') cross iff their endpoints are ordered differently;
  // edges sharing an endpoint cannot cross), which is what dagre's own sweep starts from, not what it guarantees.
  const bandIndexOf = new Map(deskIds.map((d, i) => [d, i]));
  const rowsFor = (side) => {
    const rows = [];
    for (const k of links.keys()) {
      const [label, d] = k.split('|');
      if (!side.has(label) || !bandIndexOf.has(d)) continue;
      rows.push([label, bandIndexOf.get(d)]);
    }
    return rows;
  };
  /** Exact straight-line count for one column against the band column, given the actors' order. */
  const crossingsAgainst = (order, rows) => {
    const pos = new Map(order.map((l, i) => [l, i]));
    const e = rows.map(([label, b]) => [pos.get(label), b]).filter(([v]) => v !== undefined);
    let n = 0;
    for (let i = 0; i < e.length; i++) {
      for (let j = i + 1; j < e.length; j++) {
        const [a1, b1] = e[i];
        const [a2, b2] = e[j];
        if (a1 === a2 || b1 === b2) continue;
        if ((a1 - a2) * (b1 - b2) < 0) n++;
      }
    }
    return n;
  };
  const barycentre = new Map();
  for (const k of links.keys()) {
    const [label, d] = k.split('|');
    const w = links.get(k);
    const prev = barycentre.get(label) ?? [0, 0];
    barycentre.set(label, [prev[0] + (bandIndexOf.get(d) ?? 0) * w, prev[1] + w]);
  }
  const meanBand = (l) => {
    const [sum, n] = barycentre.get(l) ?? [0, 0];
    return n === 0 ? 0 : sum / n;
  };
  const byBand = (x, y) => meanBand(x) - meanBand(y) || x.localeCompare(y);
  /** Adjacent-swap descent: keep any swap that lowers the count, repeat until none does. */
  const descend = (start, rows) => {
    const best = start.slice();
    let cur = crossingsAgainst(best, rows);
    let moved = true;
    while (moved) {
      moved = false;
      for (let i = 0; i + 1 < best.length; i++) {
        const trial = best.slice();
        [trial[i], trial[i + 1]] = [trial[i + 1], trial[i]];
        const s = crossingsAgainst(trial, rows);
        if (s < cur) {
          [best[i], best[i + 1]] = [best[i + 1], best[i]];
          cur = s;
          moved = true;
        }
      }
    }
    return { order: best, count: cur };
  };
  const minorRows = rowsFor(new Set(minorLabels));
  const majorRows = rowsFor(majorLabels);
  const sideOrders = {
    minor: descend([...minorLabels].sort(byBand), minorRows),
    major: descend([...majorLabels].sort(byBand), majorRows),
  };
  const actorId = new Map();
  [...sideOrders.minor.order, ...sideOrders.major.order].forEach((l, i) => actorId.set(l, `ROLE${i}`));
  const quote = (s) => String(s).replace(/"/g, "'");

  // The bands are FREE nodes, not one wrapped subgraph: an `OVERVIEW` wrapper made dagre stack all twelve inside a
  // single tall panel and the figure came out 490x4371 — unreadable in the other direction. Free bands rank across
  // the page between the two actor columns.
  const bands = deskIds.map((d) => {
    const head = rawBlocks.find((b) => deskNumber(b.section) === d)?.section ?? `Desk ${d}`;
    return `${bandId(d)}["${quote(head)} — ${casesPerDesk.get(d) ?? 0} use cases"]`;
  });
  const lines = ['flowchart LR'];
  for (const l of sideOrders.minor.order) lines.push(`  ${actorId.get(l)}["${quote(ACTOR_IMG + l)}"]`);
  for (const b of bands) lines.push(`  ${b}`);
  for (const l of sideOrders.major.order) lines.push(`  ${actorId.get(l)}["${quote(ACTOR_IMG + l)}"]`);
  // `for (const k of …)`, NOT `for (const [k] of …)`: a string is iterable, so the destructuring form bound `k` to the
  // key's FIRST CHARACTER. Every lookup then missed and the figure was built from `undefined --- BANDundefined` lines,
  // which is why the boxes came out stacked in one column — the graph had no edges at all.
  const bad = [];
  for (const k of [...links.keys()].sort()) {
    const [label, d] = k.split('|');
    if (!actorId.has(label) || !deskIds.includes(d)) {
      bad.push(`"${k}" resolves to actor id ${actorId.get(label)} and band ${bandId(d)}`);
      continue;
    }
    // Minor roles are written first in the sentence so the ranker puts them left; majors last, right. The
    // association itself is undirected, so this is a layout choice and not a second meaning.
    lines.push(majorLabels.has(label)
      ? `  ${bandId(d)} --- ${actorId.get(label)}`
      : `  ${actorId.get(label)} --- ${bandId(d)}`);
  }
  if (bad.length > 0) {
    throw new Error(`actor-desk overview: ${bad.length} role-desk pair(s) name no actor plate or no desk band:\n  `
      + bad.join('\n  '));
  }
  // The sheet holds one plate per role that works at a numbered desk. A role whose associations all sit in the
  // boundary block has no line here, and that is printed rather than left as an unexplained absence — otherwise the
  // figure reads as the complete actor list while it omits one.
  const unlinked = [...actorLabels.keys()].filter((l) => !degreeByLabel.has(l));
  console.log(`actor-desk overview: ${actorId.size} roles, ${deskIds.length} bands, ${links.size} line(s) — `
    + `${sideOrders.major.order.length} major drawn right, ${sideOrders.minor.order.length} minor left; `
    + `straight-line crossings predicted after the descent: left ${sideOrders.minor.count}, right ${sideOrders.major.count}`
    + (unlinked.length > 0
      ? `; ${unlinked.length} plate(s) draw NO desk line here because the guide gives them no association to a numbered desk: ${unlinked.join(', ')}`
      : ''));
  jobs.push({
    name: 'use-case-roles-at-desks',
    heading: `Actor-to-desk overview: ${actorId.size} roles, ${deskIds.length} desks, ${links.size} lines — `
      + 'major actors right, minor left',
    code: lines.join('\n'),
  });
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
  // A fixpoint, not one pass: an included use case may itself include another, and a single sweep over `edges`
  // would only extend the chain for edges that happen to sit later in the list — the figure would then drop the
  // link whose start was added after it was examined, and print a diagram that looks complete.
  for (let grew = true; grew;) {
    grew = false;
    for (const e of edges) {
      if ((e.kind === 'include' || e.kind === 'extend' || e.kind === 'dependency') && touched.has(`${e.desk}|${e.from}`) && !touched.has(`${e.desk}|${e.to}`)) {
        touched.add(`${e.desk}|${e.to}`);
        grew = true;
      }
    }
  }
  // Secondary actors that share those use cases.
  for (const e of edges) {
    if (e.kind === 'association' && touched.has(`${e.desk}|${e.to}`)) touched.add(`${e.desk}|${e.from}`);
  }
  const slug = label.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const selection = new Set([...touched, ...myIds]);
  const fig = figureFor((n) => selection.has(`${n.desk}|${n.id}`), { slug });
  if (fig === null) { fail(`the per-actor figure for [${label}] would be empty`); }
  actorFigures.push({ name: `use-case-actor-${slug}`, heading: `Actor: ${label}`, code: fig.code, selection, dropped: fig.dropped, shown: fig.shown });
}
jobs.push(...actorFigures);
console.log(`figures queued: ${jobs.length} (${deskBlocks.length} desks, 1 global, 1 complete, ${actorFigures.length} per actor)`);

// ---------------------------------------------------------- 3b. the per-actor closure audit
// The objective is that every actor's figure carries its associations AND the include / extend chains hanging off
// them. That is a property of the emitter, so it is read from the emitter rather than asserted by this script's
// comment: an actor figure may not drop a dependency edge whose own start is inside that actor's selection. One
// implementation answers two surfaces — the `--audit-actor-edges` command and the manifest's own summary line —
// because two answers to "is this figure complete" is the defect this repository has refused in every lane.
function auditActorEdges(quiet) {
  const depKind = (k) => k === 'include' || k === 'extend' || k === 'dependency';
  const labelOf = (desk, id) => nodes.get(`${desk}|${id}`)?.label ?? `${id}@${desk}`;
  const rows = [];
  let violations = 0;
  let withDeps = 0;
  for (const j of actorFigures) {
    const owed = edges.filter((e) => depKind(e.kind) && j.selection.has(`${e.desk}|${e.from}`));
    const bad = j.dropped.filter(({ e, why }) => depKind(e.kind) && j.selection.has(`${e.desk}|${e.from}`) && why !== 'DEDUPE');
    const counts = { include: 0, extend: 0, dependency: 0, association: 0 };
    for (const sig of j.shown) {
      const kind = sig.split('|')[4];
      if (kind in counts) counts[kind]++;
    }
    if (counts.include + counts.extend + counts.dependency > 0) withDeps++;
    violations += bad.length;
    rows.push({ heading: j.heading, counts, owed: owed.length, bad, mark: bad.length === 0 ? 'ok  ' : 'FAIL' });
    for (const { e, why } of bad) {
      rows.at(-1).detail = `${rows.at(-1).detail ?? ''}\n        DROPPED ${why}: [${labelOf(e.desk, e.from)}] -${e.kind}-> [${labelOf(e.desk, e.to)}] (desk ${e.desk})`;
    }
  }
  const summary = `${actorFigures.length} actor figure(s); ${withDeps} carry at least one include/extend chain; ${violations} owed dependency edge(s) dropped`;
  if (!quiet) {
    console.log('\nper-actor closure audit (edges the figure shows / owes):');
    for (const r of rows) {
      console.log(`  ${r.mark} ${r.heading.padEnd(46)} assoc ${String(r.counts.association).padStart(2)}  include ${String(r.counts.include).padStart(2)}  extend ${String(r.counts.extend).padStart(2)}  owed-deps ${String(r.owed).padStart(2)}  dropped ${r.bad.length}`);
      if (r.detail) console.log(r.detail);
    }
    console.log(`\n${summary}.`);
  }
  if (actorFigures.length === 0) { fail('the audit read no actor figures — the reader is blind, not the model clean.'); }
  if (violations > 0) { fail(`${violations} include/extend edge(s) the model owes an actor figure were not drawn there.`); }
  return summary;
}

// "Complete" is the word the headline figure carries, so it is weighed by arithmetic rather than trusted: every
// DISTINCT node in the model must be drawn, and the only edges allowed to disappear are a restatement folded into
// the one it restates, or an edge whose two ends merged into the same label. Anything else is a lost relationship.
function auditCompleteFigure(quiet) {
  if (completeReport === null) { fail('the complete figure was never emitted, so its audit has nothing to weigh.'); }
  const distinct = new Set([...nodes.values()].map((n) => `${n.kind}|${n.label}`)).size;
  const why = {};
  for (const d of completeReport.dropped) why[d.why] = (why[d.why] ?? 0) + 1;
  const bad = completeReport.dropped.filter((d) => d.why !== 'DEDUPE' && d.why !== 'SELF');
  const actorDecls = [...nodes.values()].filter((n) => n.kind === 'actor').length;
  const cases = nodes.size - actorDecls;
  const summary = `model ${nodes.size} nodes (${actorDecls} actor declarations, ${cases} use cases) fold to ${distinct} distinct shapes, drawn ${completeReport.nodes}; `
    + `model ${edges.length} edges, drawn ${completeReport.edges}, folded as restatement ${(why.DEDUPE ?? 0)} and as merged endpoints ${(why.SELF ?? 0)}`;
  if (!quiet) {
    console.log('\ncomplete-figure accounting:');
    console.log(`  ${summary}`);
    for (const d of bad) {
      console.log(`        LOST ${d.why}: ${d.e.kind} desk ${d.e.desk} ${d.e.from} -> ${d.e.to}`);
    }
  }
  if (distinct !== completeReport.nodes) {
    fail(`the complete figure drew ${completeReport.nodes} nodes but the model holds ${distinct} distinct shapes — a use case or an actor is absent from the headline figure.`);
  }
  if (bad.length > 0) {
    fail(`${bad.length} relationship(s) vanished from the complete figure for a reason that is not a restatement fold.`);
  }
  // The emitter's own ledger must add up, or the audit is weighing a population that was never handed to it: a
  // caller that quietly filtered one edge out would leave `dropped` untouched and every count above still closing.
  if (completeReport.edges + completeReport.dropped.length !== edges.length) {
    fail(`the complete figure accounts for ${completeReport.edges + completeReport.dropped.length} of the ${edges.length} edges the model holds — an edge never reached the emitter at all.`);
  }
  return summary;
}

// ---------------------------------------------------------------- 3b2. UML category guard
// An «include» or an «extend» relates a use case to a use case. One edge in the guide pointed either at a stick
// figure, which is a message to an external system and belongs on a plain dependency arrow; the guide is now
// spelled that way, and this refuses the category error rather than drawing a diagram a reviewer will mark down.
const misStereotyped = edges.filter((e) => (e.kind === "include" || e.kind === "extend")
  && (nodes.get(`${e.desk}|${e.from}`)?.kind === "actor" || nodes.get(`${e.desk}|${e.to}`)?.kind === "actor"));
if (misStereotyped.length > 0) {
  for (const e of misStereotyped) {
    console.error(`        desk ${e.desk}: [${nodes.get(`${e.desk}|${e.from}`)?.label}] -${e.kind}-> [${nodes.get(`${e.desk}|${e.to}`)?.label}]`);
  }
  fail(`${misStereotyped.length} «include»/«extend» edge(s) end on an ACTOR; a message to an external system is a plain dependency, not an inclusion or an extension.`);
}

// ---------------------------------------------------------------- 3b3. every use-case to use-case line is named
// The guide's own checklist promises no plain use-case-to-use-case association exists, and the owner asked that
// every connection between two use cases carry its «include» or «extend» label. A line drawn with no stereotype is
// a claim the reader cannot check, so the model refuses it before anything is rendered: kind is `association` and
// no label means nobody said what the arrow asserts.
const unlabelled = edges.filter((e) => e.kind === "association"
  && nodes.get(`${e.desk}|${e.from}`)?.kind === "usecase"
  && nodes.get(`${e.desk}|${e.to}`)?.kind === "usecase"
  && !String(e.label ?? "").trim());
if (unlabelled.length > 0) {
  for (const e of unlabelled) {
    console.error(`        desk ${e.desk}: [${nodes.get(`${e.desk}|${e.from}`)?.label}] --- [${nodes.get(`${e.desk}|${e.to}`)?.label}]`);
  }
  fail(`${unlabelled.length} use-case-to-use-case edge(s) carry no stereotype; name each one «include» or «extend» (or say what the plain line asserts) before the figures are drawn.`);
}


// ---------------------------------------------------------------- 3c. the model as data
// Everything downstream — the audits, the index, and any later probe that wants to ask a question about the actors —
// reads THIS file rather than re-parsing the guide. A second parser is a second definition of "what is an actor",
// and this batch met exactly that: a fresh probe counted 43 actor declarations where the generator's model holds 41,
// because the two readers disagree about which lines are graph source. One reader decides; the others consume it.
mkdirSync(outDir, { recursive: true });
const modelPath = join(outDir, 'model.json');
function emitModel() {
  writeFileSync(modelPath, JSON.stringify({
    generatedFrom: 'Documentation/Use-Case-Diagram-Guide.md',
    generator: 'storage/app/scratch/lane208-use-case-figures.mjs',
    census: {
      nodes: nodes.size,
      actors: [...nodes.values()].filter((n) => n.kind === 'actor').length,
      useCases: [...nodes.values()].filter((n) => n.kind !== 'actor').length,
      association: count('association'),
      include: count('include'),
      extend: count('extend'),
    },
    nodes: [...nodes.values()].map((n) => ({ desk: n.desk, id: n.id, label: n.label, kind: n.kind, isDesk: n.isDesk })),
    edges: edges.map((e) => ({ desk: e.desk, from: e.from, to: e.to, kind: e.kind, label: e.label ?? null })),
  }, null, 1));
  console.log(`model written for other readers: ${modelPath}`);
}
emitModel();

if (process.argv.includes('--audit-actor-edges') || process.argv.includes('--audit-complete-figure')) {
  if (process.argv.includes('--audit-actor-edges')) {
    auditActorEdges(false);
    console.log('AUDIT CLEAN: every dependency edge an actor triggers is drawn in that actor\'s figure.');
  }
  if (process.argv.includes('--audit-complete-figure')) {
    auditCompleteFigure(false);
    console.log('AUDIT CLEAN: the complete figure draws every distinct node and loses no relationship but a fold.');
  }
  process.exit(0);
}

const buckets = [
  { title: 'Complete diagram over every office and desk', pick: (n) => n === 'use-case-complete-all-offices' || n === 'use-case-complete-wide' },
  { title: 'Actor-to-desk overview (the readable view)', pick: (n) => n === 'use-case-roles-at-desks' },
  { title: 'Global system boundary', pick: (n) => n === 'use-case-global-boundary' },
  { title: 'One figure per desk', pick: (n) => n.startsWith('use-case-desk-') },
  { title: 'One figure per actor — its associations, and the include and extend chains hanging off them', pick: (n) => n.startsWith('use-case-actor-') },
];
const assigned = new Map();
for (const b of buckets) {
  for (const m of jobs) {
    if (b.pick(m.name)) {
      if (assigned.has(m.name)) fail(`the index would show \`${m.name}\` twice, under [${assigned.get(m.name)}] and [${b.title}]`);
      assigned.set(m.name, b.title);
    }
  }
}
// Weighed here, before a single figure is drawn: an unplanned name is a figure that would ship unlinked, and a run
// that discovers it after two minutes of rendering has already written the artefacts nobody can find.
if (assigned.size !== jobs.length) {
  const unlisted = jobs.filter((m) => !assigned.has(m.name)).map((m) => m.name);
  fail(`${jobs.length - assigned.size} figure(s) fall into no bucket of the index and would ship unlinked: ${unlisted.join(', ')}`);
}


// ---------------------------------------------------------------- 4. render
// Pre-flight, BEFORE anything is deleted: the artefact check below can only run on a rendered SVG, and a label this
// script cannot spell (a stray quote, an unbalanced guillemet) would otherwise be discovered after the old figures
// were already unlinked — a half-written deliverable that reads like a successful run. The syntax is weighed here,
// where a refusal costs nothing.
for (const job of jobs) {
  for (const m of job.code.matchAll(/-\.->\|([^|]*)\|/g)) {
    const label = m[1];
    if ((label.match(/«/g) ?? []).length !== (label.match(/»/g) ?? []).length) {
      fail(`[${job.heading}] writes an unbalanced «…» stereotype: [${label}]`);
    }
    if (label.includes('"') && !/^"[^"]*"$/.test(label.trim())) {
      fail(`[${job.heading}] writes a label with a stray double quote: [${label}]`);
    }
    if (label.replace(/["'«»\[\]\\n\s]/g, '') === '') {
      fail(`[${job.heading}] draws a dashed edge with no label at all: [${label}]`);
    }
  }
}
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
  // The name of a relationship must reach the artefact. This is the defect that started the lane: mermaid's HTML
  // labels swallowed "<<include>>" into an empty box, so the arrow drew, the figure sized correctly, and the
  // dependency had no name on the page — a picture that reads as complete while stating nothing. Count what the
  // code asserts and require the drawn SVG to show it at least as often.
  const svgWords = r.svg.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ');
  const wanted = new Map();
  for (const m of job.code.matchAll(/-\.->\|([^|]*)\|/g)) {
    const label = m[1];
    for (const st of label.match(/«[^»]+»/g) ?? []) wanted.set(st, (wanted.get(st) ?? 0) + 1);
    const rest = label.replace(/["']/g, ' ').replace(/«[^»]+»/g, ' ').replace(/\\n/g, ' ').replace(/\[([^\]]*)\]/g, ' $1 ').trim();
    for (const word of rest.split(/\s+/).filter((w) => w.length > 3)) wanted.set(word, (wanted.get(word) ?? 0) + 1);
  }
  for (const [token, count] of wanted) {
    const shown = svgWords.split(token).length - 1;
    if (shown < count) {
      fail(`[${job.heading}] asserts ${count} label(s) reading [${token}] and the drawn SVG shows ${shown} — a relationship whose name did not reach the page.`);
    }
  }
  manifest.push({ ...job, width: r.width, height: r.height, bytes: png.length, labels: wanted.size });
  console.log(`  ok ${job.name.padEnd(52)} ${r.width}x${r.height} ${(png.length / 1024).toFixed(0)} KB`);
}
await browser.close();

const table = ['| figure | what it shows | pixels | PNG bytes |', '| :--- | :--- | ---: | ---: |'];
for (const m of manifest) table.push(`| \`${m.name}.png\` | ${m.heading} | ${m.width}×${m.height} | ${m.bytes} |`);
// The manifest may not promise what the emitter did not draw: the same audit the hand command runs is weighed here,
// and a dropped chain refuses the write rather than shipping a page that claims completeness.
const closure = auditActorEdges(true);
const completeness = auditCompleteFigure(true);
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
  `Per-actor closure, weighed by \`--audit-actor-edges\` rather than asserted: ${closure}.`,
  'An actor figure that prints no stereotype owes none in the model; the audit names any owed edge the emitter dropped.',
  '',
  `Completeness of the headline figure, weighed by \`--audit-complete-figure\`: ${completeness}.`,
  'A node folds only when the same kind carries the same label twice (an overview restatement of its desk), and an',
  'edge folds only when both ends merged. Any other disappearance stops this generator before it writes.',
  '',
  'The byte column is a reading of the render that wrote it, not a property of the model: re-rendering the same',
  'undivided model gave every one of the 43 figures a different PNG size while all 43 kept identical pixel',
  'dimensions. Ask the dimensions and the model counts whether a change moved; ask the bytes when you need to know',
  'whether a file is the one this page describes.',
  '',
  ...table,
  '',
].join('\n'));
console.log(`\nwrote ${manifest.length} figure(s) to ${outDir}`);

// ------------------------------------------------------------- 5. the page that points at them
// 43 images in a folder are an artefact nobody reaches: no document linked any of them, so the deliverable existed
// and the paper could not cite it. The index is written HERE, from the same manifest the figures were recorded in,
// because a hand-kept list of derived files is the blind spot this repository has already paid for twice — and the
// population is asserted, so a figure that fails to fall into exactly one bucket stops the run rather than going
// unlinked silently.
if (assigned.size !== manifest.length || manifest.some((m) => !assigned.has(m.name))) {
  const unlisted = manifest.filter((m) => !assigned.has(m.name)).map((m) => m.name);
  const unplanned = [...assigned.keys()].filter((n) => !manifest.some((m) => m.name === n));
  fail(`the index was planned for ${assigned.size} figure(s) but the run produced ${manifest.length} — unlinked [${unlisted.join(', ') || 'none'}], never drawn [${unplanned.join(', ') || 'none'}]`);
}
const indexLines = [
  '# Use-case diagrams',
  '',
  'Written by `node storage/app/scratch/lane208-use-case-figures.mjs` from the use-case graphs in',
  '`Use-Case-Diagram-Guide.md`. Edit that guide, then re-run the generator; retyping a figure here would make this',
  'page a second definition of a model the guide already owns.',
  '',
  `Model as read by the last run: ${nodes.size} nodes (${[...nodes.values()].filter((n) => n.kind === 'actor').length} actor declarations, ${[...nodes.values()].filter((n) => n.kind !== 'actor').length} use cases), ${count('association')} associations, ${count('include')} «include», ${count('extend')} «extend».`,
  '',
  'Both claims below are weighed by the generator before it writes this page — `--audit-actor-edges` and',
  '`--audit-complete-figure` — so the figures are known to hold what they promise rather than assumed to:',
  `- ${closure}.`,
  `- ${completeness}.`,
  '',
];
for (const b of buckets) {
  const inBucket = manifest.filter((m) => assigned.get(m.name) === b.title);
  indexLines.push('', `## ${b.title} (${inBucket.length})`, '');
  for (const m of inBucket) {
    indexLines.push(`### ${m.heading}`, '', `![${m.heading}](Diagrams/${m.name}.png)`, '');
  }
}
lines.push('', `Every figure on disk is linked above: ${manifest.length} in the manifest, ${manifest.length} here.`, '');
const indexPath = join(dirname(outDir), 'Use-Case-Diagrams.md');
writeFileSync(indexPath, indexLines.join('\n'));
console.log(`wrote the index that links them: ${indexPath}`);
