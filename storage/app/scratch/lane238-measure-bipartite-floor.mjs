/**
 * What is the FEWEST crossings a straight-line actor↔desk overview can have, given the pairs the guide really holds?
 * The owner asked for a sheet whose lines do not cross, so "fewer" is not the deliverable — the floor is. This reads
 * the same model the figures are drawn from and counts crossings exactly as a two-column straight-edge drawing
 * produces them: edges (a,b) and (a',b') cross iff the two endpoints are ordered differently, and an edge that shares
 * an endpoint with another cannot cross it. The middle column is the desk bands, so the left fan (minor roles) and
 * the right fan (major roles) are independent problems that share ONE band order.
 *
 * It searches that band order by adjacent-swap descent and each actor column by the same descent, then prints the
 * best count found, the order, and whether zero is reached. A descent can miss an optimum, so a NON-zero answer is
 * read as "this ordering family did not get here", never as a proof that no crossing-free drawing exists.
 *
 * Usage: node storage/app/scratch/lane238-measure-bipartite-floor.mjs [--model=path] [--tries=N]
 */
import { readFileSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..', '..');
let modelPath = resolve(root, 'Documentation', 'Diagrams', 'model.json');
let tries = 6;
for (const a of process.argv.slice(2)) {
  if (a.startsWith('--model=')) modelPath = resolve(root, a.slice(8));
  else if (a.startsWith('--tries=')) tries = Number(a.slice(8));
  else {
    console.error(`REFUSED: this tool reads --model= and --tries=; it was given [${a}]`);
    process.exit(1);
  }
}
if (!Number.isInteger(tries) || tries < 1) {
  console.error(`REFUSED: --tries must be a whole number of restarts, got [${tries}]`);
  process.exit(1);
}

const model = JSON.parse(readFileSync(modelPath, 'utf8'));
// An id is declared once per block, so `A_Adm` exists under the boundary block and under a desk block: the pair the
// figure draws is (declaring desk, id), which is the same composite the generator keys its node map by. Keying on the
// bare id makes every lookup miss and the census refuses an empty population.
const byKey = new Map(model.nodes.map((n) => [`${n.desk}|${n.id}`, n]));
// One plate per role LABEL, one band per desk, one line per role↔desk pair that a real association states —
// the same fold the generator applies, read here as the pairs to lay out.
const pairs = new Map();
for (const e of model.edges) {
  if (e.kind !== 'association') continue;
  const a = byKey.get(`${e.desk}|${e.from}`);
  const b = byKey.get(`${e.desk}|${e.to}`);
  if (!a || !b || a.kind === b.kind) continue;
  const actor = a.kind === 'actor' ? a : b;
  const other = a.kind === 'actor' ? b : a;
  if (!actor.label || other.desk === '00' || !other.desk) continue;
  const k = `${actor.label}\u0000${other.desk}`;
  pairs.set(k, (pairs.get(k) ?? 0) + 1);
}
if (pairs.size === 0) {
  console.error('REFUSED: the model answered no role-desk pair, so there is nothing to lay out');
  process.exit(1);
}
const desks = [...new Set(model.nodes.filter((n) => n.kind !== 'actor' && n.desk !== '00' && n.desk).map((n) => n.desk))].sort();
const roles = [...new Set([...pairs.keys()].map((k) => k.split('\u0000')[0]))];
const degree = new Map();
for (const k of pairs.keys()) {
  const [label] = k.split('\u0000');
  degree.set(label, (degree.get(label) ?? 0) + pairs.get(k));
}
const ranked = [...roles].sort((x, y) => (degree.get(y) ?? 0) - (degree.get(x) ?? 0) || x.localeCompare(y));
const half = Math.ceil(ranked.length / 2);
const sides = { major: ranked.slice(0, half), minor: ranked.slice(half) };
const edgesOf = (side) => {
  const rows = [];
  for (const k of pairs.keys()) {
    const [label, d] = k.split('\u0000');
    if (!side.includes(label)) continue;
    rows.push([label, d]);
  }
  return rows;
};

/** Crossings between one actor column and the band column, both given as orders. */
function countCrossings(actorOrder, bandOrder, rows) {
  const posA = new Map(actorOrder.map((l, i) => [l, i]));
  const posB = new Map(bandOrder.map((d, i) => [d, i]));
  const edges = rows.filter(([l, d]) => posA.has(l) && posB.has(d))
    .map(([l, d]) => [posA.get(l), posB.get(d)]);
  let n = 0;
  for (let i = 0; i < edges.length; i++) {
    for (let j = i + 1; j < edges.length; j++) {
      const [a1, b1] = edges[i];
      const [a2, b2] = edges[j];
      if (a1 === a2 || b1 === b2) continue;
      if ((a1 - a2) * (b1 - b2) < 0) n++;
    }
  }
  return n;
}

/** Adjacent-swap descent on one order, holding the other two fixed. */
function descend(order, score) {
  const best = order.slice();
  let cur = score(best);
  let moved = true;
  while (moved) {
    moved = false;
    for (let i = 0; i + 1 < best.length; i++) {
      const trial = best.slice();
      [trial[i], trial[i + 1]] = [trial[i + 1], trial[i]];
      const s = score(trial);
      if (s < cur) {
        best[i] = trial[i]; best[i + 1] = trial[i + 1];
        cur = s;
        moved = true;
      }
    }
  }
  return { order: best, count: cur };
}

const rowsMinor = edgesOf(sides.minor);
const rowsMajor = edgesOf(sides.major);
const totalFor = (bandOrder) => {
  const m = descend(sides.minor.slice(), (o) => countCrossings(o, bandOrder, rowsMinor));
  const M = descend(sides.major.slice(), (o) => countCrossings(o, bandOrder, rowsMajor));
  return { band: bandOrder, minor: m, major: M, total: m.count + M.count };
};

let best = totalFor(desks);
for (let t = 1; t < tries; t++) {
  // A restart from a shuffled band order, because the numeric reading of the desks is a *human* order and the
  // crossing floor is a property of the graph, not of the desk numbers.
  const shuffled = desks.slice();
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  const candidate = totalFor(shuffled);
  if (candidate.total < best.total) best = candidate;
}
// One more descent over the band order itself, holding the two actor orders found above.
const fixedMinor = best.minor.order;
const fixedMajor = best.major.order;
const bandDesc = descend(best.band, (o) => countCrossings(fixedMinor, o, rowsMinor) + countCrossings(fixedMajor, o, rowsMajor));
const reScored = totalFor(bandDesc.order);
if (reScored.total < best.total) best = reScored;

console.log(`${pairs.size} role-desk line(s), ${roles.length} roles (${sides.major.length} major, ${sides.minor.length} minor), ${desks.length} bands`);
console.log(`lowest crossing count found by the descent: ${best.total} `
  + `(left fan ${best.minor.count}, right fan ${best.major.count}) over ${tries} restart(s)`);
console.log(`band order at that minimum: ${best.band.join(', ')}`);
console.log(`minor column top→bottom: ${best.minor.order.join(' | ')}`);
console.log(`major column top→bottom: ${best.major.order.join(' | ')}`);
console.log(best.total === 0
  ? 'A CROSSING-FREE straight-line layout EXISTS in this ordering family.'
  : `No ordering this descent reached is crossing-free; ${best.total} pair(s) still overlap.`);
process.exit(0);
