# SEAIT EMS — Current State Assessment (Round 3)

> **Owner addendum, 2026-10-17.** This file is kept as provenance: it records the reviewer's verdict on
> 2026-09-05, and most of it is still true. Four present-tense statements in it are not, and they are the
> ones a reader would quote, so they are corrected here rather than left to drift. `php
> storage/app/scratch/lane42-assessment-claims.php` re-reads this file and the 2026-08-29 remediation note
> against the current build and fails on any un-dated state claim that no longer holds — that is how these
> four were found.
>
> - **The CSP was not deferred.** `d29653e` added the header at
>   `app/Http/Middleware/SecurityHeaders.php:46` **in the same commit that stored this file**, so the scorecard
>   row below was already false the moment it was written. It stayed false for six weeks because nothing
>   pinned any of these headers; `tests/Feature/SecurityHeadersTest.php` does now, including the whole
>   `Content-Security-Policy` string.
> - **The counts have moved with the build:** 93 permissions, 63 tables, 23 controllers, 17 roles
>   (measured on live `ems` 2026-10-17, `SELECT COUNT(*)` against `permissions`, `roles` and
>   `information_schema.tables`). The 88 / 54 / 22 figures below are the 2026-09-05 counts and are left in
>   place as what the reviewer saw.
> - **One HSTS caveat the original did not test.** The header is sent only when this application itself sees
>   the request as HTTPS — `bootstrap/app.php` never calls `trustProxies()` and `config('trustedproxy.proxies')`
>   is unset, so `X-Forwarded-Proto` from a TLS-terminating proxy is discarded and no HSTS is emitted. The
>   middleware's own comment claimed the forwarded header would be enough; measured, it is not. Behind a proxy
>   the operator must name that proxy explicitly. Ruled 2026-10-17: the code stays as it is — trusting every
>   caller would let a client forge its own address, which the login throttle key and `auditlogs.ipAddress`
>   both read — and `DEPLOYMENT.md` §2 carries the instruction.
> - **`img-src` was widened beyond what the app uses.** It read `'self' data: blob: https:`, permitting an
>   image from any host on the internet; nothing in `resources/js` or `resources/views` names a remote image
>   and every print `<img>` is a `data:` URI, so it was narrowed to `'self' data: blob:` on 2026-10-17.
> - **The npm advisory line was never about this repository.** It reports whatever the advisory database said
>   that week; it is printed by the checker and deliberately not judged.

**Commits reviewed:** 71 (7 new since the follow-up audit, including `05bbe55` and `4690ca8` — the two commits addressing the follow-up findings and the Transition/modal bug you just fixed).
**Method:** fresh clone, re-verified every open item from both prior reports by reading the actual code, plus a fresh `npm install && npm run build && npm run lint && npm audit`.

## Bottom line

**Yes — this is now a legitimate, reasonably production-ready Enrollment Management System**, in the scoped sense established earlier: a staff-operated digitization of a real multi-office enrollment workflow (13 process roles + Staff/Instructor/OfficeHead cross-cutting roles, 88 permissions, 54 tables, 22 controllers as counted on 2026-09-05; measured again 2026-10-17 it holds 93 permissions, 63 tables and 23 controllers, and the role sentence above undercounted by one even then — `RbacSeeder` declared 17 roles, 14 desk-and-administration plus the 3 cross-cutting), not a student self-service portal (that's still a documented future phase, schema pre-provisioned for it, not built yet).

Everything **Critical** and **High** from the original audit is fixed and verified. The one new issue the follow-up audit surfaced (the audit-log side-channel around student data) is also fixed, tested, and has a dedicated regression test guarding it. What was left as of 2026-09-05 was genuinely minor — the CSP header and a transitive npm advisory. Of the two, the CSP shipped the same day (corrected 2026-10-17: see the addendum, `app/Http/Middleware/SecurityHeaders.php:46`, now pinned by `tests/Feature/SecurityHeadersTest.php`); the npm advisory line is a registry fact rather than a property of this repository. The two things the review left genuinely open — an over-broad `img-src` and HSTS behind a proxy — are dealt with below.

---

## Full scorecard, consolidated across all three rounds

| Finding | Round found | Status now | Verified by |
|---|---|---|---|
| Open self-registration (`/register`) | Original, Critical | ✅ Fixed | Route and controller both gone; confirmed `grep -c register routes/auth.php` = 0 |
| `StudentController` had zero authorization | Original, Critical | ✅ Fixed | `StudentPolicy` gates all 3 actions (`viewAny`, `view`, `quickSearch`); permission correctly excluded from `Staff`/`Instructor` |
| `APP_DEBUG=true` default, no safeguard | Original, Critical | ✅ Fixed | `AppServiceProvider` refuses to boot in production with debug on |
| No CI/CD | Original, High | ✅ Fixed | `.github/workflows/ci.yml` — SQLite job, live MySQL 8 job, frontend job |
| Tests never ran on MySQL | Original, High | ✅ Fixed | Dedicated MySQL CI job; two follow-up commits exist specifically to make it pass on fresh runners |
| No admin bootstrap process | Original, High | ✅ Fixed | `ems:create-admin`, and the one bug in it (duplicate `employeeNo` on a second run) is now fixed with an incrementing suffix |
| Unrestricted document upload | Original, High | ✅ Fixed | `mimes:pdf,jpg,jpeg,png,doc,docx` added, matching the frontend's declared intent |
| Puppeteer/Chromium missing | Original, High | 📋 Documented (infra step, not code) | `DEPLOYMENT.md` §6 spells out the install/config step |
| `SESSION_SECURE_COOKIE` unset by default | Original, High | ✅ Fixed | Flagged in `.env.example`, mandated in `DEPLOYMENT.md` |
| Hardcoded office-ID magic numbers (27 occurrences) | Original, Medium | ✅ Fixed | `OfficeId` enum exists; **zero** raw integer occurrences remain anywhere (re-swept fresh, confirmed clean — the 5 stragglers from Round 2 are gone) |
| No security headers | Original, Medium | ✅ Fixed — and the CSP parenthetical below was corrected on 2026-10-17 | `SecurityHeaders` middleware live: X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy, conditional HSTS |
| Dead `User` model | Original, Medium | ✅ Fixed | Removed |
| `$request->all()` into `::create()` | Original, Medium | ✅ Fixed | Confirmed gone from `ReferenceDataController` |
| Missing timestamps on core tables | Original, Low | ✅ Fixed | Migration adds them to all 8 named transactional tables |
| **Audit log leaked password hashes + bypassed student-data scoping** | Follow-up, new High | ✅ Fixed, with a regression test | `AuditLogObserver` redacts any key matching `password\|hash\|token\|secret` **at write time**; `audit.view` pulled off the base `Staff` role; `tests/Feature/Observers/AuditLogRedactionTest.php` asserts the actual hash value never appears in stored JSON, in both create and update paths |
| `ems:create-admin` duplicate-key bug | Follow-up, new Low | ✅ Fixed | Incrementing `EMP-ADMIN-00N` suffix |
| **The Transition/modal crash you reported** | Reported by you | ✅ Fixed (per your confirmation, and consistent with what I see in code) | `4690ca8` — three real fixes bundled together: a Headless UI `Transition.show` bug in `ui/Modal`, a Spatie ability/permission name collision on `clinic.record` (renamed to `clinic.recordAssessment` — the exact class of bug the original `AuthServiceProvider` comments warned about elsewhere, one instance had slipped through), and `InvalidStateTransitionException` now has a centralized `$exceptions->render()` handler in `bootstrap/app.php` that turns it into a friendly flash message instead of a raw 500 |
| Content-Security-Policy header | Original, Medium | ✅ Closed 2026-09-05 by `d29653e` — the same commit that stored this row, so it was already false as written (corrected 2026-10-17) | `SecurityHeaders.php:46` sets the full policy; nothing pinned it until `tests/Feature/SecurityHeadersTest.php` (2026-10-17), which is how the row survived six weeks. The reviewer's caution was sound — a CSP that breaks Inertia/Vite asset loading is a real risk — and the test is what made the one follow-up change safe to make: `img-src` had been `'self' data: blob: https:`, which permitted an image from any host on the open internet, and is now `'self' data: blob:` |
| npm supply-chain advisory | Ongoing | ⚠️ Different one now | Round 2's `nanoid` issue is gone; a fresh `npm install` today pulls in a new moderate-severity transitive advisory in `qs` (unrelated to your code — the advisory database updated between rounds). `npm audit fix` resolves it. |

---

## A few things worth calling out from this pass specifically

- **The `clinic.record` fix is a genuinely subtle catch.** Spatie's permission package auto-grants any ability whose name exactly matches a permission the user holds, bypassing the actual Policy method entirely. `ClinicPolicy::record()` had real logic behind it (e.g. checking whether a record already exists) that a `clinic.record` permission-holder could've skipped straight past. Renaming the ability to `clinic.recordAssessment` closes that specific collision. This is the same class of issue the original `AuthServiceProvider` comments show they were already guarding against elsewhere — this was the one instance that had slipped through, and it's now closed.
- **The audit-log fix came with a test that checks the right thing.** Not just "is the field masked" but "does the actual hash value appear anywhere in the stored payload" — a meaningfully stronger assertion, and it also confirms non-sensitive fields (like status) still pass through unredacted, so the fix isn't over-broad either.
- **The error-handling fix is centralized correctly.** `InvalidStateTransitionException` is now handled once, in `bootstrap/app.php`, rather than needing a try/catch repeated across the four controllers that throw it — so any future controller that calls into `EnrollmentStateMachine`/`WorkflowService` gets the friendly-error behavior automatically, for free.
- **Test coverage grew, not just held steady** — a full `ExamControllerTest` (two-stage entrance exam, retention-exam gating) and the audit-redaction regression test are both new since the follow-up.
- Build, lint, and dependency audit were all re-run fresh just now: `npm run build` succeeds, `npm run lint` is clean, `npm audit` shows one moderate (unrelated, freshly-disclosed, one-command fix).

## What's left, in order of any remaining priority

1. `npm audit fix` for the `qs` advisory — trivial, do it whenever. (Re-checked 2026-10-17: `qs` is no longer
   listed and the advisory database has moved again — 12 packages flagged on this box. It is a registry fact,
   so `lane42-assessment-claims.php` prints it and deliberately does not judge it.)
2. ~~Content-Security-Policy header~~ — closed 2026-09-05 by `d29653e`. Its one follow-up, the bare `https:`
   source in `img-src`, was tightened to `'self' data: blob:` on 2026-10-17 (`app/Http/Middleware/SecurityHeaders.php:39`),
   after a sweep found no remote image anywhere in the frontend and `ems:print-fidelity` still rendered 8/8.
   The whole policy string is pinned by `tests/Feature/SecurityHeadersTest.php`, so any future widening has to
   be chosen deliberately.
3. HSTS behind a proxy — the one item that needs the deployment, not the code. `SecurityHeaders` gates the
   header on `$request->isSecure()`, and this build trusts no proxy, so `X-Forwarded-Proto` is discarded and
   the header never appears when TLS ends at a load balancer. `DEPLOYMENT.md` §2 (Environment) now states the
   choice and why answering it with `at: '*'` would be wrong. Ruled 2026-10-17: leave the code, instruct the
   operator.
4. Puppeteer/Chromium install — this is purely a deployment-environment step per `DEPLOYMENT.md`, not something fixable in the repo itself; just don't forget it when you actually deploy.

Nothing else on the list is outstanding. Across three rounds this went from "well-modeled but not production-safe" to "the security and operational gaps are closed, with tests to keep them closed" — that's a real trajectory, not just a patch job.
