import { readFileSync, writeFileSync } from 'node:fs';

const files = {
  'resources/js/Pages/Blocking/Index.jsx': [
    ["render: `${row.yearLevel}${getYearSuffix(row.yearLevel)} Year` ternary",
      "row.yearLevel ? `${row.yearLevel}${getYearSuffix(row.yearLevel)} Year` : '—'",
      "formatYearLevel(row.yearLevel)"],
    ["filter option labels", "label: '1st Year'", "label: 'Year 1'"],
    ["filter option labels", "label: '2nd Year'", "label: 'Year 2'"],
    ["filter option labels", "label: '3rd Year'", "label: 'Year 3'"],
    ["filter option labels", "label: '4th Year'", "label: 'Year 4'"],
    ["filter option labels", "label: '5th Year'", "label: 'Year 5'"],
  ],
  'resources/js/Pages/Blocking/PrintSchedule.jsx': [
    ["printed year level", "{block.yearLevel}{getYearSuffix(block.yearLevel)} Year", "{formatYearLevel(block.yearLevel)}"],
  ],
  'resources/js/Pages/Blocking/Show.jsx': [
    ["subtitle", "${block.yearLevel}${getYearSuffix(block.yearLevel)}", "${formatYearLevel(block.yearLevel)}"],
    ["printed year level", "{block.yearLevel}{getYearSuffix(block.yearLevel)} Year", "{formatYearLevel(block.yearLevel)}"],
  ],
  'resources/js/Pages/Registrar/Index.jsx': [
    ["render ternary", "row.yearLevel ? `${row.yearLevel}${getYearSuffix(row.yearLevel)} Year` : '—'", "formatYearLevel(row.yearLevel)"],
  ],
  'resources/js/Pages/Registrar/PrintClassCards.jsx': [
    ["printed year level", "{enrollment.yearLevel ? `${enrollment.yearLevel}${getYearSuffix(enrollment.yearLevel)} Year` : '—'}", "{formatYearLevel(enrollment.yearLevel)}"],
  ],
  'resources/js/Pages/Registrar/PrintSubjectLoad.jsx': [
    ["printed year level", "{enrollment.yearLevel ? `${enrollment.yearLevel}${getYearSuffix(enrollment.yearLevel)} Year` : '—'}", "{formatYearLevel(enrollment.yearLevel)}"],
  ],
  'resources/js/Pages/Assessment/Index.jsx': [
    ["queue subtitle", "(Yr {ev.yearLevel})", "({formatYearLevel(ev.yearLevel)})"],
  ],
  'resources/js/Pages/Evaluation/Index.jsx': [
    ["render ternary", "row.yearLevel ? `Year ${row.yearLevel}` : '—'", "formatYearLevel(row.yearLevel)"],
  ],
  'resources/js/Pages/Registrar/PrintCertificate.jsx': [
    ["printed year level", "{enrollment.yearLevel ? `Year ${enrollment.yearLevel}` : '—'}", "{formatYearLevel(enrollment.yearLevel)}"],
  ],
  'resources/js/Pages/Registrar/Show.jsx': [
    ["approval summary", "Year {enrollment.yearLevel} (", "{formatYearLevel(enrollment.yearLevel)} ("],
  ],
  'resources/js/Pages/Clearance/PrintSlip.jsx': [
    ["printed year level", "{termEnrollment?.yearLevel || '—'}", "{formatYearLevel(termEnrollment?.yearLevel)}"],
  ],
  'resources/js/Pages/Evaluation/Show.jsx': [
    ["standing picker", "{`Year ${level}`}", "{formatYearLevel(level)}"],
  ],
};

const helperPatterns = [
  /const getYearSuffix = \(year\) => \{\s*\n\s*switch \(year\) \{\s*\n\s*case 1: return 'st';\s*\n\s*case 2: return 'nd';\s*\n\s*case 3: return 'rd';\s*\n\s*default: return 'th';\s*\n\s*\}\s*\n\};\n\n/,
  /function getYearSuffix\(year\) \{\s*\n\s*if \(year === 1\) return 'st';\s*\n\s*if \(year === 2\) return 'nd';\s*\n\s*if \(year === 3\) return 'rd';\s*\n\s*return 'th';\s*\n\}\n\n/,
];

const report = [];
let failed = false;

for (const [rel, edits] of Object.entries(files)) {
  let src = readFileSync(rel, 'utf8');
  const notes = [];

  for (const [what, find, replace] of edits) {
    if (!src.includes(find)) {
      report.push(`MISS  ${rel} :: ${what} :: pattern not found`);
      failed = true;
      continue;
    }
    src = src.split(find).join(replace);
    notes.push(`edit(${what})`);
  }

  let removed = 0;
  for (const re of helperPatterns) {
    src = src.replace(re, () => { removed++; return ''; });
  }
  if (src.includes('getYearSuffix')) {
    report.push(`LEFT  ${rel} :: getYearSuffix still referenced`);
    failed = true;
  } else if (removed === 1) {
    notes.push('helper removed');
  }

  if (src.includes('formatYearLevel')) {
    if (/from '@\/Components\/ui';/.test(src)) {
      src = src.replace(/import \{([^}]*)\} from '@\/Components\/ui';/, (m, names) =>
        names.split(',').some((n) => n.trim() === 'formatYearLevel') ? m : `import {${names.replace(/\s*$/, '')}, formatYearLevel } from '@/Components/ui';`);
      notes.push('import extended');
    } else {
      src = src.replace(/^/, "import { formatYearLevel } from '@/Components/ui';\n");
      notes.push('import added');
    }
  }

  writeFileSync(rel, src);
  report.push(`ok    ${rel} :: ${notes.join(', ') || 'no-op'}`);
}

const leftover = Object.keys(files).filter((f) => readFileSync(f, 'utf8').includes('getYearSuffix'));
report.push(`leftover files: ${leftover.length}`);
console.log(report.join('\n'));
process.exit(failed || leftover.length ? 1 : 0);
