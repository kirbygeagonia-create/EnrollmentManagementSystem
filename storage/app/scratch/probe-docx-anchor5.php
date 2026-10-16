<?php
// Batch two: the remaining pass-43 needles. Numbers are the usual suspects — a digit run is
// often its own <w:t>, which splits the sentence for substring purposes.
$src = 'Documentation/EMS Complete Documentation.docx';
$zip = new ZipArchive();
$zip->open($src);
$xml = $zip->getFromName('word/document.xml');
$zip->close();

foreach ([
    '87 permission definitions',
    'permission definitions',
    'Role::firstOrCreate calls',
    'firstOrCreate',
    '(87 permissions, 17 roles)',
    '87 permissions',
    'the permission set 87',
    'the permission set',
    'the three print rights',
    'three print rights',
    'because no such path exists',
    'clearance.requirement.approve',
    'Four of the rows above are enforced nowhere',
    'enforced nowhere',
    'Nothing. No controller checks it either',
    'No controller checks it either',
    'and no controller guard',
    'yearLevel is written as 1 at creation',
    'written as 1 at creation',
    'no guard for it exists anywhere in app/',
    'Nothing stops a second, simultaneously active enrollment',
    'Four of the five desk prints write the row',
    'Over-capacity room assignments persist',
    'the block total is enforced',
    'Two spellings of the same rule, differing on one status',
    'paid , partial , pending',
    'paid, partial, pending',
    'yearLevel does not follow the student',
    'nothing updates it on promotion',
    'show every returning student as first year',
    'the only test anywhere is periodStatus = open',
    'is never read',
    'cannot be moved to paid at all',
    'the deadlock of E-1',
    'ten pending approval rows',
    'shares success',
    'a row in offices and a matching clearancerequirements row',
    'no OfficeId case names it',
    'an office that exists as data only',
    'office 8 either merged or removed',
] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
