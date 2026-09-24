<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            // Blocking & Scheduling Officer can finalize the block's timetable
            // (item 9): 'final' locks schedule add/edit/delete while student
            // assignment stays open up to capacity.
            $table->enum('scheduleStatus', ['draft', 'final'])->default('draft')->after('maxStudents');
        });
    }

    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn('scheduleStatus');
        });
    }
};
