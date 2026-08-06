<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * These integer PKs were created without AUTO_INCREMENT, so every Eloquent
     * insert that omits the PK fails with SQLSTATE 1364 ("Field ... doesn't
     * have a default value"). Existing rows are untouched; the counter starts
     * at max+1. FKs referencing the altered PKs are dropped and re-created.
     */
    public function up(): void
    {
        // Raw FK/AUTO_INCREMENT DDL is MySQL-specific; SQLite (test suite)
        // does not support DROP FOREIGN KEY / MODIFY ... AUTO_INCREMENT.
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $subjectFks = [
            'fk_creditedsubjects_creditedtosubjectid' => 'creditedsubjects',
            'fk_curriculumsubjects_prerequisitesubjectid' => 'curriculumsubjects',
            'fk_curriculumsubjects_subjectid' => 'curriculumsubjects',
            'fk_enrolledsubjects_subjectid' => 'enrolledsubjects',
            'fk_schedules_subjectid' => 'schedules',
        ];
        $transferFks = [
            'fk_creditedsubjects_transferrecordid' => 'creditedsubjects',
        ];

        foreach ($subjectFks as $fk => $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }
        foreach ($transferFks as $fk => $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }

        DB::statement('ALTER TABLE `workflowsteps` MODIFY `workflowStepId` INT NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `transferacademicrecords` MODIFY `transferRecordId` INT NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `studentscholarships` MODIFY `studentScholarshipId` INT NOT NULL AUTO_INCREMENT');
        DB::statement('ALTER TABLE `subjects` MODIFY `subjectId` INT NOT NULL AUTO_INCREMENT');

        DB::statement('ALTER TABLE `creditedsubjects` ADD CONSTRAINT `fk_creditedsubjects_creditedtosubjectid` FOREIGN KEY (`creditedToSubjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `curriculumsubjects` ADD CONSTRAINT `fk_curriculumsubjects_prerequisitesubjectid` FOREIGN KEY (`prerequisiteSubjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `curriculumsubjects` ADD CONSTRAINT `fk_curriculumsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `enrolledsubjects` ADD CONSTRAINT `fk_enrolledsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `schedules` ADD CONSTRAINT `fk_schedules_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `creditedsubjects` ADD CONSTRAINT `fk_creditedsubjects_transferrecordid` FOREIGN KEY (`transferRecordId`) REFERENCES `transferacademicrecords` (`transferRecordId`)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $subjectFks = [
            'fk_creditedsubjects_creditedtosubjectid' => 'creditedsubjects',
            'fk_curriculumsubjects_prerequisitesubjectid' => 'curriculumsubjects',
            'fk_curriculumsubjects_subjectid' => 'curriculumsubjects',
            'fk_enrolledsubjects_subjectid' => 'enrolledsubjects',
            'fk_schedules_subjectid' => 'schedules',
        ];
        $transferFks = [
            'fk_creditedsubjects_transferrecordid' => 'creditedsubjects',
        ];

        foreach ($subjectFks as $fk => $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }
        foreach ($transferFks as $fk => $table) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$fk}`");
        }

        DB::statement('ALTER TABLE `workflowsteps` MODIFY `workflowStepId` INT NOT NULL');
        DB::statement('ALTER TABLE `transferacademicrecords` MODIFY `transferRecordId` INT NOT NULL');
        DB::statement('ALTER TABLE `studentscholarships` MODIFY `studentScholarshipId` INT NOT NULL');
        DB::statement('ALTER TABLE `subjects` MODIFY `subjectId` INT NOT NULL');

        DB::statement('ALTER TABLE `creditedsubjects` ADD CONSTRAINT `fk_creditedsubjects_creditedtosubjectid` FOREIGN KEY (`creditedToSubjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `curriculumsubjects` ADD CONSTRAINT `fk_curriculumsubjects_prerequisitesubjectid` FOREIGN KEY (`prerequisiteSubjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `curriculumsubjects` ADD CONSTRAINT `fk_curriculumsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `enrolledsubjects` ADD CONSTRAINT `fk_enrolledsubjects_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `schedules` ADD CONSTRAINT `fk_schedules_subjectid` FOREIGN KEY (`subjectId`) REFERENCES `subjects` (`subjectId`)');
        DB::statement('ALTER TABLE `creditedsubjects` ADD CONSTRAINT `fk_creditedsubjects_transferrecordid` FOREIGN KEY (`transferRecordId`) REFERENCES `transferacademicrecords` (`transferRecordId`)');
    }
};
