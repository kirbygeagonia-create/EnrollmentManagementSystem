<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A copy issued could name an enrollment, and often could not.
 *
 * `documentprintlog` had one subject column — `enrollmentId` — so three documents that
 * are not about an enrollment at all had nowhere to point: a clearance slip belongs to a
 * student in a period (and 19 of the 27 slips on file were printed for students with no
 * enrollment in their own period's term, so they were written with NULL), and a block
 * schedule belongs to a block (the code logged it to whichever enrollment happened to be
 * first in the block, which made a roster look like one student's document). NULL is not
 * a group: MySQL treats distinct NULLs as unrelated, so the unique issue index could not
 * reject a repeat and two students' slips could carry the same document number (§25.10).
 *
 * The table now carries all three keys and a row fills whichever ones the document
 * actually covers. Rows already on file are NOT repaired: the 21 with no enrollment were
 * issued by a desk that recorded no subject, and inventing a student or a block for them
 * would forge the trail rather than fix it. The one backfill made here is mechanical — a
 * row that does name an enrollment also names that enrollment's student.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('documentprintlog', 'studentId')) {
            Schema::table('documentprintlog', function (Blueprint $table) {
                $table->integer('studentId')->nullable()->after('enrollmentId');
                $table->index(['studentId'], 'fk_documentprintlog_studentid');
                $table->foreign('studentId')->references('studentId')->on('students')->onDelete('set null')->onUpdate('cascade');
            });
        }

        if (! Schema::hasColumn('documentprintlog', 'blockId')) {
            Schema::table('documentprintlog', function (Blueprint $table) {
                $table->integer('blockId')->nullable()->after('studentId');
                $table->index(['blockId'], 'fk_documentprintlog_blockid');
                $table->foreign('blockId')->references('blockId')->on('blocks')->onDelete('set null')->onUpdate('cascade');
            });
        }

        $this->attributeRowsThatAlreadyNameAnEnrollment();
    }

    public function down(): void
    {
        Schema::table('documentprintlog', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                if (Schema::hasColumn('documentprintlog', 'blockId')) {
                    $table->dropForeign(['blockId']);
                }
                if (Schema::hasColumn('documentprintlog', 'studentId')) {
                    $table->dropForeign(['studentId']);
                }
            }

            foreach (['blockId', 'studentId'] as $column) {
                if (Schema::hasColumn('documentprintlog', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Copy the student onto rows that carry an enrollment, leaving the unattributable rows
     * NULL. A block schedule is deliberately skipped: its `enrollmentId` was a stand-in for
     * the whole block, so stamping that one student on the roster would repeat the mistake
     * this migration exists to retire.
     */
    private function attributeRowsThatAlreadyNameAnEnrollment(): void
    {
        DB::table('documentprintlog')
            ->whereNull('studentId')
            ->whereNotNull('enrollmentId')
            ->where('documentType', '!=', 'blockSchedule')
            ->chunkById(500, function ($rows) {
                $studentsByEnrollment = DB::table('enrollments')
                    ->whereIn('enrollmentId', $rows->pluck('enrollmentId')->all())
                    ->pluck('studentId', 'enrollmentId');

                foreach ($rows as $row) {
                    $studentId = $studentsByEnrollment[$row->enrollmentId] ?? null;

                    if ($studentId !== null) {
                        DB::table('documentprintlog')
                            ->where('printLogId', $row->printLogId)
                            ->update(['studentId' => $studentId]);
                    }
                }
            }, 'printLogId');
    }
};
