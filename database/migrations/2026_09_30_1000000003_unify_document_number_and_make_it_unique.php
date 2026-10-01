<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One issuance, one number: the print trail has to be countable.
 *
 * `documentprintlog.documentNumber` was written two different ways. Certificates,
 * slips, subject loads and block schedules stored "the Nth copy issued for this
 * enrollment", while class cards stored the card's position in the student's subject
 * list. The same enrollment therefore collected several rows numbered 1 for the same
 * document type — every time the card set was reprinted — so the column could not
 * answer the one question a print log exists for: how many copies has this student
 * been given, and is this number a duplicate or a forgery?
 *
 * The column now means issuance count everywhere, and the index makes a second row
 * with the same number impossible. Existing rows are renumbered in insertion order
 * (printLogId), which preserves the sequence in which copies actually left the desk.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `0` stands for the rows that carry no enrollment at all — a block schedule
        // printed before any student was attached to the block. Enrollment ids start
        // at 1, so the sentinel cannot collide with a real group.
        $rows = DB::table('documentprintlog')
            ->orderByRaw('COALESCE(enrollmentId, 0)')
            ->orderBy('documentType')
            ->orderBy('printLogId')
            ->get(['printLogId', 'enrollmentId', 'documentType', 'documentNumber']);

        $issued = [];

        foreach ($rows as $row) {
            $group = ($row->enrollmentId ?? 0).'|'.$row->documentType;
            $issued[$group] = ($issued[$group] ?? 0) + 1;

            if ($issued[$group] !== (int) $row->documentNumber) {
                DB::table('documentprintlog')
                    ->where('printLogId', $row->printLogId)
                    ->update(['documentNumber' => $issued[$group]]);
            }
        }

        Schema::table('documentprintlog', function (Blueprint $table) {
            $table->unique(
                ['enrollmentId', 'documentType', 'documentNumber'],
                'uq_documentprintlog_issue'
            );
        });
    }

    public function down(): void
    {
        Schema::table('documentprintlog', function (Blueprint $table) {
            $table->dropUnique('uq_documentprintlog_issue');
        });
    }
};
