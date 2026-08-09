<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idrequests', function (Blueprint $table) {
            if (! Schema::hasColumn('idrequests', 'reissueReason')) {
                $table->string('reissueReason', 255)->nullable()->after('status');
            }
            if (! Schema::hasColumn('idrequests', 'is_reissue')) {
                $table->boolean('is_reissue')->default(false)->after('reissueReason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idrequests', function (Blueprint $table) {
            if (Schema::hasColumn('idrequests', 'is_reissue')) {
                $table->dropColumn('is_reissue');
            }
            if (Schema::hasColumn('idrequests', 'reissueReason')) {
                $table->dropColumn('reissueReason');
            }
        });
    }
};
