<?php

/**
 * Pass 36 — the three rulings of 2026-10-06 that came out of the inventory, written into the
 * authority. Two of them overtake sentences this same session wrote (X-4 and §25.1's form row both
 * described the unscoped print reach as settled), which is the point of measuring before asserting.
 */

require __DIR__.'/docx-edit-engine.php';

$src = dirname(__DIR__, 3).'/Documentation/EMS Complete Documentation.docx';
$apply = in_array('--apply', $argv, true);

$textEdits = [
    // §25.1 — the form's audit cell: the reach it had, replaced by the reach it now has.
    ['yes — one issue row per download, documentType enrollmentForm, sequenced in the enrollment’s own series and separate from the certificate’s. Measured live on 2026-10-06: the gate asks print.enrollmentForm and that the record is enrolled, and — exactly as print.certificate already does — it does not ask which office the requester runs, so a head of any office reaches it. The reach is inherited rather than new, and X-4 in §28.2 now names it',
        'yes — one issue row per download, documentType enrollmentForm, sequenced in the enrollment’s own series and separate from the certificate’s. The gate asks print.enrollmentForm, that the record has reached enrolled, and — ruled by the owner later the same day — that the requester runs the Registrar (office 1) or is SysAdmin. Measured before that ruling was applied, office11_head reached this route with a 200; PrintControllerTest now pins the refusal, and the same scope covers the certificate, the class cards and the subject load'],

    // §28.2 X-4 — the last generalist reach is closed, not merely named.
    ['One generalist reach ruling 7 did not take is named here rather than glossed: the four Registrar print rights — certificate, class card, subject load and, since 2026-10-06, the enrollment form — stay on OfficeHead, and no policy asks which office the requester runs, so a head anywhere in the building prints the Registrar’s papers. Measured live the same day (office11_head reached the enrollment-form route with a 200). Whether printing should be office-scoped is one decision covering all four documents, and it is the owner’s, not a per-paper fix.',
        'The one generalist reach ruling 7 left standing closed in the same week, and measurement is what found it: the four Registrar print rights stayed on OfficeHead with no office test, so a head anywhere in the building printed the Registrar’s papers — office11_head reached the enrollment-form route with a 200. Ruled and built 2026-10-06: RegistrarPolicy::mayPrint now asks the right, the enrolled status and the office — office 1 or SysAdmin, with class card and subject load open as well to a teacher whose academic unit owns the program. A head of another office is refused, which PrintControllerTest pins on both sides; on the demo dataset the instructors carry no unitId, so the Registrar is the only desk printing until someone is assigned to a unit.'],

    // §28.2 C-2 — answered, with the exception it puts on item 16.
    ['Nothing forces academicStanding = irregular. The grant writes studentType = shifter and enrollmentType = old and leaves the standing alone — enrollments.academicStanding is nullable with no column default, measured on live ems — so the receiving record arrives with no standing and the Registrar must state one at approval (§12.4). Whether a shift should by itself make a student irregular is still the departments’ answer, not the code’s.',
        'Answered 2026-10-06, and as a rule rather than a judgement: a program change — within the same department or into another — makes the student Irregular, and so does arriving from another school as a transferee. StudentType::arrivesIrregular() is the single definition; the shift grant stamps it, Admission approval stamps it for a transferee, and those two records are never left undecided. Item 16 still governs every other type — for a first-year or a returning student the standing is what the grades support, proposed by the department and stated by the Registrar at approval — so this is an exception written down, not a change of ownership. The demo tool applies the same rule to the records it seats and counts “standing the type contradicts” on every run.'],

    // §28.2 C-10 — answered for this install, still a worker in production.
    ['The queued work is real and it is two listeners: SendEnrollmentNotification and SendWorkflowNotification both implement ShouldQueue and both are registered in EventServiceProvider. QUEUE_CONNECTION is database, and measured on live ems on 2026-10-06 the jobs table holds 56 pending jobs while the only two notification rows carry the seeder’s own timestamp of 2026-08-30 — so nothing a desk does today has reached a bell, and no failure was visible. DEPLOYMENT.md describes a worker under Supervisor; whether one is part of the installed deployment is the owner’s answer, and until it is given, every notification claim in this document and in the acceptance script is a claim about the queue, not about a screen.',
        'Answered 2026-10-06, with two answers for two machines. The demo and UAT install runs QUEUE_CONNECTION=sync: until then 56 jobs sat undelivered while the only two notification rows carried the seeder’s 2026-08-30 timestamp, so every “the next desk is told” claim in this document described a queue nobody was draining. The backlog was cleared and delivery proved inside one request (2 → 5 rows, 0 jobs queued). Production keeps database plus the Supervisor worker DEPLOYMENT.md §5 describes, because a synchronous listener makes the desk’s request wait on the notification, and a failing notification would fail the action with it. What is queued is exactly those two listeners — PDF rendering has never been queued work, so §6’s browser requirement applies either way.'],

    // §1.1 — the same issue, extended.
    ['and the demo’s dean account was repaired: it had been seeded with the officeHead enum, so the role pairing had quietly replaced its Dean grant and left the shift endorsement undemonstrable on every install.',
        'and the demo’s dean account was repaired: it had been seeded with the officeHead enum, so the role pairing had quietly replaced its Dean grant and left the shift endorsement undemonstrable on every install. Three of §28.2’s institutional questions were then answered and built the same day — a program change and an arrival from another school both issue Irregular (C-2), the four print rights were office-scoped after measurement showed a Clinic head printing a Registrar paper (X-4’s last generalist reach), and this install runs its queue synchronously while production keeps the worker (C-10).'],
];

docxApply($src, dirname(__DIR__, 3).'/storage/app/scratch/docx-backup-2026-10-06-pass36.docx', $textEdits, [], $apply);
