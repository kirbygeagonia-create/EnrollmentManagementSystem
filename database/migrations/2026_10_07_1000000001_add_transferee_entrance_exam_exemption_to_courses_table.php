<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an arriving transferee still owes the school-wide General Entrance Examination
 * is the program's to decide, not the code's.
 *
 * Owner ruling 2026-10-07 (C-4). Until now the requirement was unconditional for every
 * applicant to a program that sets requiresEntranceExam, so a student arriving with credit
 * from another institution sat the same general paper as a first-year. A blanket exemption
 * written here would decide an institutional question on a developer's authority, and the
 * answer will not be the same for every program — so the decision becomes a per-program
 * setting the department owns in Reference Data → Courses, beside the two exam flags that
 * ruling C-5/G-6 already moved there.
 *
 * Defaulted false: an upgrade changes nothing that was approvable yesterday, and a program
 * opts in one checkbox at a time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'entranceExamExemptsTransferee')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('entranceExamExemptsTransferee')->default(false)->after('requiresCourseSpecificExam');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'entranceExamExemptsTransferee')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('entranceExamExemptsTransferee');
            });
        }
    }
};
