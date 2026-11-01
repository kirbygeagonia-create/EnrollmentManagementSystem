<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADS guide problem 17 (prompt part 43.C): an enrollment POST that dies halfway — power out, browser closed,
 * network dropped — must not be able to leave two records, and the desk must get an answer to "did it commit?".
 *
 * `enrollments.requestKey` holds the key the issuing act was carrying. A repeat of the same act presents the same
 * key, and `EnrollmentIssuer::issue()` answers the enrollment that already exists instead of minting a second one.
 *
 * Three things this deliberately is, and why:
 *
 * **A plain nullable column with a UNIQUE index, not a generated column.** The active-seat key was withdrawn the day
 * it was added precisely because SQLite cannot build `ADD COLUMN … GENERATED`, so the whole suite went green over a
 * rule only CI could see. This index exists on both drivers, so the local suite is a witness rather than a bystander.
 *
 * **Nullable, with NULLs outside the rule.** MySQL and SQLite both treat NULLs as distinct in a unique index, so an
 * unkeyed write — a seeder, a migration backfill, a route that has not been given a key yet — is refused by nothing.
 * That is what makes the change additive: every existing caller keeps working untouched.
 *
 * **The index is the guard; the pre-check is only the fast path.** `firstWhere('requestKey', …)` answers the ordinary
 * retry, but two requests carrying one key can both pass a read check, so the unique key is what actually refuses —
 * lane 116's rule for notification dedupe, applied to the record the school cares more about.
 *
 * The guard is the repository's own rule for a new unique key — ask the table whether the data already violates it,
 * and name the rows if so — but it runs BETWEEN the two statements, because a guard that reads a column it has not
 * yet added is a migration that only works on the driver that ignores it.
 */
return new class extends Migration
{
    private const INDEX = 'uq_enrollments_request_key';

    public function up(): void
    {
        // Order matters, and not for tidiness. The guard used to ask `requestKey` a question BEFORE the column
        // existed, which on MySQL is `1054 Unknown column 'requestKey' in 'field list'` — and it passed the local
        // suite only because the guard was written to skip SQLite, so a migration that cannot run on the school's
        // engine was green here. Add the column, then weigh the data, then put the index last.
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->string('requestKey', 64)->nullable()->after('formIssuedDate');
        });

        $dupes = DB::select('select requestKey, count(*) c from enrollments
            where requestKey is not null group by requestKey having count(*) > 1');
        if ($dupes !== []) {
            $keys = implode(', ', array_map(fn ($r) => $r->requestKey.' ('.$r->c.' rows)', $dupes));

            // The refusal is undoable rather than half-applied: the column comes back out, so the schema is left as
            // it was found and the operator has a named duplicate to resolve, not a table with a stray column.
            Schema::table('enrollments', function (Blueprint $table): void {
                $table->dropColumn('requestKey');
            });

            throw new RuntimeException('Refusing to add '.self::INDEX." over data that already violates it: {$keys}");
        }

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->unique('requestKey', self::INDEX);
        });
    }

    public function down(): void
    {
        // The index goes before the column: a unique index over a column SQLite is asked to drop is the one way to
        // make a rollback report the schema rather than remove it. Both drivers built the pair in up(), so both can
        // take it apart in this order.
        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });

        Schema::table('enrollments', function (Blueprint $table): void {
            $table->dropColumn('requestKey');
        });
    }
};
