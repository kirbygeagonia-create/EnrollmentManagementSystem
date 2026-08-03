<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('majors', function (Blueprint $table) {
            $table->integer('majorId')->autoIncrement();
            $table->integer('courseId');
            $table->string('majorName', 150);
            $table->index(['courseId'], 'fk_majors_courseid');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('majors');
    }
};
