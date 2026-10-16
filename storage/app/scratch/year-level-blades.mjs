import { readFileSync, writeFileSync } from 'node:fs';

// The printed paper must say the same thing the desk screen says.
const edits = {
  'resources/views/prints/block-schedule.blade.php': [
    ['bare year digit on the block schedule',
      '<span class="info-value">{{ $block->yearLevel }}</span>',
      "<span class=\"info-value\">{{ $block->yearLevel ? 'Year '.$block->yearLevel : 'N/A' }}</span>"],
  ],
  'resources/views/prints/class-card.blade.php': [
    ['bare year digit on the class card',
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}",
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel ? 'Year '.$enrollment->yearLevel : 'Year N/A' }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}"],
  ],
  'resources/views/prints/enrollment-certificate.blade.php': [
    ['bare year digit on the certificate',
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}",
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel ? 'Year '.$enrollment->yearLevel : 'Year N/A' }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}"],
  ],
  'resources/views/prints/subject-load.blade.php': [
    ['bare year digit on the subject load',
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}",
      "{{ $enrollment->course->courseName }} - {{ $enrollment->yearLevel ? 'Year '.$enrollment->yearLevel : 'Year N/A' }}{{ $enrollment->major ? ' - '.$enrollment->major->majorName : '' }}"],
  ],
  'resources/views/prints/clearance-slip.blade.php': [
    ['"3 Year" on the clearance slip',
      "{{ $termEnrollment?->course?->courseName ?? 'N/A' }} - {{ $termEnrollment?->yearLevel ?? 'N/A' }} Year",
      "{{ $termEnrollment?->course?->courseName ?? 'N/A' }} - {{ $termEnrollment?->yearLevel ? 'Year '.$termEnrollment->yearLevel : 'Year N/A' }}"],
  ],
};

let failed = false;
for (const [rel, list] of Object.entries(edits)) {
  let src = readFileSync(rel, 'utf8');
  for (const [what, find, replace] of list) {
    if (!src.includes(find)) {
      console.log(`MISS  ${rel} :: ${what}`);
      failed = true;
      continue;
    }
    src = src.replace(find, replace);
    console.log(`ok    ${rel} :: ${what}`);
  }
  writeFileSync(rel, src);
}
process.exit(failed ? 1 : 0);
