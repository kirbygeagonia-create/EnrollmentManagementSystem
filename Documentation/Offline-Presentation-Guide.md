# Offline Presentation Guide

The system runs **100% offline** — no internet connection is needed to demo it.
This guide covers what was done, how to boot the full stack with no network,
and how to restore the demo data if anything goes wrong.

---

## What was done for offline capability

| Item | Before | After |
|---|---|---|
| Figtree font (400/500/600) | Loaded from `fonts.bunny.net` CDN | Self-hosted at `public/fonts/` (6 woff2 files, ~53 KB) via `@font-face` in `resources/css/app.css` |
| Content-Security-Policy | Allowed `fonts.bunny.net`, `fonts.googleapis.com`, `fonts.gstatic.com` | `style-src 'self'`, `font-src 'self'` — fully closed to third parties |
| QR codes | Already local — `react-qr-code` was bundled into `public/build/` at build time | Package removed with the card-making flow (no QR rendering remains) |
| Fonts / assets in built JS/CSS | — | Verified: **zero external URLs** in `public/build/assets/*` |
| Page resources (links/scripts/images) | — | Verified on dashboard, exam, blocking, student pages: all refs point to `localhost` |
| Mail / sessions / queue | Already local (`MAIL_MAILER=log`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=database`) | No change needed |

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
| Juan Dela Cruz (`demo_juan`) | DEMO-2026-001 | First-year BSCrim, walked the **entire pipeline**: admission → 2-stage entrance exam → evaluation → assessment → payment → registrar → blocking → clinic → ID **validated** (face photo on file) |
| Maria Reyes (`demo_maria_r`) | DEMO-2026-002 | Clearance slip generated + **all 10 offices approved** + receipt recorded |
| Pedro Santos (`demo_pedro`) | DEMO-2026-003 | **Pending admission** (BSCrim) — shows up as an entrance-exam candidate in the Exam form |
| Liza Bautista (`demo_liza`) | DEMO-2026-004 | **Enrolled BSBA** — shows up as a retention-exam candidate in the Exam form |

### Suggested demo storyline (follows the real workflow)

1. **Admission** (office6_head): open the admission of Pedro Santos — pending, awaiting exam.
2. **Entrance exam** (office4_head): Exam → Record Entrance Exam → BSCrim + term 18 → Pedro appears in the candidate dropdown → record **pass**, then course-specific **pass** (office7_head records that one).
3. **Evaluation** (office7_head): open Juan Dela Cruz's evaluation — profile captured, subjects proposed, signed.
4. **Assessment → Payment → Registrar** (offices 3, 2, 1): show Juan's computed charges, the recorded payment (OR DEMO-OR-0001), and registrar approval.
5. **Blocking** (office5_head): open block BSCrim 1-A — Juan assigned.
6. **Clinic** (office11_head): Juan's health record.
7. **ID** (office22_head): Juan's validated ID request — face photo on file.
8. **Retention exam** (office7_head): Exam → Record Retention → BSBA → Liza Bautista appears → record pass.
9. **Clearance** (office8_head): Maria Reyes's slip — all 10 offices approved, receipt recorded.

## If the demo data gets lost

Restore from the backup (created 2026-09-13, includes everything above):

```powershell
# from the project folder, in Laragon's MySQL bin
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root ems < Documentation\ems-demo-backup.sql
```

Re-seed from scratch instead (rebuilds the two full-pipeline students; Pedro/Liza
come from the scripts inside the file's git history — the backup is the safer option):

```powershell
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe seed_demo_data.php
```

## Offline verification (already performed — 2026-09-13)

- Built assets contain **no external URLs** (fonts, CDNs, scripts).
- Every `<link>/<script>/<img>` on dashboard / exam / blocking / student pages resolves to `localhost`.
- CSP header: `font-src 'self' data:` — no external font hosts.
- `/fonts/figtree-latin-400-normal.woff2` served locally (HTTP 200, `font/woff2`).
- All env drivers local: mail → log file, sessions → file, queue → database.
