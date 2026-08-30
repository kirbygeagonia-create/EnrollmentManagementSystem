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

        $this->dropForeignKeysReferencing(
            ['creditedsubjects', 'curriculumsubjects', 'enrolledsubjects', 'schedules'],
            ['subjects', 'transferacademicrecords']
        );

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

        $this->dropForeignKeysReferencing(
            ['creditedsubjects', 'curriculumsubjects', 'enrolledsubjects', 'schedules'],
            ['subjects', 'transferacademicrecords']
        );

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

    /**
     * Drop foreign keys that reference the given tables, using their actual
     * constraint names so this runs identically on fresh (Laravel-generated
     * `_foreign`) and legacy (hand-authored `fk_...`) schemas. Only the FKs
     * touching the columns this migration modifies are dropped; every other
     * FK is left untouched.
     */
    private function dropForeignKeysReferencing(array $tables, array $referencedTables): void
    {
        $database = DB::connection()->getDatabaseName();
        $placeholders = implode(', ', array_fill(0, count($referencedTables), '?'));

        foreach ($tables as $table) {
            $constraints = DB::select(
                "SELECT constraint_name AS constraint_name
                 FROM information_schema.key_column_usage
                 WHERE constraint_schema = ? AND table_name = ?
                   AND referenced_table_name IN ({$placeholders})
                 GROUP BY constraint_name,
                          constraint_schema,
                          referenced_table_name",
                array_merge([$database, $table], $referencedTables)
            );

            foreach ($constraints as $row) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$row->constraint_name}`");
            }
        }
    }
};
