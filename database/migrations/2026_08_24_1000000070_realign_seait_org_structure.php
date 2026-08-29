<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modify staffusers table: make officeId nullable, add 'instructor' to role enum
        DB::statement("ALTER TABLE `staffusers` MODIFY COLUMN `officeId` INT NULL");
        DB::statement("ALTER TABLE `staffusers` MODIFY COLUMN `role` ENUM('staff', 'officeHead', 'dean', 'programHead', 'admin', 'instructor') NOT NULL DEFAULT 'staff'");

        // 2. Reassign staff users with officeId IN (17, 18, 19, 20, 21) before deleting those offices
        // Deans & Program heads get officeId = NULL and valid unitId
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL WHERE `role` IN ('dean', 'programHead')");
        
        // Specific reassignments for staff previously on offices 17-21
        // staff17 (Pilar Castillo) -> Admin, assign to office 1 (Registrar)
        DB::statement("UPDATE `staffusers` SET `officeId` = 1, `unitId` = NULL WHERE `userId` = 17");
        // staff18 (Shiela Dimagiba) -> Dean CBGG (unit 3)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 3 WHERE `userId` = 18");
        // staff19 (Lourdes Cortez) -> Program Head CICT (unit 4)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 4 WHERE `userId` = 19");
        // staff20 (Vincent Romero) -> Dean DCE (unit 5)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 5 WHERE `userId` = 20");
        // staff21 (Gina dela Cruz) -> Instructor CAF (unit 1)
        DB::statement("UPDATE `staffusers` SET `role` = 'instructor', `officeId` = NULL, `unitId` = 1 WHERE `userId` = 21");
        // staff38 (Roderick Fernandez) -> Admin, assign to office 5 (HR)
        DB::statement("UPDATE `staffusers` SET `officeId` = 5 WHERE `userId` = 38");
        // staff39 (Jose Agustin) -> Instructor CTE (unit 6)
        DB::statement("UPDATE `staffusers` SET `role` = 'instructor', `officeId` = NULL, `unitId` = 6 WHERE `userId` = 39");
        // staff40 (Henry Lansangan) -> Instructor CBGG (unit 3)
        DB::statement("UPDATE `staffusers` SET `role` = 'instructor', `officeId` = NULL, `unitId` = 3 WHERE `userId` = 40");

        // Catch-all for any other staff on offices 17-21
        DB::statement("UPDATE `staffusers` SET `officeId` = 1 WHERE `officeId` IN (17, 18, 19, 20, 21)");

        // 3. Remove clearance approvals & requirements for offices 17-21
        DB::statement("DELETE FROM `clearanceapprovals` WHERE `clearanceRequirementId` IN (SELECT `clearanceRequirementId` FROM `clearancerequirements` WHERE `officeId` IN (17, 18, 19, 20, 21))");
        DB::statement("DELETE FROM `clearancerequirements` WHERE `officeId` IN (17, 18, 19, 20, 21)");

        // 4. Remove workflow steps for offices 17-21 (if any)
        DB::statement("DELETE FROM `workflowsteps` WHERE `officeId` IN (17, 18, 19, 20, 21)");

        // 5. Delete mock offices 17-21
        DB::statement("DELETE FROM `offices` WHERE `officeId` IN (17, 18, 19, 20, 21)");

        // 6. Enforce strict Deans and Program Heads mapping to College units (1-6)
        // Unit 1 (CAF): Dean = staff23 (Rosario Domingo), PH = staff3 (Ivan Fernandez)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 1 WHERE `userId` IN (3, 23)");
        // Unit 2 (CCJE): Dean = staff7 (Nestor Cortez), PH = staff6 (John Gomez)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 2 WHERE `userId` IN (6, 7)");
        // Unit 3 (CBGG): Dean = staff18 (Shiela Dimagiba), PH = staff4 (Rodolfo Pascual)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 3 WHERE `userId` IN (4, 18)");
        // Unit 4 (CICT): Dean = staff1 (Cynthia Recto), PH = staff19 (Lourdes Cortez)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 4 WHERE `userId` IN (1, 19)");
        // Unit 5 (DCE / Engineering): Dean = staff20 (Vincent Romero), PH = staff13 (Lorenzo Araneta)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 5 WHERE `userId` IN (13, 20)");
        // Unit 6 (CTE): Dean = staff36 (Amelia Agustin), PH = staff22 (Andres Manuel)
        DB::statement("UPDATE `staffusers` SET `officeId` = NULL, `unitId` = 6 WHERE `userId` IN (22, 36)");
    }

    public function down(): void
    {
        // Re-insert offices if rolled back
        DB::statement("INSERT IGNORE INTO `offices` (`officeId`, `officeName`) VALUES 
            (17, 'Business Administration Department'),
            (18, 'BS Criminology Dean'),
            (19, 'Tourism and Hospitality Management Department'),
            (20, 'CTE Filipino'),
            (21, 'College of Agriculture and Fisheries')
        ");
    }
};
