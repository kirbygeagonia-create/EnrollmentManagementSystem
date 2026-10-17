# Demo Day Guide — SEAIT Enrollment Management System

Everything you need to run the full demo: start the system, log in as any
office, walk the professor through every FDD module, and shut it down cleanly.

> **This is the demo narration. Acceptance is a separate document:**
> `Documentation/Per-Office-UAT-Walkthrough.md` — the per-office script (ruling 18) that
> tells each desk which records to open, what the system must refuse, and what to write down
> as proof before signing off. Run that one before trusting this one's numbers.

**The system is 100% offline-capable** — no internet is needed at any point.
All fonts, scripts, and assets are served from this machine.

> **How navigation works** (so the directions below make sense): there is no
> sidebar. At the top of every page is a navbar — the **SEAIT logo** (left,
> goes to Dashboard), the **"Desks & Apps"** button (opens the module
> launcher), the **search bar** (or press **Ctrl+K** anywhere — it searches
> every student by name / school ID / course), and the **bell** + your
> **avatar** (right). Clicking a module in the launcher takes you to that
> desk, and a tab strip appears under the navbar with that desk's pages.

---

## 1. STARTING the system (5 minutes before the demo)

### Step 1 — Start Laragon
1. Open **Laragon** from the Start menu / desktop.
2. Click **Start All** (▶ button, top-left). Wait until both show green:
   - **Apache** (httpd-2.4.66) — serves the app on **port 8080**
   - **MySQL** (mysql-8.4.3) — the `ems` database

### Step 2 — Open the app
Open a browser (Chrome or Edge) and go to:

```
http://localhost:8080
```

You should see the SEAIT EMS login page.

> **No `npm`, no `artisan serve`, no build commands needed** — Apache serves
> the app from pre-built assets. Do NOT run `npm run dev`; that would spin up
> a second, different instance.

### Step 3 — 60-second sanity sweep (do this every demo morning)
1. Log in as `staff8` / `password` → dashboard loads.
2. Press **Ctrl+K**, type `Dela` → **Juan Dela Cruz (DEMO-2026-001)** appears
   → open him → you're on the Student 360° page. ✅
3. Open **http://localhost:8080/accounting/daily-report?date=2026-09-13** →
   OR **DEMO-OR-0001** (₱12,000) is listed. ✅
   *(Needed because the Daily Collections Report defaults to **today**. That
   receipt is fixed-dated 2026-09-13 and is the one the bookmark finds; Juan's
   second receipt, **DEMO-OR-TODAY-01** (₱5,500), is re-dated to the current day
   by `php tools/seed_full_demo_pipeline.php`, so the report without the `?date=`
   is never empty either. The two sum to his ₱17,500 assessment.)*
4. Log out. **You are ready.**

> **This walkthrough is written against the restored dataset, not a bare re-seed.**
> `php tools/seed_full_demo_pipeline.php` populates every desk and workflow queue
> and is safe to run any time — but on a database it has only ever touched itself,
> it creates no **Pedro Santos** and no **Maria Reyes**, and the Juan Dela Cruz it
> creates has no admission, no entrance-exam result, no clinic assessment and no
> DEMO-OR-0001. Measured: **6 of the 19 records steps 4-14 below need** on a
> self-seeded database, **18 of 19** on this machine's restored `ems`. The single
> gap on the restored box is step 13's Juan — see that step. Re-measure with
> `php storage/app/scratch/lane39-guide-preconditions.php`, and read §5 before
> restoring anything.

### If something looks wrong
| Symptom | Fix |
|---|---|
| "This site can't be reached" | Apache isn't running — Laragon → **Start All** again |
| Login page loads but login fails | MySQL isn't running — check Laragon's Database light |
| Page looks unstyled (no fonts/colors) | Wrong URL — use `http://localhost:8080` exactly, then Ctrl+F5 |
| Daily report shows "No payments" | Add `?date=2026-09-13` to the URL (see Step 3.3). That day only holds a receipt once the dataset is restored; a re-seeded database has just today's DEMO-OR-TODAY-01 |
| Data looks wrong / demo students missing | Restore the backup (see §5) |

---

## 2. ALL ACCOUNTS (logins)

**Every account uses the same password: `password`**

### Main demo account
| Username | Role | What it's for |
|---|---|---|
| `staff8` | System Administrator | **Use this one account to demo everything** — sees all desks, all data. The office accounts below exist to demonstrate RBAC. |

### Office accounts (log in as these to show access scoping)
| Username | Office (live system name) | FDD module they own |
|---|---|---|
| `office1_head` | Registrar | 10.0 Registrar Operations — approve enrollment, print COR / class cards |
| `office2_head` | Accounting | 7.0 Payments & Collections — record payments, daily report |
| `office3_head` | Scholarship | 6.0 Fee Assessments — compute & finalize charges |
| `office4_head` | Guidance | 4.0 Exams — School Entrance Examination (Stage 1, Guidance-only) |
| `office5_head` | Blocking | 9.0 Block Sections & Schedules |
| `office6_head` | Admission | 3.0 Admissions — intake, requirements, approval |
| `office7_head` | Academic Department | 4.0 Exams — Course-Specific Entrance (Stage 2) + 5.0 Retention Exam |
| `office8_head` | Registrar (the retired Clearance office's login) | kept for one purpose: it holds **no** sign right, so it demonstrates the ruling 7 split. It exists only on this machine's dataset — `DevReferenceDataSeeder` seats the nine offices ruling 12 left standing, so a database built from empty has 20 accounts and no `office8_head` (measured 2026-10-16) |
| `office11_head` | Clinic | 11.0 Clinic Assessments |
| `office22_head` | ID Office | 12.0 Student IDs |

> **The box holds 21 staff accounts, not the 11 above.** The other ten are the
> college-bound Academic Department desks `DevReferenceDataSeeder` seats —
> `dept.caf`, `dept.cbg`, `dept.cee`, `dept.cjc`, `dept.cte`, `dept.ctic`, one per
> college (the owner's ruling of 2026-10-13) — plus `dean_academic` and
> `instr.alvarez`, `instr.mendoza`, `instr.salazar`. Use a `dept.*` login when the
> professor asks how a department sees only its own college's students;
> `office7_head` and the three `instr.*` accounts carry no college, so they are
> bounded by nothing.

> **RBAC demo tip (FDD 15.0):** stay logged in as `staff8`, open **Desks &
> Apps** and scroll — you'll see all 14 desks including the admin-only
> **Administration & System Controls** section. Now log out, log in as
> `office6_head` (Admission), open **Desks & Apps** again — the launcher
> collapses to their own desk (**Admissions Desk** + **Student 360 Portal**
> only), and the admin section doesn't exist for them. Note: office heads can
> still *view* other offices' queues if they type a URL directly — that's the
> seeded design (they hold broad read permissions, zero manage permissions).
> If the professor tests it, show that every manage/write action — creating
> courses, creating users, voiding payments — returns **403 Forbidden**.
> **That contrast is role-based access control in one click.**

### Student demo data (records you'll click through — not logins)
| Student | School ID | Role in the demo |
|---|---|---|
| **Juan Dela Cruz** | DEMO-2026-001 | The fully-processed first-year: admission → exams → evaluation → assessment → payment → registrar → block → clinic. His **ID box is signed with no ID request behind it** — that request row was deleted under the owner's ruling on 2026-10-11 and the signature was left standing, so step 13 uses Kevin instead |
| **Maria Reyes** | DEMO-2026-002 | Continuing student: **retention exam passed**, clearance slip with **all 9 requirement lines approved** + desk receipt, and her pass slip **confirmed at Department Evaluation** |
| **Pedro Santos** | DEMO-2026-003 | **Pending admission** — 3 requirement submissions, Form 138 PDF attached; the entrance-exam candidate |
| **Liza Bautista** | DEMO-2026-004 | Enrolled BSBA from a prior term — the retention-exam candidate |
| **Kevin Santos** | DEMO-2026-017 | The ID desk's finished example: BSCrim year 1, every box signed, request **validated** with a **face photo on file** — shown on his Student 360 page, because the hub lists only open boxes (step 13). `tools/seed_full_demo_pipeline.php` writes his validation, so a re-seed keeps him and the September dump alone loses him (see §5) |

---

## 3. DEMO SCRIPT — in FDD module order

The FDD has 15 modules (L1). This route walks the professor through them in
workflow order — each stop names the FDD module it demonstrates. Log in as
**`staff8`** the whole way; switch accounts only for step 16 (RBAC).

### Warm-up (FDD 1.0 & 2.0)
1. **(1.0 Authentication)** At the login page, point out the form and the
   **"Forgot your password?"** link — it genuinely works: enter
   `staff8@seait.edu.ph` on that page, and the reset link is written to
   `storage/logs/laravel.log` (the demo machine has no mail server; in
   production the same flow sends real email). Show the link in the log file
   if the professor wants proof.
2. Log in as `staff8` / `password`.
3. **(2.0 Dashboard & Notifications)** The dashboard counts each office's
   pending work live. Point at the **bell** (top-right) — notifications.

### The core workflow (FDD 3.0 → 12.0)
One student's journey, front to back. Use **Pedro** (still pending) for the
intake end and **Juan** (fully processed) for everything after.

4. **(3.0 Admissions)** **Desks & Apps → Admissions Desk → Applicant
   Queue.** Open **Pedro Santos** — status **pending**, applicant details,
   address, guardian, education background, application mode. Point at the
   requirements checklist: one submission already has **Form 138 attached**
   (a real PDF uploaded through this screen — FDD 3.2.1 Document Upload).
   Click **Verify** on a requirement — status flips to verified (FDD 3.2.2).
   Optionally show the **Register Applicant** tab (FDD 3.1) — the full intake
   form.
5. **(4.0 Entrance Examinations)** **Desks & Apps → Guidance & Exam
   Lab → Exam Queue.** Click **Record School Entrance Exam** — the course
   dropdown lists **BSCrim and BSSW** (only exam-requiring courses appear —
   the two board courses with SCAT requirements), pick the
   term, and the **candidate dropdown auto-lists Pedro Santos** (admitted,
   not yet enrolled = exam candidate). Two-stage BR9: Guidance scores the
   **School Entrance** exam (Stage 1 — sees every result, pass and failed)
   and each academic department scores its own **Course-Specific** exam
   (Stage 2) for the passers Guidance transferred. That's FDD 4.1–4.4.
   The **Pass / Fail Results** tab (FDD 4.1.3) — Juan passed, listed under
   BSCrim.
6. **(5.0 Department Evaluations)** **Desks & Apps → Academic Evaluation.**
   Open Juan's evaluation: student profile captured (FDD 5.2), subjects
   proposed from the curriculum (FDD 5.3), signed by the evaluator
   (FDD 5.4), status `evaluated`. Point at the **Retention Examination**
   section on board-course evaluations (FDD 5.6, BR10) — the retention exam
   is recorded here in the Academic Evaluation area by the owning
   department, never in the Exam module; Maria's retention result is
   already on record.
7. **(6.0 Fee Assessment)** **Desks & Apps → Scholarship & Assessment.**
   Open Juan's assessment: itemized charges per fee type (FDD 6.2) — ₱11,250
   tuition, ₱4,500 laboratory, ₱1,500 miscellaneous, ₱250 library, ₱17,500
   assessed — finalized (FDD 6.3). If asked about corrections — FDD 6.4 lets
   you edit computed charge amounts.
8. **(7.0 Payments)** **Desks & Apps → Cashier Payment Desk.** Juan's
   receipts — OR **DEMO-OR-0001**, ₱12,000, status paid, against that ₱17,500
   sheet (FDD 7.2), and OR **DEMO-OR-TODAY-01**, ₱5,500, which the seed tool
   re-dates to the current day. Then the
   **Daily Collections Report** tab — and show the bookmarked URL from §1
   (`?date=2026-09-13`) so DEMO-OR-0001 appears in the day's collections
   (FDD 7.3). *Strong moment if the professor wants action: record a live
   payment on any assessment still on screen — the report updates to
   today instantly.*
9. **(8.0 Clearances)** **Desks & Apps → Campus Clearance.** Open **Maria
   Reyes**'s clearance slip — **all 9 requirement lines are approved**
   (Registrar, Accounting, Scholarship, Guidance, Blocking, Admission,
   Academic Department, Clinic, ID Office — the legacy Clearance office was
   folded into the Registrar, ruling 12) and the ₱100 replacement fee that
   Reference Data owns is recorded. **Print the slip** (FDD 8.4) — the browser
   print dialog opens, and the footer prints the fee from the fee type rather
   than a hard-coded amount. The **Clearance Periods** tab shows period
   management (FDD 8.2): closing refuses while any slip is still pending, with
   the count named, and a window extends only through its own action.
10. **(9.0 Block Sections)** **Desks & Apps → Blocking & Timetables.** Open
    block **BSCrim 1-A** — Juan is assigned, capacity vs enrolled counts,
    the timetable matrix with rooms and instructors, and conflict detection
    (FDD 9.2–9.4). **Print the schedule** (FDD 9.5).
11. **(10.0 Registrar)** **Desks & Apps → Registrar Official Suite.** Juan's
    record shows final approval (FDD 10.2). Show the print buttons —
    **Certificate of Registration, Class Cards, Subject Load** (FDD 10.3–
    10.6) — each opens a printable view. **Enrollment Form (PDF)** is the
    fourth tile and downloads instead of opening a screen: the Blade template
    is the paper, `print.enrollmentForm` guards it, and the issue row it mints
    is its own `enrollmentForm` sequence. Say plainly that this paper prints
    student type and standing but no program or term — that gap is finding
    P-2, open, not a broken button.
12. **(11.0 Clinic)** **Desks & Apps → School Clinic.** Juan's health
    assessment — height, weight, blood pressure, PhilHealth, physical exam
    findings (FDD 11.2–11.3).
13. **(12.0 Student IDs)** **Desks & Apps → Student ID Hub.** The hub is a queue of
    work still to do: it lists the enrollments whose ID box is still open, which on
    this box is **Danica Sotto (DEMO-2026-016)** and **Nena Alcantara
    (DEMO-2026-026)** — requests `pending`, every box ahead of them signed. Open
    Danica: with no photo on file the **Validate** button is disabled and the reason
    prints under it; attach the face photo (FDD 12.2), press **Validate** (FDD 12.3) —
    the desk shows the captured face on the pending request — and the box signs itself,
    so she leaves the queue. That is FDD 12.1–12.4 **done** rather than displayed.
    Validation is the desk's last action; the EMS no longer records card production or
    hand-over. Finished work now stays on the same screen: press the **Issued** tab and
    the desk lists every enrollment whose ID box is signed (seven on this box, against
    the two still in the queue), so **Kevin Santos** (DEMO-2026-017) opens from there
    with his request reading `validated` — no typing `/id/92`, and no dependence on an
    enrollment id that moves when the database is rebuilt. The tab is the desk's
    history, and its rows link to the same screen the queue's do. *One honest note
    remains: Juan's ID box is signed with no request behind it — that row claimed
    validated with no photo and no validator, was deleted under the owner's ruling on
    2026-10-11, and the signature was deliberately left standing, which is the one
    note `php artisan ems:audit:record-states` still reports. The Issued tab shows him
    with ID Status reading **None** rather than hiding the row.*

### Cross-cutting modules (FDD 13.0 → 15.0)
14. **(13.0 Student 360°)** Press **Ctrl+K**, type `Dela`, open Juan —
    **the student's whole life on one page**: admission history, exams,
    enrollment, subjects, payments, clearances, clinic records, documents
    (FDD 13.1–13.4). Open **Kevin** instead for the ID-request line, for the reason
    step 13 gives. This is also your fastest navigation tool all demo long.
15. **(14.0 Reference Data)** **Desks & Apps → Reference Catalogs** (admin
    section). Show 3–4 catalogs quickly — Courses, Fee Types, Blocks,
    Admission Requirements — and point out search/filters run server-side
    (type in the search box, results narrow, the URL carries the filter).
    That's FDD 14.1–14.3 across the 13 catalogs — the 13
    `admin/reference-data/*` GET screens the router answers: admission
    requirements, blocks, clearance requirements, courses, curriculums, fee
    types, grade scale, majors, offices, rooms, scholarship types, subjects and
    terms. The catalog landing page and the per-curriculum subject drill-in are
    not counted; `php storage/app/scratch/lane41-doc-claims.php` re-reads the
    number, and the peso figures below with it.
16. **(15.0 Users, Roles & Security)** **Desks & Apps → User & Role
    Security.** The roles list shows all 17 roles; open one to see its
    permission matrix (FDD 15.2). Switch to the **Audit Logs** tab —
    every action from this demo session is there with who/what/when
    (FDD 15.5). Then do the **RBAC switch** from §2: log out, log in as
    `office6_head`, open **Desks & Apps** — Admission only.

### Closing line for the professor
> "Every module in the FDD maps to what you just saw — 15 modules, 74
> functions, all live — and the whole system runs offline on this machine."

---

## 4. ENDING the demo (shutdown)

1. **Log out** (avatar, top-right → Log out) — a clean, logged end to the
   audit trail.
2. Close the browser tabs.
3. In **Laragon**, click **Stop All** (■). Both lights turn grey.
4. Done — Windows can sleep or shut down normally.

> Keep `storage/logs/laravel.log` — it holds the password-reset links that
> back up the FDD 1.3 demo if anyone asks for proof.

---

## 5. EMERGENCY: restore the demo data

**The dump to restore on demo day is the one you take the night before.** It is
the only file that re-creates exactly what §3 walks. Take it while the box is
right, keep it outside the repo (the remote is public; a dump of the working
database is not synthetic data). The one file in `Documentation/` earns its place
by being synthetic: all 16 of its students carry a `DEMO-2026-###` school id and
every student email in it is `@example.com` — re-check that before ever
committing a newer dump.

```powershell
# Laragon running (MySQL up), from PowerShell:
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe -u root --single-transaction ems > "C:\Users\ADMIN\ems-backups\ems-pre-demo-<date>.sql"
```

If data gets corrupted or the professor experiments and changes something,
restore that file and refresh the browser (Ctrl+F5):

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ems < "C:\Users\ADMIN\ems-backups\ems-pre-demo-<date>.sql"
```

Then run `php tools/seed_full_demo_pipeline.php` once. **Measured 2026-10-16** on a
copy of a dump taken exactly this way: `migrate --force` says *"Nothing to migrate"*
(the schema is current, unlike the September file), both seeders exit 0, and the
pipeline creates
no base rows, prints no `SCOPE —` banner and reports no findings — it relocates
nothing, and among the lines it prints the one state it changes is **DEMO-OR-TODAY-01**,
re-dated to the morning you are presenting, which is the line §1's sanity sweep reads.
Afterwards the box walks the same **18 of 19** records this machine does and
`ems:audit:record-states` reports the one ruled note, not a fault.

### If there is no fresh dump: `Documentation/ems-demo-backup.sql`

One command is **not** enough with that file. It is a **2026-09-20** snapshot —
its newest applied migration is `2026_09_20_1000000000_add_schedule_status_to_blocks_table`
and its newest audit row is 07:57 that morning, so every ruling and repair since
that date is simply not in it — and what it does put back was measured by loading
it into a scratch database and walking §3 (`php
storage/app/scratch/lane39-dump-contents.php` re-reads it; `php
storage/app/scratch/lane39-guide-preconditions.php` re-walks it). Its curriculum
half is still sound: 16 programs, the same 16 courses the live catalog holds.

| What the snapshot returns | What that costs you |
|---|---|
| 10 of its 11 enrollments on **term 18** — the Summer term this build retired | every desk that reads the demo term shows nothing: **10 of 19** §3 records are walkable, and Juan holds no enrollment on that term at all |
| 63 tables, including the retired `studentids`, and **no `shiftingrequests`** | the Shift Requests module has no table until `php artisan migrate --force` re-runs the 19 migrations the snapshot predates |
| 11 staff accounts | the 10 added since — 6 per-college Academic Department desks, the dean, 3 instructors — don't exist until both seeders run |
| Maria's slip with **10** approved requirement lines | step 9 would open a slip that contradicts the count the guide says out loud; `db:seed` retires legacy office 8 in foreign-key order (ruling 12) and the slip reads 9 lines again |
| `idrequests` row 53: `validated`, `cardPhotoPath` NULL, no validator | the contradictory row deleted under the owner's ruling on 2026-10-11 comes back, and `php artisan ems:audit:record-states` reports it again |
| 12 `documentprintlog` rows with no student, enrollment or block key | the same command reports those 12 too (§25 P-14) |
| 2 completed boxes on enrollment 89, signed by `office2_head` for offices 4 and 3 | the seed tool exits **1** naming them — the current build refuses a cross-office signature, and those rows are the snapshot's, not the tool's |

Run all five, in this order — each step's output is what the next one depends on:

```powershell
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ems < "C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\Documentation\ems-demo-backup.sql"
php artisan migrate --force
php artisan db:seed
php artisan db:seed --class=DevReferenceDataSeeder
php tools/seed_full_demo_pipeline.php
```

`db:seed` is not optional: without it the pipeline stops at its first named cause
— `scholarshiptypes … has no row with scholarshipName = Full Scholarship` — and
warns that the dataset may be partway seeded. The last command relocates the cast
off term 18 onto the demo term (it prints `G-9: … 18 -> 11`), re-dates the
clearance window to that term's calendar, and re-creates Kevin Santos's validated
ID request with its photo. It then exits 1 on the two September signatures above;
read that line rather than chasing it, because no action this build offers could
have written them and none can un-write them.

Afterwards the box walks 18 of §3's 19 records — the same single gap this machine
already carries, step 13's Juan — and `ems:audit:record-states` reports the 14
rows the snapshot re-introduced. Run the pipeline once per restore: its
`SCOPE —` banner prints only on a run that had to create the two base demo rows
itself, so a second run on the same database says nothing — and that banner is
what tells you the dataset in front of you was built by the tool rather than
restored.

---

## 6. Quick reference card (print this part)

```
URL:        http://localhost:8080
Admin:      staff8            / password
Registrar:  office1_head      / password
Accounting: office2_head      / password
Scholarship:office3_head      / password
Guidance:   office4_head      / password
Blocking:   office5_head      / password
Admission:  office6_head      / password
Academic:   office7_head      / password
Clearance:  office8_head      / password
Clinic:     office11_head     / password
ID Office:  office22_head     / password

Navigate:   "Desks & Apps" button (top bar)  |  Ctrl+K = search any student

Students:   Juan Dela Cruz   DEMO-2026-001  (full pipeline; ID box signed, no request behind it — ruled)
            Maria Reyes     DEMO-2026-002  (retention + clearance complete, 9 lines)
            Pedro Santos    DEMO-2026-003  (pending admission, exam candidate)
            Liza Bautista   DEMO-2026-004  (BSBA, retention candidate)
            Kevin Santos    DEMO-2026-017  (ID validated + face photo, on his 360 page)
            Danica Sotto    DEMO-2026-016  (open ID box — this is who step 13 validates)

Daily report (fixed-date OR DEMO-OR-0001, ₱12,000):  http://localhost:8080/accounting/daily-report?date=2026-09-13
Daily report (today): DEMO-OR-TODAY-01, ₱5,500 — re-dated to the current day by the seed tool
```
