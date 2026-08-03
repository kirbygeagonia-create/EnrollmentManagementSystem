<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocks', function (Blueprint $table) {
            $table->integer('blockId')->autoIncrement();
            $table->integer('courseId');
            $table->integer('termId');
            $table->integer('yearLevel');
            $table->string('blockName', 150);
            $table->integer('maxStudents');
            $table->index(['courseId'], 'fk_sections_courseid');
            $table->index(['termId'], 'fk_sections_termid');
            $table->foreign(['courseId'])->references(['courseId'])->on('courses')->onUpdate('cascade');
            $table->foreign(['termId'])->references(['termId'])->on('academicterms')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocks');
    }
};
