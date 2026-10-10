# Per-Office UAT Walkthrough — SEAIT Enrollment Management System

Written under ruling 18 (2026-10-02): *"build these rulings on the demo dataset first, then
write the per-office UAT walkthrough script. Real Registrar data comes after."*

This is an **acceptance script**, not a demo narration. Each office section tells that office
which records to open, what to do with them, what the system must refuse, and what to write
down as proof. A section is passed only when every step **and every refusal** behaved as
stated.

**Companion documents:** `Documentation/Demo-Day-Guide.md` (how to run a demo) and
`Documentation/EMS Complete Documentation.docx` (the authority: rules, screens, and §28's
finding register). Where this script cites a ruling, it is the owner's ruling of 2026-10-02
numbered as in that document.

---

## 1. How to use this script

1. **One row, one check.** Do the step exactly as written, then tick `OK` or write what
   actually happened in `Observed`. A step you could not perform is a finding, not a skip.
2. **Refusals are mandatory.** Every office has a *Refusal checks* block. A gate that cannot
   say no is not accepted — most of the defects this build closed were exactly that: a rule
   that passed because nothing was there to check.
3. **Name the record.** Write the enrollment id / school id / document number you saw, not
   "it worked". Those numbers are how a later session can tell whether the system was right.
4. **Do not repair the data mid-test.** If a step needs a record the demo no longer has, stop
   that office's section, re-run the setup in §2, and start that section again.
5. **Sign off per office.** One line at the end of each section: tester, role, date, and
   `PASS` / `PASS WITH FINDINGS` / `FAIL`, with finding numbers listed.

### Pass criterion for the whole system

All eleven sections `PASS` or `PASS WITH FINDINGS` with every finding written down, **and**
the three cross-desk checks in §13 pass. Anything else goes back to the development team with
the observed evidence.

---

## 2. Setup before any office starts (one person, 15 minutes)

| # | Step | What must happen | OK |
|---|---|---|---|
| 2.1 | Laragon → **Start All**, confirm Apache and MySQL are running | `http://localhost:8080` serves the app | ☐ |
| 2.2 | From the project folder: `php tools/seed_full_demo_pipeline.php` | Ends with `DEMO-DAY DATASET FULLY SEEDED!` and prints **no line starting with `!`** | ☐ |
| 2.3 | Read the counter block at the end of that run | `Evaluation — level the record does not support 0`, `Clearance — slips with no enrollment in the period term 0`, `Ledger — enrolled with no fee sheet 0`, `Ledger — past Accounting but still owes 0`, `Ledger — receipts exceeding the assessment 0` | ☐ |
| 2.4 | Two counters are **expected to be non-zero** (they are demo states, not defects) | `Evaluation — returning records not yet confirmed 3`, `Print trail — issue rows carrying no key at all 21` | ☐ |
| 2.5 | `php artisan ems:print-fidelity` | `Done: 8/8 rendered in storage/app/prints/fidelity` | ☐ |
| 2.6 | `php artisan test tests/Feature/E2E` | 4 passed. These run against the live `ems` database, so they prove the dataset supports a full walkthrough end to end | ☐ |
| 2.7 | Back the database up once: `mysqldump -u root ems > C:\Users\ADMIN\ems-backups\ems-pre-UAT-<date>.sql` | File is > 1 MB | ☐ |
| 2.8 | Create the two **head-only** test accounts — **Admin → User Management**: `head11_only` in office 11 and `head22_only` in office 22, each with the role **OfficeHead and nothing else**, password `password` | Both open their own counter's screens. These are the only accounts that can prove ruling 7 at the Clinic and ID desks, because **the demo dataset has no bare head**: `RbacSeeder` step 3 pairs every `office{N}_head` with that office's desk role, so each head holds their own signature deliberately. Remove or restore them when §17 finishes | ☐ |
| 2.9 | Confirm the queue answer before testing a desk on a notice: `grep QUEUE_CONNECTION .env` | This install runs **`QUEUE_CONNECTION=sync`** (the owner's answer to §28 **C-10**, 2026-10-06), so a notice is written inside the request and the bell shows what a desk just did — a delivery was proved on live `ems` the same day. The 56 jobs that had been sitting undelivered were cleared. **Production is a different answer**: DEPLOYMENT.md's Supervisor worker still applies, so do not copy `sync` onto the school's server | ☐ |
| 2.10 | Sign in as **your own office account**, open the **App Launcher** and the dashboard's **Live Office Queues**, and write down which cards appear | Every desk you may open must have a card, and no card may lead to a refusal. Both halves are now answered by one server rule — `DashboardController::QUEUES` asks each desk's own authorization — because this check was never in the script and the defect it would have caught survived: the Clearance card was gated on **office 8**, the office ruling 12 folded into Registrar, so with no account sitting in office 8 it rendered for nobody except `staff8`. Measured after the fix: the ten non-admin accounts entitled to open Clearance all see the card, and the three `instr.*` accounts and `dean_academic` see only the desks their own policies open | ☐ |

> **Which server answered?** `php artisan serve` also binds `8080` on this machine and
> silently shadows Laragon's Apache on loopback. If a screen 404s a route you know exists,
> check you are talking to Apache and not to a leftover `artisan serve`.

**Reset if an office breaks the data:** re-run 2.2 (the tool reconciles; a second run creates
nothing new) or restore the dump from 2.7.

---

## 3. The accounts, and the one identity used only for refusals

Every account's password is `password`.

| Log in as | Office | Roles held | Used in section |
|---|---|---|---|
| `office6_head` | 6 Admission | AdmissionOfficer + OfficeHead | §5 |
| `office4_head` | 4 Guidance | GuidanceStaff + OfficeHead | §6, and §8.4 (the councillor's grant) |
| `office7_head` | 7 Academic Department | DeptEvaluator + OfficeHead | §7, §8 |
| `dean_academic` | 7 Academic Department | Dean | §8.3 (endorsement) |
| `instr.alvarez` | 7 Academic Department | DeptEvaluator + Instructor | §7 (a plain evaluator) |
| `office3_head` | 3 Scholarship | ScholarshipOfficer + OfficeHead | §9 |
| `office2_head` | 2 Accounting | AccountingStaff + OfficeHead | §10, §11.8 |
| `office1_head` | 1 Registrar | RegistrarApprover + OfficeHead | §11, §12 — and the office-scope refusals §13R.1, §15R.3 |
| `office5_head` | 5 Blocking | BlockingCoordinator + OfficeHead | §13 |
| `office11_head` | 11 Clinic | ClinicStaff + OfficeHead | §14, §17.2 |
| `office22_head` | 22 ID Office | IdOfficer + OfficeHead | §15 |
| `staff8` | 1 Registrar | Admin + SysAdmin | §16, §17 |
| `office8_head` | 1 Registrar | RegistrarApprover + OfficeHead | §7R.1, §10R.4 — **not** a negative test for the Registrar's own signature |
| `head11_only` *(you create it, §2.8)* | 11 Clinic | **OfficeHead only** | §12R.1, §14R.2, §14R.3, §17.1 |
| `head22_only` *(you create it, §2.8)* | 22 ID Office | **OfficeHead only** | §15R.4 |

**Read this before using any account as a refusal.** No account on the demo dataset is a bare
`OfficeHead`: `RbacSeeder` step 3 pairs every office head with that office's desk role, so
`office11_head` holds `clinic.sign` and `office1_head` holds `enrollment.approve` *by design* —
that pairing is how ruling 7 kept each desk's own signature reachable. `office8_head` is the
retired Clearance office's login; ruling 12 moved it under the Registrar, and the pairing then
made it a Registrar approver, so it can certify an enrollment and proves nothing about ruling 7 at
the Registrar desk. The two accounts §2.8 creates are therefore the only identities that refuse
because a **signature is missing** rather than because an office is wrong, which is why §12R.1,
§14R.2, §15R.4 and §17.1 use them. `office8_head` still refuses `evaluation.sign` and
`payment.refund`, and refuses them for the right reason — no role it holds grants either.

`dean_academic` was `DeptEvaluator + OfficeHead` on the live install until 2026-10-06: its
`staffusers.role` said `officeHead`, and the pairing above overwrote the Dean grant the seeder had
just given it. That made §8.3 impossible to demonstrate — `shift.sign.department` belongs to Dean
and ProgramHead. It now reads `dean`, and holds **Dean + DeptEvaluator**. If §8.3 refuses with
"not permitted", check that account's roles first.

---

## 4. The dataset each office will meet

All active records sit **term 11 — AY 2025-2026, 2nd semester** (ruling 1). The academic year
2026-2027 (terms 19/20/21) exists and term 19 is the term that covers today, so the shared
term chip and `activeTerms` resolve.

| Student | School ID | Enrollment | Where it sits | What it is for |
|---|---|---|---|---|
| Juan Dela Cruz | DEMO-2026-001 | 53 | `enrolled`, clinic + ID validated | the completed first-year path |
| Maria Reyes | DEMO-2026-002 | 596 `pending` + 595 `enrolled` (term 10) | clearance slip 27 approved and receipted, pass slip **confirmed** | the approvable returning record |
| Pedro Santos | DEMO-2026-003 | — | admission 41 `pending` for **BSCrim**, 1 requirement submitted / 2 pending | the Admission desk's blocked approval — first the documents (5.2), then both examinations (5R.4) |
| Liza Bautista | DEMO-2026-004 | 262 `enrolled` **irregular**, 55 (term 10) + 654 (term 2) `enrolled` | one failed subject on the prior load | standing derived from grades, not typed |
| Ana Gonzales | DEMO-2026-005 | — | admission 45 `approved` | approved application, enrollment issued |
| Carlo Mendoza | DEMO-2026-006 | — | admission 44 `rejected`, transferee | the rejection path |
| Rico Navarro | DEMO-2026-007 | 598 `pending` + 597 `enrolled` (term 10) | clearance slip 42 **pending, no receipt** | the Registrar's block, live |
| Elena Ramos | DEMO-2026-008 | 84 `pending` | first-year, no history | the derived level must read 1 |
| Rafael Cruz | DEMO-2026-009 | 85 `pending` year 2 **irregular**, 655 `enrolled` (term 2, year 1) | transferee | a placement the desk gave, not the record — and standing the type decides (C-2) |
| Grace Tan | DEMO-2026-010 | 86 `paid` | cash + bank check | Registrar approval queue |
| Christian Lim | DEMO-2026-012 | 87 `assessed`, owes ₱17,500 | nothing held | full payment |
| Bea Alonzo | DEMO-2026-013 | 88 `assessed`, assessed ₱18,500 / owes ₱10,500 | **₱8,000 already held** | part payment and refund |
| Patricia Diaz | DEMO-2026-014 | 89 `enrolled`, no block seat | | Blocking's queue |
| Gabriel Ramos | DEMO-2026-015 | 90 `enrolled`, no block seat | | Blocking's queue |
| Danica Sotto | DEMO-2026-016 | 91 `enrolled`, clinic + ID validated | | the second completed path |
| Kevin Santos | DEMO-2026-017 | 92 `enrolled`, ID request open, **no clinic record** | | the clinic-before-ID order |
| Marco Villanueva | DEMO-2026-018 | 264 `paid` **confirmed**, 263 (term 10) + 656 (term 2) `enrolled` | slip 119 approved | the returning record awaiting approval |
| Isagani Torres | DEMO-2026-021 | 287 **`returnedToEvaluation`** | a Registrar return with a reason | the return path |
| Angelica Reyes | DEMO-2026-019 | 285 `evaluated`, no sheet | | Assessment compute |
| Camille Custodio | DEMO-2026-022 | 318 `evaluated` **with a sheet already** | owed ₱17,500 | the sheet-outrunning-the-load state |
| Marisol Domingo | DEMO-2026-020 | 286 `assessed`, **owes ₱0** (grant 1, Full Scholarship 100%) | nothing held | settled without a receipt |
| Iñigo Barrameda | DEMO-2026-024 | 475 `pending` year 2, 657 `enrolled` | BSCrim continuing | retention examination |
| Relinda Sabla | DEMO-2026-025 | 476 `pending` year 2, 658 `enrolled` | BSCrim continuing | retention examination, **not yet examined** |

**Sections already open:** block 53 `BSCrim 1-A` year 1 (2 students / 40, 6 class meetings),
block 75 `BSIT 1-A` year 1 (1/40, 6), block 114 `BSBA 2-A` year 2 (0/40, 6).
**Clearance window:** the seeded period sits term 11 and is `open`.
**Fee types:** id 5 `Clearance Slip Replacement` ₱100.00 — the slip footer and the charged fee
must both read that row.
**Shift docket:** `shiftingrequests` holds **0 rows**; §7 files the first one.
**Two applicant gaps, deliberate:** the demo set has **no applicant to a board-only program** (BSA,
BSCE, BSEE) and **no pending transferee** — Carlo Mendoza's transferee application (44) is `rejected`,
which is the rejection path it is there for. Measured 2026-10-09 across all four admissions: 27 and 41
first-year BSCrim, 45 first-year BSIT, 44 rejected transferee. So rows **5.8** and **7.17 create the
applicant they test**; an empty queue on either is the dataset, not a defect. Registering them during
the walk also exercises intake, which is the point.

---

## 5. Admission Office (office 6) — account `office6_head`

Path: **Desks & Apps → Admission** (`/admission`).

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 5.1 | Open the queue | Show admission 41 (Pedro Santos) as `pending` with 3 requirement lines and their submission states | | ☐ |
| 5.2 | Open Pedro's record and try **Approve** | **Refuse**, naming the requirement that is not yet verified — not a generic "cannot approve" | | ☐ |
| 5.3 | Upload a PDF to each pending requirement line (Submit), then **Verify** each one | Only a `pdf/jpg/jpeg/png/doc/docx` file is accepted; after the last verification the *document* blocker clears — but Pedro's program is BSCrim, a board program, so the two examination blockers remain and 5R.4 shows them | | ☐ |
| 5.4 | Approve, having recorded the results the desk does not own: the **general** pass in §6 (Guidance) and the **course-specific** pass in §7 (the department) | `admissionStatus = approved`, **one** enrollment created for student+term, `evaluatedBy` = you, and the record appears in Department Evaluation's queue | | ☐ |
| 5.5 | Open the created enrollment | `yearLevel` is the derived level (1 for a student with no history), `curriculumId` is pinned, and `academicStanding` is **empty** — the department decides standing, not this desk (ruling of the audit: approval must not pre-decide it) | | ☐ |
| 5.6 | Approve the same admission again | No second enrollment: the response names the enrollment already holding the seat (ruling 3) | | ☐ |
| 5.7 | Reject Carlo Mendoza's application if it is not already rejected | `rejected`, no enrollment created | | ☐ |
| 5.8 | File a **transferee** application (**Admission → New Application**) for a program that carries the course-specific flag, then ask the Reference Data holder (§16.8) to tick *Exempt transferees from the General Entrance Exam* on that program and re-read the blockers | Before the tick: both examination blockers. After it: the **General** blocker is gone and the **Course-Specific** one remains. C-4, ruled 2026-10-07 — a transferee arriving with credit may be waived the school-wide screening, never the department's own test. The flag is per-program and **default off**, so no application approvable before this change becomes approvable now | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 5R.1 | Try to record a **general entrance** result from this account | Not offered and refused — exam recording belongs to Guidance and the department | | ☐ |
| 5R.2 | After a course-specific exam **pass** is recorded for an applicant, look at the admission | Still `pending`. An examination result must never decide the application (that was finding G-8) | | ☐ |
| 5R.3 | Attach a `.txt` file to a requirement | Rejected with the allowed-type message | | ☐ |
| 5R.4 | With Pedro's three documents now verified and **no** examination result on record, press **Approve** | Refused **twice more, each by name**: *"No General Entrance Exam result on record for this applicant to BSCrim for this term."* and *"No Course-Specific Entrance Exam result on record for this applicant, and BSCrim requires one."* Both come from the **program's** flags, never from this desk (C-5, ruled 2026-10-07). Before that ruling the second blocker could never fire on the install — no program carried the flag, so a departmental test could be demanded on paper and skipped in practice. Each blocker is read against **this admission's own program and term**: a pass Pedro earned under a different program does not answer here (ruled 2026-10-08, and see §18 item 7) | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 6. Guidance Office (office 4) — account `office4_head`

Path: **Exams** (`/exam`), results list `/exam/results`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 6.1 | Filter the student list for an examining program and the demo term (`/exam/students?courseId=…&termId=11&stage=entrance`) | Candidates are the applicants for that course/term; the list is not empty on a term the curriculum covers | | ☐ |
| 6.2 | Record a **School Entrance Examination** pass for a pending first-year applicant | `examStage = entrance`, `examType = general`, result stored against the applicant's own term | | ☐ |
| 6.3 | Record a **fail** on another candidate | One `examresults` row is written and **nothing else** — the application stays `pending`, because a result must never decide an admission (finding G-8, closed 2026-10-01). The Admission desk then refuses that applicant naming *"The General Entrance Exam result on file is fail, not pass."*, and the department cannot score them: the stage-4 prerequisite refuses a non-pass general row. Do **not** expect the row to flip to `rejected` — an earlier draft of this script said it does, and the code stopped doing that | | ☐ |
| 6.4 | Open the pass/fail list | Both readings appear, with the stage named | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 6R.1 | Try to record a **course-specific** or **retention** result from Guidance | Refused — those belong to the owning department | | ☐ |
| 6R.2 | Try to approve an admission from this account | Refused (no `admission.approve`) | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 7. Academic Department (office 7) — Department Evaluation, accounts `office7_head` and `instr.alvarez`

Path: **Evaluation** (`/evaluation`), one record at `/evaluation/{enrollment}`.

This is the busiest section. Take the records in this order.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 7.1 | Open `/evaluation` as `instr.alvarez` | The queue lists the `pending` and `returnedToEvaluation` loads: enrollments 596, 598, 84, 85, 475, 476 and 287 | | ☐ |
| 7.2 | Open **Isagani Torres (287)** — the record the Registrar returned | The return reason is visible on the record, and the desk can propose again | | ☐ |
| 7.3 | Open **Elena Ramos (84)**, the student with no history | The subject picker offers only the subjects the **pinned curriculum** offers for that program, year level and term semester — never an empty list on the demo term | | ☐ |
| 7.4 | Propose a load that omits a mandatory subject and sign nothing | **Refused**, naming the missing subject ids — a regular student must propose the year's mandatory block | | ☐ |
| 7.5 | Propose the full offered load | Accepted; status becomes `evaluated` | | ☐ |
| 7.6 | Open **Iñigo Barrameda (475)** and **Relinda Sabla (476)**, both BSCrim year 2 | Try to **Sign** before a retention result exists → refused. The refusal must name the examination, not a generic blocker | | ☐ |
| 7.7 | Record the retention pass for Iñigo (`POST /evaluation/{enrollment}/retention`) | Sign now succeeds; Relinda stays unsigned and continues to demonstrate the block | | ☐ |
| 7.8 | Open **Rafael Cruz (85)**, the transferee | The picker includes the second-year load; his prior-year record (655) is what earned the level. Where a curriculum subject waits behind a prerequisite, it is only offered because the credited or passed subject is on record (ruling 10). **His standing already reads `irregular`** — C-2 was ruled 2026-10-06: a student arriving from another school is irregular because of what the type means, not because a desk read grades | | ☐ |
| 7.9 | Process a credit transfer with a **passing** grade, then one with a **failing** grade | Only the passing line is credited; the failing line stays on the record and does **not** unlock the subject behind it | | ☐ |
| 7.10 | Fill the demographic profile where it is incomplete, then try to sign | The screen shows the checklist of gaps, and signing refuses on the same list — one rule, two readers | | ☐ |
| 7.11 | Decide standing on **Liza Bautista (262)** | Standing is derived from her grades (she failed a subject → `irregular`), and a placement that differs from the derivation is called out in the confirmation message rather than silently accepted | | ☐ |
| 7.12 | Confirm Maria Reyes' pass slip (`POST /evaluation/596/clearance/confirm`) | The confirmation writes `clearanceConfirmedBy`/`At` on the enrollment; the Registrar can now read the clearance as passed (ruling 5) | | ☐ |
| 7.13 | Issue a returning student's enrollment: **Evaluation → Issue**, pick a student with an earlier record, note the prefilled numbers | The form opens on the level **the record implies** (completed years, computed by the server) and on the student's last program; `yearLevel` is editable. Issuing builds the workflow form — **6 boxes** for continuing and shifter types | | ☐ |
| 7.14 | Issue again for the same student and the same term | **Refused**, naming the enrollment that already holds the seat (ruling 3) | | ☐ |
| 7.15 | Try to issue with a **different program** from the student's last enrollment | **Refused**, and the refusal points at the shift flow — a program change may not pass as an ordinary continuation | | ☐ |
| 7.16 | Record a **course-specific entrance** pass for Pedro (**Exams → Record Course-Specific Exam**, as `office7_head`) — the second blocker §5R.4 names | The candidate list is the School Entrance **passers** transferred by Guidance (BR9): BSCrim runs the general paper, so an applicant with no general result is not offered. The row stores `examType = courseSpecific` against the applicant's own term, the Admission desk's second blocker clears, and the application itself is untouched — a result never decides an admission (5R.2) | | ☐ |
| 7.17 | **Have the Admission desk (`office6_head`) register an applicant first** — Admission → Register Applicant, program **BSCE**, term 11, type *First Year* (the department cannot: `admission.create` belongs to AdmissionOfficer and Dean) — then open **Exams → Record Course-Specific Exam** as `office7_head` and pick BSCE | The roster lists **the applicants of a program that runs no general paper**, so the new name is there even though BSCE has no School Entrance passer to transfer. Record a departmental pass and the Admission desk's blocker clears on that result alone. These three programs carry `requiresCourseSpecificExam` with `requiresEntranceExam` off: there is no Stage 1 to transfer and none is demanded. Before 2026-10-07 this roster was built from passers who cannot exist, which made every applicant to a board-only program permanently unapprovable. **An empty list before anyone registers is correct, not the bug** — which is why this row creates its own applicant (§4) | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 7R.1 | Sign an evaluation as `office8_head` (RegistrarApprover + OfficeHead) | Refused — `evaluation.sign` left OfficeHead under ruling 7, and no role this account holds grants it. The department's own signer is `office7_head` | | ☐ |
| 7R.2 | Propose a subject the curriculum places behind an unsatisfied prerequisite | Refused with `X requires Y` naming both codes | | ☐ |
| 7R.3 | Confirm a pass slip for a student with no approved slip in the accepting window | Refused, and the message says which of the two is missing | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 8. Academic Department (office 7) — the shift docket, accounts `office7_head`, `dean_academic`, `office4_head`

Path: **Evaluation → Shift Requests** (`/evaluation/shift-requests`). The table is empty at
the start of this section; the walkthrough fills it.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 8.1 | File a shift for a currently enrolled student: current program, target program, and the student's own will statement | A `pending` paper is created, signed by the department staff member who filed it | | ☐ |
| 8.2 | Try to **decide** the paper as the same person who filed it, before endorsement | Refused — a decision requires the `endorsed` state | | ☐ |
| 8.3 | Endorse as `dean_academic` | Status `endorsed`, the dean's signature and date recorded. If this refuses, check the account before the rule: `dean_academic` held only `DeptEvaluator + OfficeHead` until 2026-10-06, because it was seeded with the `officeHead` enum and `RbacSeeder` step 3 re-derives roles from that column — it now reads `dean`, so the account holds **Dean + DeptEvaluator** and `shift.sign.department` is reachable | | ☐ |
| 8.4 | **Decide (grant)** as a Guidance councillor (`office4_head`) | Status `granted`; the receiving enrollment is created with `studentType = shifter`, **`academicStanding = irregular`** (C-2, ruled 2026-10-06 — a program change is irregular by what it means, so the record no longer arrives undecided), its own block seat, the curriculum pinned, and **no examination requirement** attached to it (ruling 11's proof is the form plus credit evaluation) | | ☐ |
| 8.5 | Open both enrollment ids named on the granted paper | If the record being left sat in the **same term**, it is now `dropped` with the reason naming the shift request, and its proposed/confirmed subject rows are retired — the seat is not held twice. An earlier term is left alone as history | | ☐ |
| 8.6 | File a second paper for a student who already holds another active enrollment in the target term and try to grant it | **Refused**, pointing at the Registrar's drop action — the shift paper is not a way around ruling 17 | | ☐ |
| 8.7 | Decide a different paper as **rejected** | Status `rejected`; no enrollment is created, and nothing on the student's current record changes | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 9. Scholarship Office (office 3) — account `office3_head`

Path: **Assessment** (`/assessment`), a sheet at `/assessment/{assessment}`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 9.1 | Compute charges for **Angelica Reyes (285)**, still `evaluated` | A fee sheet is created; the enrollment does **not** move state — `compute()` deliberately leaves it `evaluated` | | ☐ |
| 9.2 | Look at **Camille Custodio (318)** in this queue | The list shows a sheet on a load the department has not yet decided, which is what the pipeline's counter `Assessment — sheets on a load not yet decided 1` names | | ☐ |
| 9.3 | Adjust charges on a sheet | The adjustment recomputes the balance from the current charges; nothing is re-stamped `paid` | | ☐ |
| 9.4 | Finalize a sheet whose enrollment is `evaluated` | Status becomes `assessed` and the Accounting desk sees the account | | ☐ |
| 9.5 | Apply the **Full Scholarship (100%)** to a sheet that owes money (`POST /assessment/{assessment}/scholarships`) | Coverage is applied and the balance drops; Marisol Domingo (286) is this case already settled at ₱0 | | ☐ |
| 9.6 | **Withdraw (revoke)** Marisol's grant with a reason (`POST /assessment/scholarships/1/withdraw`) | The grant is **not deleted**: status `revoked`, the reason, who and when are on the row; coverage is recomputed from the grants still standing at **their own percentage of the assessed total**, so the account owes again — even if the record had already been approved | | ☐ |
| 9.7 | Re-apply a grant, then **expire** it at the end of the award | Status `expired` with the same trail; the balance moves again | | ☐ |
| 9.8 | Check the arithmetic on a stacked award (two grants whose percentages exceed 100) | Coverage is capped by the assessed total, and the figure does not depend on the order the grants were keyed | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 9R.1 | Compute charges for an enrollment whose department signature is missing | Refused — compute needs the evaluation signature | | ☐ |
| 9R.2 | Withdraw a grant without a reason, or with a 5-character one | Refused: the reason is required, 10–500 characters | | ☐ |
| 9R.3 | Withdraw a grant as `office2_head` (Accounting) | Refused — `assessment.scholarships.withdraw` belongs to this office | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 10. Accounting Office (office 2) — account `office2_head`

Path: **Accounting** (`/accounting/...`); the daily report is its own screen.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 10.1 | Record the **full** payment for **Christian Lim (87)**, who owes ₱17,500 | A receipt with an OR number; the enrollment moves to `paid` | | ☐ |
| 10.2 | Record a **part payment** on **Bea Alonzo (88)** — ₱2,000 of the ₱10,500 owed | The receipt is `partial`, the account still owes, and the record does **not** reach `paid`. `partial` used to be a value no desk could produce | | ☐ |
| 10.3 | Pay the remainder | Now `paid`; the account is closed by two receipts, and the balance figure is the same one the Registrar reads | | ☐ |
| 10.4 | **Refund** one of Bea's receipts (`POST /accounting/payments/{payment}/refund`, with a reason) | The receipt **keeps its OR number and its payment date**; the refund is a new fact on the row (who, when, why). If the account had been `paid` or `enrolled`, it moves back to `assessed` — the Registrar can no longer approve it (ruling 14) | | ☐ |
| 10.5 | Try to **void** that refund | **Refused.** A refund may be refunded again but never voided, because voiding means "never filed" and would erase the payout while leaving the reopened balance | | ☐ |
| 10.6 | Settle **Marisol Domingo (286)** who owes ₱0 (`POST /accounting/286/settle` after step 9.6 re-credited her, or on any zero-balance account) | Settled to `paid` **without inventing a receipt**. The fee sheet states that no receipt was owed, and names the hand and the hour from the Accounting workflow step (ruling 9) | | ☐ |
| 10.7 | Open the **daily report** on a date with activity | Receipts and refunds appear separately: cash in, cash handed back, and the net. A refund must not read as revenue | | ☐ |
| 10.8 | Print the fee sheet / subject load for a settled-without-receipt account | The printed page carries the same settlement note the screen shows | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 10R.1 | Record a payment of ₱0 | Refused — a receipt must be positive; the zero-balance path is the explicit settle action | | ☐ |
| 10R.2 | Record a payment on a load that is still `evaluated` | Refused — the account must be `assessed` first | | ☐ |
| 10R.3 | Record a payment as `office1_head` (the Registrar's head) | Refused by the office scope — `payment.record` sits with Accounting; a Registrar bypass was one of the defects ruling 7 closed | | ☐ |
| 10R.4 | Refund a receipt as `office8_head` | Refused — `payment.refund` is Accounting's | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 11. Clearance — the Registrar's window and the other offices' lines, accounts `office1_head` and each owning office

Path: **Clearance** (`/clearance`), windows at `/clearance/periods`, slips at
`/clearance/slip/generate`, prints at `/clearance/{clearance}/print` and `/print/pdf`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 11.1 | Open `/clearance/periods` | One window sits the demo term (11) and reads `open`. There is no second accepting window — two open windows make the Registrar read a term that has no students | | ☐ |
| 11.2 | Generate a slip for a student who has no enrollment in the window's term | The desk seats the student first (the tool does this) — a slip with no enrollment behind it is the defect that was closed; the check is that **no such slip exists today** | | ☐ |
| 11.3 | Print the generated slip | The footer states the replacement fee **from Reference Data** (fee type 5, ₱100.00) and the document carries its own issue number | | ☐ |
| 11.4 | Have each owning office sign **only its own** requirement line (there are 9 lines, one per office that participates) | A signature from an office that does not own the line is refused; every line carries a requirement name — no blank "Clearance" line | | ☐ |
| 11.5 | Record the desk receipt on a fully approved slip | `overallStatus = approved` with `receivedBy` — the receipt is the act that makes the clearance approved | | ☐ |
| 11.6 | **Close** the window while any clearance is still pending | **Refused with the count named** (ruling 16). Rico Navarro's slip 42 is the pending one that blocks this | | ☐ |
| 11.7 | **Extend** the window (`PATCH /clearance/periods/{period}/extend`) | Status `extended`, and the extension is visible to every reader: slips are still accepted, and the Registrar still treats clearance season as live | | ☐ |
| 11.8 | **Replace a lost slip** (`POST /clearance/slip/replace`) as `office2_head`, then as `office1_head` | Both are accepted — Accounting and the Registrar perform this act; the replacement fee is filed as the OR, and the reissued slip gets a fresh number that continues **past** the unattributable pile | | ☐ |
| 11.9 | Print the replacement slip | Number is unique against every other printed slip of that type, including the 21 legacy rows that carry no key | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 11R.1 | Try to reach `extended` through the ordinary status radio on the period form | Refused — `extended` is reachable **only** through the extend action | | ☐ |
| 11R.2 | Sign another office's requirement line | Refused by office scope | | ☐ |
| 11R.3 | Delete the `Clearance Slip Replacement` fee type as `staff8` and try to replace a slip | The replace action **refuses and names the fee row**; the printed slip shows no amount until the row exists | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 12. Registrar Office (office 1) — account `office1_head`

Path: **Registrar** (`/registrar`), a record at `/registrar/{enrollment}`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 12.1 | Open the queue | It shows `paid` records awaiting approval — Grace Tan (86) and Marco Villanueva (264) — each with the **seven-gate checklist** on the record page | | ☐ |
| 12.2 | Try to approve a **continuing** record whose pass slip is not confirmed | Refused. The reason is **on the record page** under the red gate (the refusal is a 403, not a session flash) — do not expect a banner | | ☐ |
| 12.3 | Confirm Maria Reyes' slip at Evaluation (7.12), then approve her load | Approved; `enrolled`, and `enrollmentType` reads `old` for a returning student | | ☐ |
| 12.4 | State the standing at approval | Approval is refused until the Registrar states the standing; the recorded standing is what every later document prints | | ☐ |
| 12.5 | **Return** a record to Evaluation with a reason (Isagani Torres 287 is the standing example) | Status `returnedToEvaluation` and the reason reaches the Evaluation desk; the inbox notice is addressed to the desk that must act next and appears on its bell during the request (§2.9 — this install runs the queue synchronously) | | ☐ |
| 12.6 | **Drop** an `assessed`/`paid`/`enrolled` record with a required reason (`POST /registrar/{enrollment}/drop`) | Status `dropped` with `dropReason`; the reason also lands in the status history with who and when; proposed and confirmed subject rows are retired, which **releases the block seat**; `dropped` is terminal | | ☐ |
| 12.7 | Re-enroll that student in the **same term** at Evaluation | Allowed — a dropped record does not hold the seat (ruling 17) | | ☐ |
| 12.8 | Print **COR / certificate, subject load, class card** for an enrolled record, as screen and as PDF | Each renders from real rows, each writes its own issue row keyed to the record it covers, and the printed year level uses the one shared formatter (`1st Year (Freshman)`), never `1 Year` | | ☐ |
| 12.9 | Print the **enrollment form itself** (`GET /registrar/{enrollment}/print/enrollment-form/pdf`, `print.enrollmentForm`) | A PDF downloads and `documentprintlog` gains an `enrollmentForm` row numbered in its **own** sequence — printing the form does not advance the certificate's. There is deliberately **no screen** for this paper: the blade is the form, and a second layout could only drift from it. Ask the Registrar whether the paper is acceptable **as the record it is**: it is the only document that prints student type and standing | | ☐ |
| 12.10 | Compare a printed PDF against **the physical form held at the Registrar's desk** | Layout is acceptable to the Registrar — this is the one step in this script that needs the Registrar's eye, not the tester's. The photographs of these forms are deliberately **not** in the repository: they carry real student numbers and staff signatures, so the comparison is against the paper. `php artisan ems:print-fidelity` prints the same guidance. | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 12R.1 | Approve an enrollment as `head11_only` (§2.8 — OfficeHead, no desk role) | Refused — `enrollment.approve` left OfficeHead (ruling 7) and sits with RegistrarApprover. **Do not use `office8_head` for this one**: since ruling 12 it is a Registrar office head, and the seeder pairs it with RegistrarApprover, so it can approve | | ☐ |
| 12R.2 | Approve while **no clearance window is accepting** | Refused for continuing and shifter types. "No open period" is a **block**, never a silent pass (ruling 4) | | ☐ |
| 12R.3 | Approve a record whose confirmed load sits behind an unsatisfied prerequisite | Refused by gate 7, which reads the record rather than the Evaluation screen's lock | | ☐ |
| 12R.4 | Drop a record that is still `pending` or `evaluated` | Refused — drop is only for `assessed`, `paid`, `enrolled` | | ☐ |
| 12R.5 | Drop without a reason | Refused — the reason is required, and it is what makes the status history readable | | ☐ |
| 12R.6 | Request the enrollment form PDF for a record that has not reached `enrolled` | Refused. The right is `print.enrollmentForm`; the record must also be enrolled, and the gate is named away from the permission so the status test actually runs | | ☐ |
| 12R.7 | Request the enrollment form PDF as the **plain staff account** from §16.5 (no desk role) | Refused — the paper is reached by `print.enrollmentForm`, which sits with `RegistrarApprover`, `OfficeHead` and SysAdmin, exactly where `print.certificate` sits | | ☐ |
| 12R.8 | Request the **certificate and the enrollment form** as `office11_head` — a head, but of the Clinic | **Refused since 2026-10-06 (#35).** `office11_head` holds `print.certificate` and `print.enrollmentForm`, and is still turned away: the right is necessary, not sufficient, and the four Registrar papers now also ask whether the requester runs office 1 — or, for a class card and a subject load, whether the requester's academic unit owns the program. Before this ruling the same request returned 200, measured on live `ems` the morning it was closed | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 13. Blocking Office (office 5) — account `office5_head`

Path: **Blocking** (`/blocking`), a block at `/blocking/{block}`, print at
`/blocking/{block}/print` and `/print/pdf`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 13.1 | Create a block for a (program, term, year level) | The block appears with its own `yearLevel` and capacity | | ☐ |
| 13.2 | Add class schedules to the block: subject, room, teacher, day and time | Two lessons cannot occupy the **same room or the same teacher at the same time**; the demo timetable spreads meetings by subject position in the block, so a fresh block starts clean | | ☐ |
| 13.3 | Assign **Patricia Diaz (89)** and **Gabriel Ramos (90)** to a block | The seat counter reads **distinct students, not subject rows**: a block holding 2 students × 3 subjects reads `2/40`, not `6/40` | | ☐ |
| 13.4 | Assign more students than `maxStudents` allows | Refused with the capacity stated | | ☐ |
| 13.5 | **Unassign** a student | The seat is released and the roster recomputes | | ☐ |
| 13.6 | Print the block schedule (screen and PDF) | Lists every class meeting with room, teacher and time; the issue row is keyed to the **block**, not to one representative student | | ☐ |
| 13.7 | Finalize the block | Downstream desks read the finalized section; the roster is the record the ID and clinic desks see | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 13R.1 | Assign students as `office1_head` | Refused — `blocking.assignStudents` is office-5 scoped (SysAdmin and Admin pass deliberately) | | ☐ |
| 13R.2 | Add a schedule that collides with an existing one for the same room or teacher | Refused, naming the collision | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 14. Clinic (office 11) — account `office11_head`

Path: **Clinic** (`/clinic`), a record at `/clinic/{enrollment}`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 14.1 | Record a health assessment for an enrolled student who has none (Kevin Santos, 92) | A clinic record is created and the workflow box for office 11 closes | | ☐ |
| 14.2 | Amend a record (`PATCH /clinic/records/{clinic}`) | The edit is attributed; nothing re-opens the workflow | | ☐ |
| 14.3 | **Reopen** a completed record (`POST /clinic/records/{clinic}/reopen`) | Allowed, and the record carries `reopened`. This value is *reachable and tested* — it was challenged as dead code and the owner ruled to keep the feature | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 14R.1 | Attempt any clinic action as `office2_head` | Refused by office scope | | ☐ |
| 14R.2 | Log in as `head11_only` (§2.8: office 11, `OfficeHead` and nothing else) and record a health assessment | **Refused.** Recording the assessment *is* the signature on the Clinic box, so the act asks `clinic.sign` as well as the counter's `clinic.record` (ruling 7, wired into the code 2026-10-05). No `clinicrecords` row is written and the office 11 box stays `Pending` | | ☐ |
| 14R.3 | With the same head-only account, **amend** an existing record and **reopen** it | Both allowed: correcting and reopening are counter work, not signatures, and still ask only `clinic.update` and `clinic.reopen` | | ☐ |

> `head11_only` and `head22_only` are test identities, not desk accounts. Delete them when §17
> finishes, or leave them and note it — either way do not renumber or re-pair them, because the
> refusals above depend on them holding `OfficeHead` alone.

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 15. ID Office (office 22) — account `office22_head`

Path: **ID** (`/id`), a request at `/id/{enrollment}`, photo at
`/id/requests/{idRequest}/photo`, `.../remark`, `.../validate`.

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 15.1 | Create a card request for an enrolled student (`newStudent`) | A `pending` request is opened | | ☐ |
| 15.2 | Attach the face photo, then **Validate** | Validation is **terminal**: status `validated`, the validator and date recorded. There is no release step and no `released` value any more | | ☐ |
| 15.3 | Open a request whose data looks wrong and record a **mismatch remark** (`POST /id/requests/{idRequest}/remark`, `id.remarkRequest`) | The remark is stored on the **pending** request; a validated request cannot take one | | ☐ |
| 15.4 | Print the class card / ID-facing documents | Each subject of an expanded card prints, and the issue row is attributable to the student | | ☐ |

**Refusal checks**

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 15R.1 | Validate a request with no face photo on file | Refused — the photo is a validation prerequisite | | ☐ |
| 15R.2 | Look for any release/cancel action on the desk | None exists, and `idrequests.status` holds only `pending` and `validated` | | ☐ |
| 15R.3 | Act on an ID request as `office1_head` | Refused by office scope | | ☐ |
| 15R.4 | Log in as `head22_only` (§2.8: office 22, `OfficeHead` and nothing else), attach a photo and press **Validate** on a pending request | **Refused.** Validation is terminal and it *is* the ID box's signature, so the act asks `id.sign` as well as the counter's `id.validate` (ruling 7, wired into the code 2026-10-05). The request stays `pending`, `validatedBy` and `validatedDate` stay empty, and the office 22 box is unsigned. The same account may still create a request and attach its photo — those are counter work | | ☐ |

> `head22_only` is the ID desk's version of §14's `head11_only`; restore or delete both after §17.

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 16. Administration — reference data and users, account `staff8`

| # | Do this | The system must | Observed | OK |
|---|---|---|---|---|
| 16.1 | Open the Reference Data hub | Only the catalogs this user's role actually maintains are listed (a Registrar sees the grade scale, not the fee catalog) | | ☐ |
| 16.2 | Change the **Clearance Slip Replacement** fee amount, then re-print a slip | The printed footer and the charged OR both follow the fee row — one number, no config default anywhere | | ☐ |
| 16.3 | Edit the **grade scale**: remove every passing band | **Refused.** An empty passing set silently reverts standing derivation to an assumed 3.00, so the screen will not allow it | | ☐ |
| 16.4 | Maintain admission and clearance requirement **lines**, each with its own name and owning office | Every generated slip carries those named lines; no unnamed line appears | | ☐ |
| 16.5 | Create a staff account through **User Management**, deliberately **with no desk role**, and confirm there is no public self-registration | `POST /register` does not exist — staff accounts come from this screen or from the first-deploy console command. Keep the new account: §17.3 tests it | | ☐ |
| 16.6 | Confirm the office list | **Nine offices.** There is no office 8 and no `clearancerequirements` line without a name | | ☐ |
| 16.7 | Check the audit trail on any record you touched in this section | The audit log shows the entity, the change and who made it — including a clearance-period extension (who and when, from the widened `auditlogs.entityId`) | | ☐ |
| 16.8 | Open **Reference Data → Courses** and read the four examination columns on every program | Each program shows Required / Not Required for the **General Entrance**, **Course-Specific** and **Retention** examinations, and the **transferee waiver** reads *Exempt* or *Must sit*. Five programs carry the course-specific flag — BSCrim, BSCE, BSEE, BSA, BSSW (C-5, ruled 2026-10-07: the board programs; **PROVISIONAL**, the owning department replaces the set from this same screen). The waiver is off on all sixteen. Tick it on one program and confirm the flag persists on reopen — that tick is what §5.8 then tests from the Admission side | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 17. Cross-desk security pass — three checks, one account each

These are the checks that prove the permission model is the one the ruling describes. Do them
last, after the desks have finished, so a failure is not mistaken for a data problem.

| # | Log in as | Do this | The system must | Observed | OK |
|---|---|---|---|---|---|
| 17.1 | `head11_only` (§2.8 — OfficeHead only, in office 11) | Look for the approve / sign / validate buttons on the Registrar, Evaluation, Admission, Clinic and ID desks, then POST one of them anyway | **Every one is refused.** A head keeps counter work — `clearance.approve` is deliberately still theirs — but holds none of `enrollment.approve`, `admission.approve`, `evaluation.sign`, `clinic.sign`, `id.sign`. This is the check that proves the *signature* itself, from inside a real office, where office scope cannot be the reason (`office8_head` can no longer serve as it: ruling 12 put it in office 1 and the seeder paired it with RegistrarApprover) | | ☐ |
| 17.2 | `office11_head` (Clinic head) | Try to compute an assessment and to approve an enrollment | Refused — the counter stays; the other desk does not | | ☐ |
| 17.3 | the plain staff account you created in §16.5 (no desk role) | Open a Student 360 record from quick-search | It is not offered: `students.view` is a granted permission held by the desk roles that need it, not a default of `Staff` or `Instructor` | | ☐ |

Sign-off: ____________ (role ____________) date ________ result ______ findings ______

---

## 18. Known limits to state in the report, not to test as failures

1. **The clearance gate is window-based, not term-based.** One accepting window answers for
   the whole queue; a slip issued under a different term's window would also pass. Changing
   that is a ruling about which window a term owns.
2. **The enrollment form prints, but the paper itself is thin.** `GET
   /registrar/{enrollment}/print/enrollment-form/pdf` (`print.enrollmentForm`) issues the form from
   the blade and mints its own `enrollmentForm` issue row. It has no screen deliberately: the blade
   is the paper, and a second layout could only drift from it. What it still cannot do is name the
   program, the year level or the term — `enrollment-form.blade.php` prints student type and
   standing and no academic period at all. That is finding **P-2**, still open: report a missing
   field on that paper as P-2, not as a broken print action.
3. **21 print rows carry no key at all** (19 legacy clearanceSlip rows and 2 blockSchedule
   rows). They are kept untouched by ruling 6, and the sequence deliberately steps over the
   numbers they hold rather than reusing them.
4. **`documentprintlog` still has no unique index over (enrollmentId, documentType,
   documentNumber)** for the NULL-keyed rows; MySQL treats NULLs as distinct, so the guarantee
   for new prints is the sequencing rule, not the schema.
5. **Demo data, not real records.** Real Registrar spreadsheets replace §4's table; re-run this
   script against the migrated data before accepting the system for production.
6. **Notifications depend on a worker nobody has confirmed is installed.** The two listeners that   write them (`SendEnrollmentNotification`, `SendWorkflowNotification`) are queued, the queue
   connection is `database`, and live `ems` held **56 undelivered jobs** on 2026-10-06 with only two
   notification rows — both stamped by the seeder. Until §28's **C-10** is answered, read every
   notice check in this script as a check on the queue, not on the desk that acted.
   _Answered the same day: this install now runs `QUEUE_CONNECTION=sync`, the 56 stale jobs were
   cleared, and a notice was proved to reach `notifications` inside the request. Production keeps the
   Supervisor worker DEPLOYMENT.md describes — sync is the answer for the demo machine, not for the
   school's server._
7. **An examination result answers for the program and term printed on it — closed 2026-10-08, and it
   used to be a limit.** `Admissions::examResults()` joins on `studentId` alone, so the approval
   blockers once read a general or departmental pass earned against *another* program as clearing the
   application in front of them. `ApprovalReadinessTest` now pins the scoped reading, and the blockers
   name the program: *"…on record for this applicant to BSCrim for this term."* To see the difference,
   record a general pass for a student under one program and open their application to another — the
   second one still refuses. **Worth measuring before you rely on it:** on the demo install no student
   holds a result for more than one course, so no applicant's outcome changed when the rule tightened.

_(Print scope is no longer a known limit: ruled and built 2026-10-06, and tested at §12R.8.)_
