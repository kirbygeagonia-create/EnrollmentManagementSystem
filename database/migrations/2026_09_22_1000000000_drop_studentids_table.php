<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finish the validation-only ID flow cleanup: the studentids table is pure
 * card-production machinery (QR encoding, PVC card records, vendor intake)
 * and nothing writes it any more — the ID Office is a validation desk whose
 * history lives on idrequests itself (validatedBy/validatedDate, status).
 *
 * - studentids is dropped outright; its historical reads (Student 360's
 *   "Student IDs" card, the user-delete guard) move to idrequests.
 * - producedByVendor on idrequests is dropped too — nullable and no longer
 *   written since card-making was removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('studentids');

        Schema::table('idrequests', function (Blueprint $table) {
            if (Schema::hasColumn('idrequests', 'producedByVendor')) {
                $table->dropColumn('producedByVendor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idrequests', function (Blueprint $table) {
            if (! Schema::hasColumn('idrequests', 'producedByVendor')) {
                $table->string('producedByVendor', 255)->nullable()->after('cardPhotoPath');
            }
        });

        Schema::create('studentids', function (Blueprint $table) {
            $table->increments('idId');
            $table->integer('studentId');
            $table->integer('idRequestId');
            $table->string('qrCode', 255)->unique();
            $table->date('issueDate');
            $table->enum('validationStatus', ['pendingValidation', 'active', 'lost', 'replaced'])->default('pendingValidation');
            $table->string('securityPhotoPath', 500)->nullable();
            $table->integer('validatedBy')->nullable();
            $table->dateTime('validatedDate')->nullable();
            $table->timestamps();

            $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('idRequestId')->references('idRequestId')->on('idrequests')->onDelete('cascade')->onUpdate('cascade');
        });
    }
};
