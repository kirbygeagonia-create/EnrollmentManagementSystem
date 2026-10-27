# Confirmation requested: is your program a board program?

**Issued 2026-10-26 · one page per department · answers arrive at the Registrar, not in the software**

This asks the three departments whose programs are flagged as board programs on the school's own instruction
and on nothing written by the school. It changes no code and moves no row: the flag is a box in
Reference Data → Courses, and the answer is the same box ticked or unticked. It is the department half of
**§28.2 C-5**, the register's one remaining engineering-side ask, and it is carried in
`Documentation/Registrar-Window-Packet-2026-10-26.md` §B.

## What the system does with the flag

Measured on live `ems` today: **16 programs; 5 carry "Course-Specific Exam"; 2 of those 5 also carry the general
entrance exam; 0 exempt a transferee from it.**

| Program | Code | Flagged | Also general entrance exam | Where that came from |
|---|---|---|---|---|
| Bachelor of Science in Criminology | BSCrim | yes | yes | `SEAIT_Official_Information.md` §2 — "Board Course — Requires SCAT Entrance & Retention Exams" |
| Bachelor of Science in Social Work | BSSW | yes | yes | §2 — "Board Program" |
| Bachelor of Science in Civil Engineering | BSCE | yes | no | the owner's instruction alone — **this is what we are asking you to confirm** |
| Bachelor of Science in Electrical Engineering | BSEE | yes | no | the owner's instruction alone — **confirm** |
| Bachelor of Science in Agriculture | BSA | yes | no | the owner's instruction alone — **confirm** |

With the flag **on**, an applicant to your program cannot be admitted while the departmental paper is missing or
failed. The gate reads a result for that applicant's own program and term, and the refusal the Admission desk sees
names it: "No Course-Specific Entrance Exam result on record for this applicant, and *BSCE* requires one." —
`app/Policies/AdmissionPolicy.php:142`. A recorded result that is not `pass` blocks with its own word instead
(:145-146). With the flag **off**, an absent departmental result blocks nothing, and your program's paper is not
offered for recording at all (`app/Http/Controllers/Exam/ExamController.php:384`).

So the question is not cosmetic: the flag decides whether your department's examination stands between an
applicant and enrolment.

## What to answer, per department

One line, one initial. If you clear your program, say so and the Registrar unticks the box — the program keeps the
general entrance examination only if it carries that flag separately.

| Department | ☐ Confirmed: our program requires its own departmental entrance examination | ☐ Cleared: it does not | ☐ Confirmed, and the paper we mean is: | Initial / date |
|---|---|---|---|---|
| Civil Engineering (BSCE) | ☐ | ☐ | ☐ | |
| Electrical Engineering (BSEE) | ☐ | ☐ | ☐ | |
| Agriculture (BSA) | ☐ | ☐ | ☐ | |

If a fourth program should be flagged, name it in the margin; the Registrar adds it in the same screen.

## What an answer costs, and how it comes back off

| The answer is | It is carried by | It comes back off by |
|---|---|---|
| "confirmed" | nothing — the flag already stands; this page only records that its owning department said so | the page itself; no data moves |
| "cleared" | one untick in Reference Data → Courses for that program (`app/Http/Controllers/Admin/ReferenceDataController.php:105`, `:130`) | tick the same box again |
| "add a program" | the same screen, one box | the same box |

No migration, no release, no re-seed. Ruled 2026-10-23 and re-affirmed 2026-10-26: **all five flags stand** until
each owning department answers, precisely so that this is a click and not a release.

## Who holds the other halves

Criminology and Social Work are documented as board programs by the school's own information sheet, so their rows
are not being asked here — a department may still correct them. The Registrar's window still owes four print
questions, and `ems:preflight` still answers the install-time one (G-13); both are in the packet.

Re-measure every figure on this page before re-issuing it:

```
php storage/app/scratch/lane73-packet-evidence.php     # the counts and the five program lines
php storage/app/scratch/lane73-packet-claims.php       # weighs the packet's figures against live ems
```
