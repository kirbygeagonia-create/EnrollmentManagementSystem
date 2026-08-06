<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These columns are NOT NULL in the schema but the controllers intentionally
     * write NULL values:
     *  - educationalinstitutions.cityMunicipality/province: created on-the-fly
     *    from credit-transfer payloads that only carry name/type
     *  - idrequests.cardPhotoPath/producedByVendor and
     *    studentids.securityPhotoPath: optional (photo uploaded later)
     *
     * Uses the Schema builder so the change is portable across MySQL and
     * SQLite (test suite), instead of MySQL-only raw MODIFY DDL.
     */
    public function up(): void
    {
        Schema::table('educationalinstitutions', function (Blueprint $table) {
            $table->string('cityMunicipality', 150)->nullable()->change();
            $table->string('province', 150)->nullable()->change();
        });
        Schema::table('idrequests', function (Blueprint $table) {
            $table->string('cardPhotoPath', 500)->nullable()->change();
            $table->string('producedByVendor', 255)->nullable()->change();
        });
        Schema::table('studentids', function (Blueprint $table) {
            $table->string('securityPhotoPath', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('educationalinstitutions', function (Blueprint $table) {
            $table->string('cityMunicipality', 150)->nullable(false)->change();
            $table->string('province', 150)->nullable(false)->change();
        });
        Schema::table('idrequests', function (Blueprint $table) {
            $table->string('cardPhotoPath', 500)->nullable(false)->change();
            $table->string('producedByVendor', 255)->nullable(false)->change();
        });
        Schema::table('studentids', function (Blueprint $table) {
            $table->string('securityPhotoPath', 500)->nullable(false)->change();
        });
    }
};
