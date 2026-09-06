<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (!Schema::hasColumn('admissions', 'applicationMode')) {
                $table->enum('applicationMode', ['faceToFace', 'online'])
                    ->default('faceToFace')
                    ->after('applicantType');
            }
        });
    }

    public function down(): void
    {
        Schema::table('admissions', function (Blueprint $table) {
            if (Schema::hasColumn('admissions', 'applicationMode')) {
                $table->dropColumn('applicationMode');
            }
        });
    }
};
