# Demo Day Guide — SEAIT Enrollment Management System

Everything you need to run the full demo: start the system, log in as any
office, walk the professor through every FDD module, and shut it down cleanly.

**The system is 100% offline-capable** — no internet is needed at any point.
All fonts, scripts, QR rendering, and assets are served from this machine.

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
   OR **DEMO-OR-0001** (₱17,500) is listed. ✅
   *(Needed because the Daily Collections Report defaults to **today**; the
   seeded payment is dated 2026-09-13. Just bookmark this URL.)*
4. Log out. **You are ready.**

### If something looks wrong
| Symptom | Fix |
|---|---|
| "This site can't be reached" | Apache isn't running — Laragon → **Start All** again |
| Login page loads but login fails | MySQL isn't running — check Laragon's Database light |
| Page looks unstyled (no fonts/colors) | Wrong URL — use `http://localhost:8080` exactly, then Ctrl+F5 |
| Daily report shows "No payments" | Add `?date=2026-09-13` to the URL (see Step 3.3) |
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
| `office4_head` | Guidance | 4.0 Exams (course-specific entrance) + 5.0 Evaluations |
| `office5_head` | Blocking | 9.0 Block Sections & Schedules |
| `office6_head` | Admission | 3.0 Admissions — intake, requirements, approval |
| `office7_head` | Academic Department | 4.0 Exams (general + retention) |
| `office8_head` | Clearance | 8.0 Clearances |
| `office11_head` | Clinic | 11.0 Clinic Assessments |
| `office22_head` | ID Office | 12.0 Student IDs |

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
| **Juan Dela Cruz** | DEMO-2026-001 | The fully-processed first-year: admission → exams → evaluation → assessment → payment → registrar → block → clinic → **ID card with QR `SEAIT-DEMO-53`** |
| **Maria Reyes** | DEMO-2026-002 | Continuing student: **retention exam passed**, clearance slip with **all 10 offices approved** + receipt |
| **Pedro Santos** | DEMO-2026-003 | **Pending admission** — 3 requirement submissions, Form 138 PDF attached; the entrance-exam candidate |
| **Liza Bautista** | DEMO-2026-004 | Enrolled BSBA from a prior term — the retention-exam candidate |

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
5. **(4.0 Entrance & Retention Exams)** **Desks & Apps → Guidance & Exam
   Lab → Exam Queue.** Click **Record Entrance Exam** — the course dropdown
   lists **BSCrim** (only exam-requiring courses appear), pick the term, and
   the **candidate dropdown auto-lists Pedro Santos** (admitted, not yet
   enrolled = exam candidate). That's FDD 4.1–4.4. Now back up and click
   **Record Retention** — the course list now shows **BSBA** and the
   candidates show **Liza Bautista** — that's FDD 4.5 (BR10: retention
   gating for board courses). Finally the **Pass / Fail Results** tab
   (FDD 4.6) — Juan passed, listed under BSCrim.
6. **(5.0 Department Evaluations)** **Desks & Apps → Academic Evaluation.**
   Open Juan's evaluation: student profile captured (FDD 5.2), subjects
   proposed from the curriculum (FDD 5.3), signed by the evaluator
   (FDD 5.4), status `evaluated`.
7. **(6.0 Fee Assessment)** **Desks & Apps → Scholarship & Assessment.**
   Open Juan's assessment: itemized charges per fee type (FDD 6.2),
   finalized (FDD 6.3). If asked about corrections — FDD 6.4 lets you edit
   computed charge amounts.
8. **(7.0 Payments)** **Desks & Apps → Cashier Payment Desk.** Juan's
   payment — OR **DEMO-OR-0001**, ₱17,500, status paid (FDD 7.2). Then the
   **Daily Collections Report** tab — and show the bookmarked URL from §1
   (`?date=2026-09-13`) so his OR appears in the day's collections
   (FDD 7.3). *Strong moment if the professor wants action: record a live
   payment on any assessment still on screen — the report updates to
   today instantly.*
9. **(8.0 Clearances)** **Desks & Apps → Campus Clearance.** Open **Maria
   Reyes**'s clearance slip — **all 10 office sign-offs are approved**
   (Registrar, Accounting, Scholarship, Guidance, Blocking, Admission,
   Academic Department, Clearance, Clinic, ID Office) and the ₱100 receipt
   is recorded (FDD 8.6). **Print the slip** (FDD 8.4) — the browser print
   dialog opens. The **Clearance Periods** tab shows period management
   (FDD 8.2).
10. **(9.0 Block Sections)** **Desks & Apps → Blocking & Timetables.** Open
    block **BSCrim 1-A** — Juan is assigned, capacity vs enrolled counts,
    the timetable matrix with rooms and instructors, and conflict detection
    (FDD 9.2–9.4). **Print the schedule** (FDD 9.5).
11. **(10.0 Registrar)** **Desks & Apps → Registrar Official Suite.** Juan's
    record shows final approval (FDD 10.2). Show the print buttons —
    **Certificate of Registration, Class Cards, Subject Load** (FDD 10.3–
    10.6) — each opens a printable view.
12. **(11.0 Clinic)** **Desks & Apps → School Clinic.** Juan's health
    assessment — height, weight, blood pressure, PhilHealth, physical exam
    findings (FDD 11.2–11.3).
13. **(12.0 Student IDs)** **Desks & Apps → Student ID Hub.** Juan's ID:
    requested → produced → **validated**, with the **QR code** — scan it
    with your phone and it reads **`SEAIT-DEMO-53`** (studentId 53). That's
    FDD 12.1–12.5 end-to-end.

### Cross-cutting modules (FDD 13.0 → 15.0)
14. **(13.0 Student 360°)** Press **Ctrl+K**, type `Dela`, open Juan —
    **the student's whole life on one page**: admission history, exams,
    enrollment, subjects, payments, clearances, clinic records, ID card,
    documents (FDD 13.1–13.4). This is also your fastest navigation tool
    all demo long.
15. **(14.0 Reference Data)** **Desks & Apps → Reference Catalogs** (admin
    section). Show 3–4 catalogs quickly — Courses, Fee Types, Blocks,
    Admission Requirements — and point out search/filters run server-side
    (type in the search box, results narrow, the URL carries the filter).
    That's FDD 14.1–14.3 across the 12 catalogs.
16. **(15.0 Users, Roles & Security)** **Desks & Apps → User & Role
    Security.** The roles list shows the 11 roles; open one to see its
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

If data gets corrupted or the professor experiments and changes something:

```powershell
# Laragon running (MySQL up), from PowerShell:
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ems < "C:\Users\ADMIN\OneDrive\Documents\3rd_Year\EnrollmentManagementSystem\Documentation\ems-demo-backup.sql"
```

Then refresh the browser (Ctrl+F5). All four students, all workflow states,
and all 11 accounts return exactly as documented above.

Backup taken **2026-09-13** — 63 tables, all demo rows included.

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

Students:   Juan Dela Cruz   DEMO-2026-001  (full pipeline, QR: SEAIT-DEMO-53)
            Maria Reyes     DEMO-2026-002  (retention + clearance complete)
            Pedro Santos    DEMO-2026-003  (pending admission, exam candidate)
            Liza Bautista   DEMO-2026-004  (BSBA, retention candidate)

Daily report (seeded OR):  http://localhost:8080/accounting/daily-report?date=2026-09-13
```
