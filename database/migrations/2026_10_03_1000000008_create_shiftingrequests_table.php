<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The record a program change never had (G-7, ruling 11).
 *
 * §28 records the hole plainly: "A shift appears only as a courseId different from the
 * previous term's. Nothing notifies, nothing retires the previous blockId, and no history
 * row records the change" — and §21.4 that "nothing in any history table says these two
 * enrollments are the shift". Reconstruction meant reading the course and major off each
 * enrollment and guessing.
 *
 * This table is that record. It is application-shaped, as the ruling directs, but it
 * starts at Academic Department Evaluation rather than the admissions desk: a SEAIT
 * student changing program is not an applicant, so they do not file an admission.
 *
 * Two enrollment pointers carry the whole point of the row. `currentEnrollmentId` is the
 * record whose block seat the shift retires; `grantedEnrollmentId` is the receiving
 * enrollment the grant issued. Together in one row they answer "which two enrollments are
 * the shift" without a second history table — and the three signature columns say who
 * filed it, who endorsed it, and who made the final call, which the Guidance Councillor's
 * signature is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shiftingrequests', function (Blueprint $table) {
            $table->integer('shiftingRequestId')->autoIncrement();
            $table->integer('studentId');
            $table->integer('currentCourseId');
            $table->integer('targetCourseId');
            $table->integer('currentEnrollmentId')->nullable();
            $table->integer('grantedEnrollmentId')->nullable();

            // The student's own declared will to move — the paper form this replaces had
            // the student write it, so it is stored as stated rather than as a checkbox.
            $table->text('willStatement');

            $table->enum('requestStatus', ['pending', 'endorsed', 'granted', 'rejected'])->default('pending');

            // Filed at Academic Department Evaluation (office 4 is that box in the
            // workflow vocabulary; office 7 is the academic unit's own desk).
            $table->integer('requestedBy');
            $table->dateTime('requestedAt');

            // What the receiving department proposes: the term the student enters and the
            // year level the credit evaluation lands them at. §5.1: the level is the
            // department's academic call, never a value a student type supplies.
            $table->integer('termId')->nullable();
            $table->integer('yearLevel')->nullable();
            $table->integer('departmentSignedBy')->nullable();
            $table->dateTime('departmentSignedAt')->nullable();

            // The Guidance Councillor's signature is the final call either way, and a
            // refusal without a reason is not a decision a panel can defend.
            $table->integer('decisionBy')->nullable();
            $table->dateTime('decidedAt')->nullable();
            $table->string('decisionRemarks', 500)->nullable();

            $table->timestamps();

            $table->index(['studentId'], 'fk_shiftingrequests_studentid');
            $table->index(['currentCourseId'], 'fk_shiftingrequests_currentcourseid');
            $table->index(['targetCourseId'], 'fk_shiftingrequests_targetcourseid');
            $table->index(['currentEnrollmentId'], 'fk_shiftingrequests_currentenrollmentid');
            $table->index(['grantedEnrollmentId'], 'fk_shiftingrequests_grantedenrollmentid');
            $table->index(['requestedBy'], 'fk_shiftingrequests_requestedby');
            $table->index(['departmentSignedBy'], 'fk_shiftingrequests_departmentsignedby');
            $table->index(['decisionBy'], 'fk_shiftingrequests_decisionby');
            $table->index(['requestStatus'], 'idx_shiftingrequests_status');

            $table->foreign('studentId')->references('studentId')->on('students')->onUpdate('cascade');
            $table->foreign('currentCourseId')->references('courseId')->on('courses')->onUpdate('cascade');
            $table->foreign('targetCourseId')->references('courseId')->on('courses')->onUpdate('cascade');
            $table->foreign('currentEnrollmentId')->references('enrollmentId')->on('enrollments')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('grantedEnrollmentId')->references('enrollmentId')->on('enrollments')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('termId')->references('termId')->on('academicterms')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('requestedBy')->references('userId')->on('staffusers')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('departmentSignedBy')->references('userId')->on('staffusers')->onDelete('set null')->onUpdate('cascade');
            $table->foreign('decisionBy')->references('userId')->on('staffusers')->onDelete('set null')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shiftingrequests');
    }
};
