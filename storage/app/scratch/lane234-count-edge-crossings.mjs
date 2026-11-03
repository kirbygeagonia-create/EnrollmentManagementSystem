#!/usr/bin/env node
/**
 * How many times do the lines of a drawn figure cross each other?
 *
 * The owner's ruling on the complete use-case figure is that its connectors must not cross or intersect. That is a
 * property of the rendered artefact, so it is measured on the artefact: this reads a mermaid SVG, takes every edge
 * path, flattens its curves into sampled points, and counts proper intersections between pairs of edges. Two edges
 * that MEET at a shared node are not a crossing, so intersections within `--near=<px>` of either endpoint are
 * excluded — that tolerance is printed, because a number without its rule is not a measurement.
 *
 * Usage: node storage/app/scratch/lane234-count-edge-crossings.mjs <a.svg> [<b.svg> …] [--near=14] [--samples=18]
 * Refusals (exit 1): a file that is not there, a file with no edge paths at all, an SVG whose paths could not be
 * parsed — a reader that saw nothing must never be able to report zero crossings.
 */
import { readFileSync, existsSync } from 'node:fs';
import { resolve } from 'node:path';

const args = process.argv.slice(2);
const files = [];
let near = 14;
let samples = 18;

for (const a of args) {
  if (a.startsWith('--near=')) near = Number(a.slice(7));
  else if (a.startsWith('--samples=')) samples = Number(a.slice(10));
  else if (a.startsWith('--')) {
    console.error(`REFUSED: this tool does not read the flag ${a}`);
    process.exit(1);
  } else files.push(a);
}
if (files.length === 0) {
  console.error('REFUSED: at least one .svg is required.');
  process.exit(1);
}
if (!Number.isFinite(near) || near < 0 || !Number.isInteger(samples) || samples < 4) {
  console.error(`REFUSED: --near must be a non-negative number and --samples an integer of 4 or more (got ${near}, ${samples}).`);
  process.exit(1);
}

/** Commands in a path `d` string, as [letter, …numbers]. */
function commands(d) {
  const out = [];
  const re = /([MmLlHhVvCcSsQqTtAaZz])([^MmLlHhVvCcSsQqTtAaZz]*)/g;
  let m;
  while ((m = re.exec(d)) !== null) {
    const nums = (m[2].match(/-?\d*\.?\d+(?:e[-+]?\d+)?/gi) || []).map(Number);
    out.push([m[1], nums]);
  }
  return out;
}

/** Flatten one path into a polyline of absolute points, sampling each cubic. */
function polyline(d) {
  const pts = [];
  let cx = 0;
  let cy = 0;
  let sx = 0;
  let sy = 0;
  let last = null;
  for (const [cmd, nums] of commands(d)) {
    const rel = cmd === cmd.toLowerCase();
    const L = cmd.toUpperCase();
    if (L === 'M') {
      for (let i = 0; i + 1 < nums.length; i += 2) {
        const x = rel ? cx + nums[i] : nums[i];
        const y = rel ? cy + nums[i + 1] : nums[i + 1];
        if (i === 0) {
          cx = sx = x;
          cy = sy = y;
          pts.push([x, y]);
        } else pts.push([x, y]);
      }
    } else if (L === 'L') {
      for (let i = 0; i + 1 < nums.length; i += 2) {
        cx = rel ? cx + nums[i] : nums[i];
        cy = rel ? cy + nums[i + 1] : nums[i + 1];
        pts.push([cx, cy]);
      }
    } else if (L === 'H') {
      for (const n of nums) {
        cx = rel ? cx + n : n;
        pts.push([cx, cy]);
      }
    } else if (L === 'V') {
      for (const n of nums) {
        cy = rel ? cy + n : n;
        pts.push([cx, cy]);
      }
    } else if (L === 'C') {
      for (let i = 0; i + 5 < nums.length; i += 6) {
        const p = [nums[i], nums[i + 1], nums[i + 2], nums[i + 3], nums[i + 4], nums[i + 5]].map((v, j) => (rel ? (j % 2 ? cy : cx) + v : v));
        for (let s = 1; s <= samples; s++) {
          const t = s / samples;
          const mt = 1 - t;
          const x = mt ** 3 * cx + 3 * mt ** 2 * t * p[0] + 3 * mt * t ** 2 * p[2] + t ** 3 * p[4];
          const y = mt ** 3 * cy + 3 * mt ** 2 * t * p[1] + 3 * mt * t ** 2 * p[3] + t ** 3 * p[5];
          pts.push([x, y]);
        }
        cx = p[4];
        cy = p[5];
      }
    } else if (L === 'Q') {
      for (let i = 0; i + 3 < nums.length; i += 4) {
        const p = [nums[i], nums[i + 1], nums[i + 2], nums[i + 3]].map((v, j) => (rel ? (j % 2 ? cy : cx) + v : v));
        for (let s = 1; s <= samples; s++) {
          const t = s / samples;
          const mt = 1 - t;
          pts.push([mt ** 2 * cx + 2 * mt * t * p[0] + t ** 2 * p[2], mt ** 2 * cy + 2 * mt * t * p[1] + t ** 2 * p[3]]);
        }
        cx = p[2];
        cy = p[3];
      }
    } else if (L === 'S' || L === 'T') {
      const step = L === 'S' ? 4 : 2;
      for (let i = 0; i + step - 1 < nums.length; i += step) {
        const tail = L === 'S'
          ? [nums[i], nums[i + 1], nums[i + 2], nums[i + 3]]
          : [nums[i], nums[i + 1]];
        const abs = tail.map((v, j) => (rel ? (j % 2 ? cy : cx) + v : v));
        if (L === 'S') {
          const r1x = last && last[0].toUpperCase() === 'C' ? 2 * cx - last[1][4] : cx;
          const r1y = last && last[0].toUpperCase() === 'C' ? 2 * cy - last[1][5] : cy;
          for (let s = 1; s <= samples; s++) {
            const t = s / samples;
            const mt = 1 - t;
            const x = mt ** 3 * cx + 3 * mt ** 2 * t * r1x + 3 * mt * t ** 2 * abs[0] + t ** 3 * abs[2];
            const y = mt ** 3 * cy + 3 * mt ** 2 * t * r1y + 3 * mt * t ** 2 * abs[1] + t ** 3 * abs[3];
            pts.push([x, y]);
          }
          cx = abs[2];
          cy = abs[3];
        } else {
          const r1x = last && last[0].toUpperCase() === 'Q' ? 2 * cx - last[1][0] : cx;
          const r1y = last && last[0].toUpperCase() === 'Q' ? 2 * cy - last[1][1] : cy;
          for (let s = 1; s <= samples; s++) {
            const t = s / samples;
            const mt = 1 - t;
            pts.push([mt ** 2 * cx + 2 * mt * t * r1x + t ** 2 * abs[0], mt ** 2 * cy + 2 * mt * t * r1y + t ** 2 * abs[1]]);
          }
          cx = abs[0];
          cy = abs[1];
        }
      }
    } else if (L === 'Z') {
      pts.push([sx, sy]);
      cx = sx;
      cy = sy;
    }
    last = [cmd, nums];
  }
  return pts.filter((p) => p.every((v) => Number.isFinite(v)));
}

function segIntersect(a, b, c, d) {
  const r = [b[0] - a[0], b[1] - a[1]];
  const s = [d[0] - c[0], d[1] - c[1]];
  const den = r[0] * s[1] - r[1] * s[0];
  if (Math.abs(den) < 1e-12) return null;
  const t = ((c[0] - a[0]) * s[1] - (c[1] - a[1]) * s[0]) / den;
  const u = ((c[0] - a[0]) * r[1] - (c[1] - a[1]) * r[0]) / den;
  if (t <= 1e-9 || t >= 1 - 1e-9 || u <= 1e-9 || u >= 1 - 1e-9) return null;
  return [a[0] + t * r[0], a[1] + t * r[1]];
}

const dist = (p, q) => Math.hypot(p[0] - q[0], p[1] - q[1]);

let exitCode = 0;
const census = [];
for (const file of files) {
  const path = resolve(file);
  if (!existsSync(path)) {
    console.error(`REFUSED: ${path} is not on disk.`);
    process.exit(1);
  }
  const svg = readFileSync(path, 'utf8');

  // Edge paths in a mermaid flowchart carry a class naming them an edge; node outlines (rects, ellipses, the cluster
  // borders) are NOT connectors, so taking every <path> in the file would accuse a box of crossing a line.
  const edgeRe = /<path[^>]*\bd="([^"]+)"[^>]*\/?>(?:<\/path>)?/g;
  const edges = [];
  let m;
  while ((m = edgeRe.exec(svg)) !== null) {
    const tag = m[0];
    if (!/class="[^"]*(edge|path-outer|message|flowchart-link|divider)/i.test(tag)) continue;
    if (/stroke-(width="0")|display:\s*none/i.test(tag)) continue;
    const pl = polyline(m[1]);
    if (pl.length >= 2) edges.push(pl);
  }
  if (edges.length === 0) {
    console.error(`REFUSED: ${path} yielded no edge paths, so a crossing count from it would be a story about nothing.`);
    process.exit(1);
  }

  let crossings = 0;
  const touches = [];
  for (let i = 0; i < edges.length; i++) {
    for (let j = i + 1; j < edges.length; j++) {
      const A = edges[i];
      const B = edges[j];
      let found = null;
      for (let a = 0; a + 1 < A.length && !found; a++) {
        for (let b = 0; b + 1 < B.length && !found; b++) {
          const p = segIntersect(A[a], A[a + 1], B[b], B[b + 1]);
          if (!p) continue;
          const endA = dist(p, A[0]) < near || dist(p, A[A.length - 1]) < near;
          const endB = dist(p, B[0]) < near || dist(p, B[B.length - 1]) < near;
          if (endA || endB) continue;
          found = p;
        }
      }
      if (found) {
        crossings++;
        if (touches.length < 6) touches.push(`edges ${i} x ${j} at ${found[0].toFixed(0)},${found[1].toFixed(0)}`);
      }
    }
  }

  // `split('/')` alone leaves a Windows path whole, so the census line printed the absolute path where it meant a
  // filename — and a grep for the figure name then matched nothing while the numbers were right.
  console.log(`${path.split(/[/\\]/).pop()}: ${edges.length} edge(s), ${crossings} crossing pair(s) [endpoint tolerance ${near}px, ${samples} samples per curve]`);
  census.push({ file: path.split(/[/\\]/).pop(), edges: edges.length, crossings });
  for (const t of touches) console.log('   ', t);
  // A tally with fewer printed rows than it counts is the queue-not-a-gate defect inside my own census: the reader
  // cannot tell which pairs were never shown. Name the withheld count instead of staying quiet about it.
  if (crossings > touches.length) {
    console.log(`    … ${crossings - touches.length} further crossing pair(s) NOT PRINTED (the row cap is ${touches.length}); this list is INCOMPLETE`);
  }
  if (crossings > 0) exitCode = 1;
}
// The footer is the census: how many sheets were opened, how many carry any overlap, and the total. A per-file list
// without it makes the reader add up 35 lines by eye, and an exit code alone cannot say WHICH sheets are clean.
if (census.length !== files.length) {
  console.error(`REFUSED: ${files.length} file(s) were asked for and ${census.length} came back judged.`);
  process.exit(1);
}
const dirty = census.filter((c) => c.crossings > 0);
console.log(`figures read: ${census.length}, edges ${census.reduce((s, c) => s + c.edges, 0)}, `
  + `crossing pairs ${census.reduce((s, c) => s + c.crossings, 0)} over ${dirty.length} figure(s)`);
console.log(`  clean: ${census.filter((c) => c.crossings === 0).length} of ${census.length} figure(s) draw every line without an overlap`);
if (dirty.length > 0) {
  console.log(`  not clean: ${dirty.map((c) => `${c.file} (${c.crossings})`).join(', ')}`);
}
console.log(exitCode === 0 ? 'NO CROSSINGS in the figures read.' : 'CROSSINGS PRESENT: the lines above overlap each other away from any node.');
process.exit(exitCode);
