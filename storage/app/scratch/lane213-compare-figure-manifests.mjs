import { readFileSync } from 'node:fs';

const rows = (f) => readFileSync(f, 'utf8').split('\n')
  .filter((l) => l.startsWith('| `use-case'))
  .map((l) => l.split('|').map((s) => s.trim()));

const old = rows(process.argv[2]);
const nw = rows(process.argv[3]);
const om = new Map(old.map((r) => [r[1], r]));
const nm = new Map(nw.map((r) => [r[1], r]));

const clean = (k) => k.replace('`use-case-', '').replace('.png`', '');
const dim = [];
const bytes = [];
let missing = 0;
for (const [k, o] of om) {
  const n = nm.get(k);
  if (!n) { missing++; console.log(`MISSING IN NEW: ${k}`); continue; }
  if (o[3] !== n[3]) dim.push(`${clean(k)}  ${o[3]} -> ${n[3]}  (bytes ${o[4]} -> ${n[4]})`);
  else if (o[4] !== n[4]) bytes.push(clean(k));
}
console.log(`figures compared: ${om.size} old vs ${nm.size} new; missing ${missing}`);
console.log(`dimension changed: ${dim.length}`);
for (const d of dim) console.log(`  ${d}`);
console.log(`same dimensions, different bytes: ${bytes.length}`);
for (const b of bytes.slice(0, 8)) console.log(`  ${b}`);
