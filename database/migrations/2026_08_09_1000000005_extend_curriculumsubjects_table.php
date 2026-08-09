<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curriculumsubjects', function (Blueprint $table) {
            if (!Schema::hasColumn('curriculumsubjects', 'is_elective')) {
                $table->boolean('is_elective')->default(false)->after('semesterOffered');
            }
            if (!Schema::hasColumn('curriculumsubjects', 'elective_group')) {
                $table->string('elective_group')->nullable()->after('is_elective');
            }
            if (!Schema::hasColumn('curriculumsubjects', 'elective_min_choices')) {
                $table->unsignedTinyInteger('elective_min_choices')->nullable()->after('elective_group');
            }
            if (!Schema::hasColumn('curriculumsubjects', 'elective_max_choices')) {
                $table->unsignedTinyInteger('elective_max_choices')->nullable()->after('elective_min_choices');
            }
        });
    }

    public function down(): void
    {
        Schema::table('curriculumsubjects', function (Blueprint $table) {
            $columnsToDrop = ['elective_max_choices', 'elective_min_choices', 'elective_group', 'is_elective'];
            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('curriculumsubjects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};