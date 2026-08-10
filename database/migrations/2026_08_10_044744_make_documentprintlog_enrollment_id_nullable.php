<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Clearance slips are printed BEFORE the student is enrolled, so a
        // print log may legitimately have no enrollment. The service layer
        // already writes `?->enrollmentId`; only the column was wrongly NOT NULL.
        Schema::table('documentprintlog', function (Blueprint $table) {
            $table->integer('enrollmentId')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documentprintlog', function (Blueprint $table) {
            $table->integer('enrollmentId')->nullable(false)->change();
        });
    }
};
