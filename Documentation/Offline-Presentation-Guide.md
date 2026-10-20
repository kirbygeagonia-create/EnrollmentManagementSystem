# Offline Presentation Guide

The system runs **100% offline** — no internet connection is needed to demo it.
This guide covers what was done, how to boot the full stack with no network,
and how to restore the demo data if anything goes wrong.

---

## What was done for offline capability

| Item | Before | After |
|---|---|---|
| Figtree font (400/500/600/700/800, latin + latin-ext) | Loaded from `fonts.bunny.net` CDN | Self-hosted at `public/fonts/` — re-counted 2026-10-16: **10 woff2 files, 88,516 bytes (~86 KB)** — via `@font-face` in `resources/css/app.css` |
| Content-Security-Policy | Allowed `fonts.bunny.net`, `fonts.googleapis.com`, `fonts.gstatic.com` | `default-src 'self'`, `script-src 'self' 'unsafe-inline' 'unsafe-eval'`, `style-src 'self' 'unsafe-inline'`, `font-src 'self' data:`, `img-src 'self' data: blob:` (`app/Http/Middleware/SecurityHeaders.php:35-39`) — no third-party source in any directive except the dev-server origins in `connect-src`. Closed 2026-10-17: `img-src` had also carried a bare `https:`, which permitted an image from any host even though nothing in the app loads one; every print `<img>` reads `PrintAssets::logoDataUri()`, a `data:` URI, and a sweep of `resources/js` and `resources/views` found no remote image. The whole string is pinned by `tests/Feature/SecurityHeadersTest.php`, and `php artisan ems:print-fidelity` still renders 8/8 |
| QR codes | Already local — `react-qr-code` was bundled into `public/build/` at build time | Package removed with the card-making flow (`grep -c qr package.json` → 0); no QR rendering remains |
| Fonts / assets in built JS/CSS | — | Re-checked 2026-10-16: **nothing in `public/build/assets/*` is fetched**. The only remote strings are `http://www.w3.org/…` XML namespaces, `http://localhost` literals, and one `https://reactjs.org/docs/error-decoder.html` link inside React's own error text — three text values, zero requests |
| Page resources (links/scripts/images) | — | Verified on dashboard, exam, blocking, student pages: all refs point to `localhost` |
| Mail / sessions / queue | Already local (`MAIL_MAILER=log`, `SESSION_DRIVER=file`) | No change needed. `.env` now runs `QUEUE_CONNECTION=sync` — a job is executed in the request instead of waiting on a worker; `.env.example` still ships `database` |

Every HTTP request the browser makes during a demo resolves to
`http://localhost:8080`. Disconnecting Wi-Fi changes nothing.

---

## Boot checklist (demo day, no internet)

Run through this ~10 minutes before presenting:

1. **Start Laragon** (Apache + MySQL start automatically).
2. Confirm MySQL is up: the Laragon window shows both green.
3. The site is served from the pre-built assets — no `npm run dev` needed.
   Open **http://localhost:8080**.
4. Log in as the admin: **staff8 / password**
   (office heads: `office1_head`, `office2_head`, … `office22_head`, same password).

## Demo dataset (already seeded)

| Login (username) | Password | Who |
|---|---|---|
| `staff8` | `password` | SysAdmin — sees everything |
| `office6_head` | `password` | Admission Office |
| `office7_head` | `password` | Academic Department (course-specific exams, evaluations, retention) |
| `office4_head` | `password` | Guidance (School Entrance Examination) |
| `office3_head` | `password` | Scholarship (fee assessments) |
| `office2_head` | `password` | Accounting |
| `office1_head` | `password` | Registrar |
| `office5_head` | `password` | Blocking & Scheduling |
| `office11_head` | `password` | Clinic |
| `office22_head` | `password` | ID Office |

**Students:**

| Student | School ID | State |
|---|---|---|
| Juan Dela Cruz (`demo_juan`) | DEMO-2026-001 | First-year BSCrim, walked the **entire pipeline**: admission → 2-stage entrance exam → evaluation → assessment → payment → registrar → blocking → clinic. His ID **box** is signed with no request behind it — that row was deleted under the owner's ruling of 2026-10-11 and the signature left standing, so the ID desk shows Kevin |
| Maria Reyes (`demo_maria_r`) | DEMO-2026-002 | Clearance slip generated + **all 9 requirement lines approved** + receipt recorded (the legacy Clearance office was folded into the Registrar, ruling 12) |
| Pedro Santos (`demo_pedro`) | DEMO-2026-003 | **Pending admission** (BSCrim) — shows up as an entrance-exam candidate in the Exam form |
| Liza Bautista (`demo_liza`) | DEMO-2026-004 | BSBA, completed year 2 on the prior term — the retention-exam candidate, and the standing evidence the Evaluation desk repeats her into year 2 with |
| Kevin Santos (`demo_kevin`) | DEMO-2026-017 | BSCrim year 1 on the demo term, every box signed, ID request **validated** with a **face photo on file** — the ID desk's finished example |

### Suggested demo storyline (follows the real workflow)

1. **Admission** (office6_head): open the admission of Pedro Santos — pending, awaiting exam.
2. **Entrance exam** (office4_head): Exam → Record Entrance Exam → BSCrim + the demo term (termId 11, AY 2025-2026 2nd semester) → Pedro appears in the candidate dropdown → record **pass**, then course-specific **pass** (office7_head records that one).
3. **Evaluation** (office7_head): open Juan Dela Cruz's evaluation — profile captured, subjects proposed, signed.
4. **Assessment → Payment → Registrar** (offices 3, 2, 1): show Juan's computed charges (₱17,500 across four fee types), the recorded receipts (OR DEMO-OR-0001 ₱12,000 on 2026-09-13 and OR DEMO-OR-TODAY-01 ₱5,500 on the current day), and registrar approval.
5. **Blocking** (office5_head): open block BSCrim 1-A — Juan assigned.
6. **Clinic** (office11_head): Juan's health record.
7. **ID** (office22_head): the hub lists only **open** ID boxes — on this box Danica Sotto (DEMO-2026-016) and Nena Alcantara (DEMO-2026-026). Attach Danica's face photo and press **Validate**; that act signs the box, so she leaves the queue. Then **Kevin Santos**'s history shows the finished state (`validated`) on his record page — the hub itself keeps no list of validated requests.
8. **Retention exam** (office7_head): on the **Department Evaluation** screen, not the Exam module — `POST evaluation/{enrollment}/retention` (FDD 5.6, BR10). Liza Bautista's repeating-year record is the one to record it against; Maria Reyes's is already on file.
9. **Clearance** (staff8): Maria Reyes's slip — **all 9 requirement lines approved**, receipt printed. `office8_head` exists for the opposite demo: it holds **no** sign right, so an approval attempted there is refused (rulings 7 and 12).

## If the demo data gets lost

**Restore a dump you took of the current `ems`, not the one in `Documentation/`.**
`Documentation/ems-demo-backup.sql` is a **2026-09-20** snapshot, and loading it is
one of five commands, not the whole recovery — `Documentation/Demo-Day-Guide.md` §5
carries the order and, row by row, what the snapshot puts back (10 of its 11
enrollments come back on the retired term 18, its schema predates 19 migrations and
has no `shiftingrequests`, and it re-creates the contradictory ID request the owner
ruled deleted on 2026-10-11).

```powershell
# Laragon running (MySQL up), from PowerShell — take this the night before:
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqldump.exe -u root --single-transaction ems > "C:\Users\ADMIN\ems-backups\ems-pre-demo-<date>.sql"
```

Rebuilding from empty instead runs every desk and workflow queue and leaves **no**
record in a state a desk could not have produced — measured 2026-10-16 on a scratch
database: all four steps exit 0, the pipeline reports no findings, and
`php artisan ems:audit:record-states` prints no FAIL line (12 rules when that was measured; the command carries
16 now — lane 53 added a bare fee sheet and an unrecorded payment step on 2026-10-18, and lane 55 the two
timetable collisions on 2026-10-19):

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan migrate:fresh --force
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan db:seed
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe artisan db:seed --class=DevReferenceDataSeeder
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe tools\seed_full_demo_pipeline.php
```

What that dataset does **not** hold is the named cast the storyline above walks: no
Pedro Santos, no Maria Reyes, no `office8_head`, and no intake papers, exam results,
clinic record or DEMO-OR-0001 on the Juan it creates — 6 of the 19 records the
walkthrough needs, against 18 of 19 on the restored box. The pipeline prints exactly
that as a `SCOPE —` block after its own success banner, so a box built from empty
says so out loud instead of failing quietly at step 4.

## Offline verification (performed 2026-09-13; the asset and env bullets re-checked 2026-10-16)

- Nothing fetched from a remote host in the built assets — the only remote strings
  are XML namespaces, `localhost` literals and one React error-decoder link in text.
- Every `<link>/<script>/<img>` on dashboard / exam / blocking / student pages resolves to `localhost`.
- CSP header: `font-src 'self' data:` — no external font hosts.
- `/fonts/figtree-latin-400-normal.woff2` served locally (HTTP 200, `font/woff2`).
- All env drivers local: mail → log file, sessions → file, queue → `sync` (`.env`
  line 41; a job runs inside the request, so no worker is needed to demo offline.
  `.env.example` still ships `database` — set it to `sync` on a fresh box or a
  queued job sits in `jobs` unrun).
