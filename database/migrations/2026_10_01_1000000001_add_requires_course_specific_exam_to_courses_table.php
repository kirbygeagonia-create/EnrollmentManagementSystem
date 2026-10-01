<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A program can require an entrance examination without requiring the departmental
 * one that follows it.
 *
 * `requiresEntranceExam` was doing both jobs at once: it made the school-wide General
 * examination a condition of admission, and it was the only signal that a department
 * also examines applicants for its own program. So the admission gate could refuse an
 * applicant for a missing general result while waving through the applicant who never
 * sat the Criminology or Education board test at all (§28 G-6/C-5). The two questions
 * need their own flag. Defaulted false: no program that was approvable yesterday
 * becomes unapprovable by an upgrade, and the Registrar turns it on per program in
 * Reference Data → Courses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('courses', 'requiresCourseSpecificExam')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->boolean('requiresCourseSpecificExam')->default(false)->after('requiresEntranceExam');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('courses', 'requiresCourseSpecificExam')) {
            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn('requiresCourseSpecificExam');
            });
        }
    }
};
