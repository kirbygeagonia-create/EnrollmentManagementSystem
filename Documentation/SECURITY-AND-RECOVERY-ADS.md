# Security and Recovery (ADS problems 22 and 21, presentation items 3 and 6)

Every line here is either read from the code on 2026-11-01 or printed by a named instrument on that date. The
guide's own warning is honoured: no security certification is claimed, and what is not built is written as not
built.

## 1. Access control — who may do what, and where that is decided

**Server-side, per record, on every act.** 171 `$this->authorize()` calls across `app/Http/Controllers` resolve
against 15 policies in `app/Policies/`, registered in `app/Providers/AuthServiceProvider.php`. `routes/web.php`
carries only the `auth` middleware — no `can:` or `permission:` constraint on any route — which is deliberate: the
route answers "is this a signed-in staff member" and the policy answers "is this *your* record". The guide's rule
that hiding a button is not protection is therefore met structurally, not by convention.

| Control | Where it lives | What pins it |
|---|---|---|
| Named permissions, never bare office numbers | `database/seeders/RbacSeeder.php`, `App\Enums\OfficeId` | `PermissionMatrixTest`, `AbilityNameCollisionTest` |
| A gate ability may not wear a permission's name | `bootstrap/app.php` Spatie `Gate::before` | `AbilityNameCollisionTest` |
| College boundary in one class | `App\Support\CollegeScope` | policy cases, and the bell since 2026-11-01 (`NoticeCollegeBoundaryTest`) |
| Credential hashes out of every payload | `Staffusers::$hidden`, `Students::$hidden` | `tests/Feature/DeskQueue/DeskStudentLoadColumnsTest.php`, `mutate-g52-desk-payload-tests.php` |
| A sort join may not widen the row | `App\Support\ListSort` select pin | §28.3 G-51 cases, `mutate-g40-enum-offer-and-select-pin.php` |
| Private uploads not served at all | `config/filesystems.php` (`serve` off, ruling G-50) | `ServedUploadPathTest`, `mutate-g50-served-folder-tests.php` |
| Secrets redacted at write time | `app/Observers/AuditLogObserver.php` | the audit-redaction cases in `tests/Feature/Observers/` |

**Least privilege, measured.** Live `ems` on 2026-10-28 answered **96 permissions, 308 grants, 17 roles, 0 orphan
grants**; the same numbers re-read before this document were unchanged. The demo dataset holds 21 staff accounts
across nine offices, and the six college-bound department accounts (`dept.caf` … `dept.cte`) hold evaluation and
admission rights only.

**Sessions and transport.** `config/session.php`: `database` driver, 120-minute lifetime, HttpOnly true,
SameSite `lax`, `secure` from `SESSION_SECURE_COOKIE`. CSRF is the framework default for the `web` group and the SPA
sends `X-XSRF-TOKEN`, read from `resources/js/Layouts/AuthenticatedLayout.jsx`.
`SecurityHeaders` emits HSTS only when the application itself sees HTTPS; behind a TLS-terminating proxy the operator
sets `TRUSTED_PROXIES` (`config/trustedproxy.php`), and `ems:preflight` refuses the five spellings that look filled in
and install nothing, including the wildcard that would let any client forge its address while
`LoginRequest::throttleKey()` and `auditlogs.ipAddress` read `$request->ip()`.

**Injection.** All SQL reaches MySQL through Eloquent or query-builder bindings. The raw SQL that exists is in
migrations and in `selectRaw` aggregates over constant column lists; no request value is interpolated into a
statement. `php storage/app/scratch/lane46-filter-contract.php` additionally judges each desk filter against the
handler that reads it, which is where a sort or filter key that the server ignores would otherwise hide.

**Audit.** `auditlogs` carries `userId`, `action`, `entityTable`, `entityId`, `oldValues`, `newValues`, `ipAddress`,
`createdAt` and `adminOverride`, written by an observer attached to every model. 3,526 rows were on live `ems` when
the college-boundary work read it. Deletes are recorded as `deleted`, so the trail survives the row.

**What is not claimed.** No penetration test, no dependency audit gate, no CSP beyond the header added in `d29653e`,
and no role exists for a student — public `/register` is removed and students are deliberately not notifiable, so
"student login" is out of scope by design rather than by omission. Two privacy questions wait on the school and are
registered, not answered: who may read a clinical note beyond the clinic desk (§28.3 G-38), and the retention rule
the Data Privacy Act row asks for.

## 2. Recovery — the drill was run, not described

Commands for a dump existed in `Documentation/Demo-Day-Guide.md` and `Documentation/Offline-Presentation-Guide.md`,
and `tools/validate_demo_backup.php` can read a dump, but nothing had ever restored one and compared the rows — the
restore drill and the nightly job are unchecked boxes in `Documentation/First_Build_Plan.md`. The guide says plainly
that a backup file does not prove recovery, so the drill is now an executable tool and its reading is the evidence.

`php tools/ems-backup-drill.php --target=ems_drill`, run on 2026-11-01:

```
  autocommit                       ON
  innodb_flush_log_at_trx_commit   1
  sync_binlog                      1
dump: 1,415,647 bytes (1.4 MB), no DROP DATABASE, the domain tables are present
restored into ems_drill and compared 64 table(s) row for row
  ordered checksum students       rows 24/24   digest 5d6e4e38  MATCHES
  ordered checksum enrollments    rows 37/37   digest a4231be1  MATCHES
  ordered checksum payments       rows 15/15   digest 09044f58  MATCHES
scratch schema dropped (pass --keep to leave ems_drill in place): exit 0
VERDICT: 0 table(s) missing, 0 row-count or checksum difference(s), dump 1,415,647 bytes, restore exit 0.
```

Three things the run says that a paragraph of instructions does not.

- **The server's durability configuration was read, not assumed.** `innodb_flush_log_at_trx_commit = 1` and
  `sync_binlog = 1` are the full-sync pair, which is what makes the ACID durability claim an operating fact rather
  than a hope; a machine running with either at `2` or `0` would print a different line and the durability argument
  would have to be re-made.
- **The dump cannot destroy its source.** The tool refuses when the file carries `DROP DATABASE`, which is the
  exact failure this repository already lived through once — `mysqldump --databases` emits the drop as an executable
  version comment, so a `sed`-edited copy still drops the live schema. It also refuses a target name it was not told
  to own, so no invocation can restore over `ems`.
- **A count that agrees is not a table that agrees.** Row counts are compared for all 64 tables and then three
  ordered digests are taken. A digest is computed by reading the rows in key order and hashing them in the tool, not
  with `GROUP_CONCAT`, because `group_concat_max_len` defaults to 1024 bytes and would silently hash only the head of
  a large table — a checksum that quietly covers a prefix is a weaker claim than the one it appears to make.

**Backup frequency, retention and protection are still an owner step, and this document says so rather than
inventing a policy.** The dump lands in `C:\Users\ADMIN\ems-backups`, which is the only place a database dump is
allowed to live (a snapshot committed to the repository sat on a public remote for six weeks and had to be untracked,
with three documents re-pointed the same batch). A scheduled job has not been built; the guide's "document backup
frequency and retention" is answered here by naming the missing piece, not by describing a job that does not exist.
Point-in-time recovery is likewise not implemented: `binlog` files are enabled by `sync_binlog = 1` but no tool in
this repository replays them, so recovery to the last transaction is a documented procedure and a design, not a
demonstrated act.

## 3. Failed and committed transactions

Uncommitted work is invisible by construction: every enrollment act runs inside `DB::transaction(...)` whose closure
commits on return and rolls back on a throw, so a `try/catch` cannot accidentally report success for a partial write.
`php artisan ems:audit:record-states` weighs the states a desk must not be able to reach (20 rules) and answers 0
findings on live `ems`, which is the reading that says nothing partial is sitting on the records today. The
presentation's remaining half — a printed COMMIT beside a printed ROLLBACK from the same act — is owed as
traceability tests T02, T17 and T18, and the interruption case (a request key the desk can ask about after the
connection dies) is traceability row P17, **built on 2026-11-01**: `enrollments.requestKey` with
`uq_enrollments_request_key`, `EnrollmentIssuer::issue($attrs, $issuer, $requestKey)` answering a replay with the
enrollment the first send created, and a refusal when one key arrives naming a different student or term. What the
recovery story still does not have is a lookup route by key (the desk that lost its response is the desk that can
re-send, so the re-send is the lookup today) and any rate limit on the POST.
