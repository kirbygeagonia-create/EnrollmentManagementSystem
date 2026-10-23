<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ruled by the owner on 2026-10-23: an extension moves the clearance **window**, not a student.
 *
 * `extendedDeadline` was nullable, `$fillable` and `date`-cast, so it looked like the per-student half of
 * §27.7's extended state. It was never either half. Measured before dropping it, because "nothing writes it"
 * is a claim about code and about data, and only one of those is in the repository. `grep -rn extendedDeadline`
 * over `app/`, `resources/js/`, `database/seeders/`, `tests/` and `tools/` named the model's own `$fillable`
 * and `casts()` and nothing else — no writer, no reader, no screen, no test. Run the same grep after this batch
 * and `app/` answers empty: two files in `database/migrations/` are all that name the column, this table's
 * `create` migration and this one. Live `ems` held 4 `studentclearances` rows with `COUNT(extendedDeadline)` = 0,
 * so the column stored no data either. §30.4 had already recorded it as a value the schema carries and no code
 * uses, which is the shape that keeps an AI agent writing it into a specification.
 *
 * Dropping it rather than wiring it is the ruling. `Clearanceperiods::accepting()` (open OR extended) and the
 * window's own `extendPeriod()` action are the whole of the extended state, and a per-student date would have
 * needed a rule the school has not stated: whose deadline, set by whom, and what a slip does after it passes.
 * `down()` restores the column empty — the rollback puts the schema back, not an invention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studentclearances', function (Blueprint $table) {
            if (Schema::hasColumn('studentclearances', 'extendedDeadline')) {
                $table->dropColumn('extendedDeadline');
            }
        });
    }

    public function down(): void
    {
        Schema::table('studentclearances', function (Blueprint $table) {
            if (! Schema::hasColumn('studentclearances', 'extendedDeadline')) {
                $table->date('extendedDeadline')->nullable()->after('overallStatus');
            }
        });
    }
};
