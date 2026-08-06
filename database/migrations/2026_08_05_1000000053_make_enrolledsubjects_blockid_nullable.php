<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subject load is proposed during Department Evaluation, but blocks are
     * assigned later in the workflow (Blocking & Scheduling phase, after
     * Registrar approval). enrolledsubjects.blockId must therefore be nullable.
     */
    public function up(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->integer('blockId')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('enrolledsubjects', function (Blueprint $table) {
            $table->integer('blockId')->nullable(false)->change();
        });
    }
};
