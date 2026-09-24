<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalog expansion (refinement item 5): every subject carries its official
 * CHED course description alongside the code, name, and unit breakdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('subjectDesc', 500)->nullable()->after('subjectName');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('subjectDesc');
        });
    }
};
