// Read-only check of the goal's claim, in the unit the artefacts use.
//
// What this adds over the generator's own audits (which stay authoritative for what a figure DRAWS):
//   1. global reachability — every use case is reachable from SOME actor by following the model's edges,
//      and no node sits edgeless. The generator weighs disappearance inside one figure, so an actor-
//      less box would still be drawn as a box and pass; that is the defect class that caught UC_Srch360.
//   2. the figure set, the manifest rows and the index links are one set, not three lists kept by hand.
//
// What this deliberately does NOT re-count: how many figures carry an include/extend chain. The
// generator's audit answers that per figure over what was DRAWN (dedupe applied); a label-scoped count
// here would be a second definition of the same word, which is what this repository removes on sight.
import { readFileSync, existsSync } from 'node:fs';
import { join } from 'node:path';

// Every surface is addressable so a harness can plant a defect in a copy rather than edit the deliverable.
// An accepted-and-ignored flag is refused by name, because a run that ignores its own scope prints exactly
// the verdict a scoped one does.
const flag = (name, dflt) => {
  const hit = process.argv.slice(2).find((a) => a.startsWith(`--${name}=`));
  return hit === undefined ? dflt : hit.slice(name.length + 3);
};
const unknown = process.argv.slice(2).filter((a) => a.startsWith('--')
  && !['model', 'manifest', 'index', 'dir', 'figures'].some((n) => a.startsWith(`--${n}=`)));
if (unknown.length > 0) {
  console.error(`REFUSED: flag(s) not read by this tool: ${unknown.join(', ')}`);
  process.exit(1);
}
const dir = flag('dir', 'Documentation/Diagrams');
// where the PNGs live, so a harness can point --manifest= at a copy without losing the files
const figDir = flag('figures', dir);
const modelPath = flag('model', join(dir, 'model.json'));
const manifestPath = flag('manifest', join(dir, 'manifest.md'));
const indexPath = flag('index', 'Documentation/Use-Case-Diagrams.md');
for (const [label, p] of [['model', modelPath], ['manifest', manifestPath], ['index', indexPath]]) {
  if (!existsSync(p)) { console.error(`REFUSED: the ${label} surface [${p}] is not a file this tool can read.`); process.exit(1); }
}

const model = JSON.parse(readFileSync(modelPath, 'utf8'));
const manifest = readFileSync(manifestPath, 'utf8');
const index = readFileSync(indexPath, 'utf8');

const kindOf = new Map(model.nodes.map((n) => [n.id, n.kind]));
const labelOf = new Map(model.nodes.map((n) => [n.id, n.label]));
const bad = [];
const note = (msg) => { bad.push(msg); console.log(`BAD   ${msg}`); };
const observation = (msg) => console.log(`NOTE  ${msg}`);

// ---- 1. manifest rows are the record of which file is which figure
const rows = [...manifest.matchAll(/^\| `([^`]+\.png)` \| ([^|]+?) \| (\d+)×(\d+) \| (\d+) \|$/gm)]
  .map((m) => ({ file: m[1], shows: m[2].trim(), w: +m[3], h: +m[4], bytes: +m[5] }));
if (rows.length === 0) note('the manifest parsed no figure rows — the reader is blind, not the model clean');
const actorRows = rows.filter((r) => r.shows.startsWith('Actor:'));
const deskRows = rows.filter((r) => r.shows.startsWith('Desk '));
const otherRows = rows.filter((r) => !r.shows.startsWith('Actor:') && !r.shows.startsWith('Desk '));
console.log(`manifest: ${rows.length} figures = ${actorRows.length} actor + ${deskRows.length} desk + ${otherRows.length} overview/complete`);
if (actorRows.length + deskRows.length + otherRows.length !== rows.length) {
  note(`the buckets do not add to the ${rows.length} rows the manifest lists`);
}

for (const r of rows) {
  const p = join(figDir, r.file);
  if (!existsSync(p)) { note(`${r.file} is listed in the manifest but not on disk`); continue; }
  const size = readFileSync(p).length;
  if (size !== r.bytes) note(`${r.file} holds ${size} bytes where the manifest states ${r.bytes}`);
  if (!index.includes(r.file)) note(`${r.file} is not linked from Documentation/Use-Case-Diagrams.md`);
}
for (const link of index.matchAll(/\]\(([^)]*use-case-[^)]*\.png)\)/g)) {
  const base = link[1].split('/').pop();
  if (!rows.some((r) => r.file === base)) note(`the index links ${base}, which the manifest lists in no row`);
  if (!existsSync(join(figDir, base))) note(`the index links ${base}, which is not on disk`);
}

// ---- 2. one figure per distinct actor label, and none for a label the model no longer names
const declsByLabel = new Map();
for (const a of model.nodes.filter((n) => n.kind === 'actor')) {
  if (!declsByLabel.has(a.label)) declsByLabel.set(a.label, []);
  declsByLabel.get(a.label).push(a);
}
const figLabels = new Set(actorRows.map((r) => r.shows.replace(/^Actor:\s*/, '').trim()));
if (figLabels.size !== actorRows.length) note('two actor figures carry the same label — one actor, two answers');
for (const label of declsByLabel.keys()) {
  if (!figLabels.has(label)) note(`actor label "${label}" names no figure in the manifest`);
}
for (const label of figLabels) {
  if (!declsByLabel.has(label)) note(`the manifest draws an actor figure for "${label}", which the model declares nowhere`);
}

// ---- 3. what each actor owes, at model scope (a reading, not a competing verdict)
const assocOfLabel = (ids) => {
  const set = new Set(ids);
  const cases = new Set();
  for (const e of model.edges) {
    if (e.kind !== 'association') continue;
    if (set.has(e.from)) cases.add(e.to);
    if (set.has(e.to)) cases.add(e.from);           // a desk block may write the line actor-side-last
  }
  return [...cases].filter((i) => kindOf.get(i) === 'usecase');
};
const totals = {};
for (const e of model.edges) totals[e.kind] = (totals[e.kind] ?? 0) + 1;
console.log(`\nmodel: ${model.nodes.length} nodes, ${declsByLabel.size} distinct actor labels folded from`
  + ` ${model.nodes.filter((n) => n.kind === 'actor').length} declarations, ${totals.association} associations,`
  + ` ${totals.include} include, ${totals.extend} extend, ${totals.dependency ?? 0} dependency`);
console.log(`\nper-actor reading (model scope; the generator's own audit is authoritative for what a figure draws):`);
const noAssocLabels = [];
for (const [label, decls] of declsByLabel) {
  const cases = assocOfLabel(decls.map((d) => d.id));
  const ids = new Set(decls.map((d) => d.id));
  const others = new Set();
  for (const e of model.edges) {
    if (e.kind !== 'association') continue;
    if (ids.has(e.from)) others.add(labelOf.get(e.to) ?? e.to);
    if (ids.has(e.to)) others.add(labelOf.get(e.from) ?? e.from);
  }
  others.delete(label);
  if (cases.length === 0) noAssocLabels.push([label, decls, others]);
  console.log('  ', String(cases.length).padStart(3), String(others.size).padStart(3),
    decls.length > 1 ? `x${decls.length}`.padStart(4) : '   ', label);
}
console.log(`  cols: use cases named | other actors sharing them | declarations folded`);

// An actor with no association is a category, not automatically a defect: an external system reached only
// by a message edge participates without initiating. Name it, and let the owner rule whether it should join
// the association set (task #62) — do not silently pass it and do not silently fail it.
for (const [label, decls, others] of noAssocLabels) {
  const edgesHere = model.edges.filter((e) => {
    const ids = new Set(decls.map((d) => d.id));
    return ids.has(e.from) || ids.has(e.to);
  });
  observation(`"${label}" is drawn with no association line; its edges are ${JSON.stringify(edgesHere.map((e) => `${e.kind}: ${labelOf.get(e.from)} -> ${labelOf.get(e.to)}`))}`
    + ` — an external actor that only receives. Feeds task #62, not a rendering defect.`);
}

// ---- 4. global reachability: this is the check the generator cannot make
const adj = new Map();
for (const e of model.edges) {
  if (!adj.has(e.from)) adj.set(e.from, new Set());
  adj.get(e.from).add(e.to);
  if (!adj.has(e.to)) adj.set(e.to, new Set());      // an association is undirected
  adj.get(e.to).add(e.from);
}
const reach = new Set();
const stack = model.nodes.filter((n) => n.kind === 'actor').map((n) => n.id);
for (const id of stack) reach.add(id);
const walk = [...stack];
while (walk.length) {
  const n = walk.pop();
  for (const t of adj.get(n) ?? []) if (!reach.has(t)) { reach.add(t); walk.push(t); }
}
const unreachableCases = model.nodes.filter((n) => n.kind === 'usecase' && !reach.has(n.id));
if (unreachableCases.length > 0) {
  note(`${unreachableCases.length} use case(s) no actor can reach even transitively:`
    + ` ${unreachableCases.map((u) => u.label).join(', ')} — a box nobody can arrive at is not a use case`);
} else {
  console.log(`\nevery one of the ${model.census.useCases} use cases is reachable from an actor by following the edges`);
}
const noDirectAssoc = model.nodes.filter((n) => n.kind === 'usecase')
  .filter((n) => !model.edges.some((e) => e.kind === 'association' && (e.from === n.id || e.to === n.id)));
console.log(`${noDirectAssoc.length} use case(s) carry no association of their own and arrive through an`
  + ` «include»/«extend» chain — legitimate in UML, so each is named here rather than failed:`);
for (const n of noDirectAssoc) {
  const es = model.edges.filter((e) => (e.from === n.id || e.to === n.id) && e.kind !== 'association');
  if (es.length === 0) note(`"${n.label}" is reached by no association AND by no stereotype edge — an island`);
  else console.log('   ', n.label.padEnd(52), es.map((e) => `${e.kind}${e.from === n.id ? ' ->' : ' -> '} ${labelOf.get(e.from === n.id ? e.to : e.from)}`).join('; '));
}
const edgeless = model.nodes.filter((n) => !model.edges.some((e) => e.from === n.id || e.to === n.id));
if (edgeless.length > 0) note(`${edgeless.length} node(s) hold no edge at all: ${edgeless.map((n) => n.label).join(', ')}`);
else console.log(`no node in the model sits edgeless (the UC_Srch360 class, re-weighed)`);

// ---- 5. the actor vocabulary: a role, not a stage
// `Documentation/Use-Case-Diagram-Guide.md` states that a person is named by ROLE and that the stage words
// (Applicant, Examinee, Payer) belong to the prose tables, never to an actor declaration. A rule written into a
// guide and enforced by nothing is the defect this repository removes on sight, so the promise is weighed here:
// after the 2026-11-03 fold the model declares 41 actors across 19 roles and none of them spells a stage.
const STAGE_WORDS = ['Applicant', 'Examinee', 'Payer'];
const staged = [...declsByLabel.keys()].filter((l) => STAGE_WORDS.some((w) => l.includes(w)));
if (staged.length > 0) {
  note(`${staged.length} actor label(s) name a stage rather than a role: ${staged.join(', ')}`
    + ' — the guide says a person is labelled by role; the tables keep the stage words');
} else {
  console.log(`every actor label names a role, none a stage (${STAGE_WORDS.join(', ')} do not appear)`);
}

// ---- 6. stereotype wording must reach the artefact
const unlabeled = model.edges.filter((e) => (e.kind === 'include' || e.kind === 'extend') && !String(e.label).includes('«'));
if (unlabeled.length > 0) {
  note(`${unlabeled.length} stereotype edge(s) carry no «include»/«extend» word — mermaid draws an empty box:`
    + ` ${unlabeled.map((e) => `${labelOf.get(e.from)} -> ${labelOf.get(e.to)}`).join(', ')}`);
} else {
  console.log(`all ${totals.include + totals.extend} «include»/«extend» edges carry their UML word`);
}

// ---- 6. read the generator's own verdicts rather than re-deriving them
const actorAudit = manifest.match(/Per-actor closure[^\n]*?(\d+) actor figure\(s\); (\d+) carry at least one include\/extend chain; (\d+) owed dependency edge\(s\) dropped/);
if (!actorAudit) note('the manifest names no per-actor closure audit');
else {
  console.log(`\ngenerator's per-actor audit (its own unit, drawn-edge scope): ${actorAudit[1]} figures,`
    + ` ${actorAudit[2]} carry a chain, ${actorAudit[3]} owed edge(s) dropped`);
  if (+actorAudit[1] !== actorRows.length) note(`audit names ${actorAudit[1]} actor figures where the manifest lists ${actorRows.length}`);
  if (+actorAudit[3] !== 0) note(`the generator dropped ${actorAudit[3]} owed dependency edge(s) from an actor figure`);
}
const ledger = manifest.match(/model (\d+) nodes \((\d+) actor declarations, (\d+) use cases\) fold to (\d+) distinct shapes, drawn (\d+); model (\d+) edges, drawn (\d+), folded as restatement (\d+) and as merged endpoints (\d+)/);
if (!ledger) note('the manifest names no completeness ledger for the headline figure');
else {
  const [, nNodes, nDecl, nCases, nShapes, nDrawn, nEdges, nEdgeDrawn, nFold, nMerge] = ledger.map((v, i) => (i ? +v : v));
  const shapeKinds = new Set(model.nodes.map((n) => `${n.kind}|${n.label}`)).size;
  console.log(`generator's completeness ledger: ${nNodes} nodes -> ${nShapes} shapes drawn ${nDrawn};`
    + ` ${nEdges} edges -> ${nEdgeDrawn} drawn + ${nFold} restatement + ${nMerge} merged`);
  if (nNodes !== model.nodes.length) note(`ledger counts ${nNodes} nodes where model.json holds ${model.nodes.length}`);
  if (nDecl !== model.nodes.filter((n) => n.kind === 'actor').length) note('ledger and model disagree on actor declarations');
  if (nCases !== model.census.useCases) note('ledger and model disagree on the use-case count');
  if (nShapes !== shapeKinds) note(`ledger folds to ${nShapes} shapes where ${shapeKinds} distinct kind|label pairs exist`);
  if (nDrawn !== nShapes) note(`${nShapes} shapes fold but ${nDrawn} were drawn — a shape disappeared`);
  if (nEdges !== model.edges.length) note(`ledger counts ${nEdges} edges where model.json holds ${model.edges.length}`);
  if (nEdgeDrawn + nFold + nMerge !== nEdges) note(`the edge ledger does not close: ${nEdgeDrawn}+${nFold}+${nMerge} != ${nEdges}`);
}

console.log(bad.length === 0
  ? `\nAGREES: ${rows.length} figures on disk, each named by the manifest and linked from the index;`
    + ` ${actorRows.length} actor figures = ${declsByLabel.size} distinct actor labels; every use case reachable;`
    + ` both audits close their ledgers`
  : `\nBAD: ${bad.length} finding(s) above`);
process.exit(bad.length === 0 ? 0 : 1);
