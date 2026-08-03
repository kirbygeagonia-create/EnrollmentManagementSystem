<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\Blocks;
use App\Models\Documentprintlog;
use App\Models\Enrollments;
use App\Models\Studentclearances;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class PrintService
{
    /**
     * Generate PDF from Blade template using Browsershot.
     */
    public function generatePdf(string $template, array $data, string $filename): string
    {
        $html = view($template, $data)->render();

        $path = storage_path("app/prints/{$filename}");

        // Ensure directory exists
        if (! file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        Browsershot::html($html)
            ->setOption('landscape', false)
            ->setOption('format', 'A4')
            ->setOption('margin', [
                'top' => '15mm',
                'right' => '15mm',
                'bottom' => '15mm',
                'left' => '15mm',
            ])
            ->save($path);

        return $path;
    }

    /**
     * Print clearance slip.
     */
    public function printClearanceSlip(Studentclearances $clearance, int $printedBy): Documentprintlog
    {
        $filename = "clearance-slip-{$clearance->studentClearanceId}-".now()->format('YmdHis').'.pdf';

        $this->generatePdf('prints.clearance-slip', [
            'clearance' => $clearance->load(['student', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'receivedByUser']),
        ], $filename);

        $printLog = Documentprintlog::create([
            'enrollmentId' => $clearance->student->enrollments->first()?->enrollmentId,
            'documentType' => DocumentType::ClearanceSlip,
            'printedDate' => now(),
            'printedBy' => $printedBy,
            'documentNumber' => Documentprintlog::where('documentType', DocumentType::ClearanceSlip)
                ->where('enrollmentId', $clearance->student->enrollments->first()?->enrollmentId)
                ->count() + 1,
        ]);

        return $printLog;
    }

    /**
     * Print enrollment certificate.
     */
    public function printEnrollmentCertificate(Enrollments $enrollment, int $printedBy): Documentprintlog
    {
        $filename = "certificate-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        $this->generatePdf('prints.enrollment-certificate', [
            'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'registrarProcessedByUser']),
        ], $filename);

        $printLog = Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::Certificate,
            'printedDate' => now(),
            'printedBy' => $printedBy,
            'documentNumber' => Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
                ->where('documentType', DocumentType::Certificate)
                ->count() + 1,
        ]);

        return $printLog;
    }

    /**
     * Print class cards (one per subject).
     */
    public function printClassCards(Enrollments $enrollment, int $printedBy): array
    {
        $printLogs = [];

        foreach ($enrollment->enrolledSubjects as $index => $es) {
            $filename = "class-card-{$enrollment->enrollmentId}-{$es->subjectId}-".now()->format('YmdHis').'.pdf';

            $this->generatePdf('prints.class-card', [
                'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'registrarProcessedByUser']),
                'subject' => $es->subject->load(['schedule.room', 'schedule.instructor', 'schedule.meetings']),
            ], $filename);

            $printLogs[] = Documentprintlog::create([
                'enrollmentId' => $enrollment->enrollmentId,
                'documentType' => DocumentType::ClassCard,
                'printedDate' => now(),
                'printedBy' => $printedBy,
                'documentNumber' => $index + 1,
            ]);
        }

        return $printLogs;
    }

    /**
     * Print subject load.
     */
    public function printSubjectLoad(Enrollments $enrollment, int $printedBy): Documentprintlog
    {
        $filename = "subject-load-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        $this->generatePdf('prints.enrollment-certificate', [
            'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'registrarProcessedByUser']),
        ], $filename);

        $printLog = Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::SubjectLoad,
            'printedDate' => now(),
            'printedBy' => $printedBy,
            'documentNumber' => Documentprintlog::where('enrollmentId', $enrollment->enrollmentId)
                ->where('documentType', DocumentType::SubjectLoad)
                ->count() + 1,
        ]);

        return $printLog;
    }

    /**
     * Print block & schedule.
     */
    public function printBlockSchedule(Blocks $block, int $printedBy): Documentprintlog
    {
        $filename = "block-schedule-{$block->blockId}-".now()->format('YmdHis').'.pdf';

        $this->generatePdf('prints.block-schedule', [
            'block' => $block->load(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings']),
        ], $filename);

        // Log to the first enrollment in this block
        $enrollmentId = $block->enrolledSubjects->first()?->enrollmentId;

        $printLog = Documentprintlog::create([
            'enrollmentId' => $enrollmentId,
            'documentType' => DocumentType::BlockSchedule,
            'printedDate' => now(),
            'printedBy' => $printedBy,
            'documentNumber' => 1,
        ]);

        return $printLog;
    }

    /**
     * Print enrollment form.
     */
    public function printEnrollmentForm(Enrollments $enrollment, int $printedBy): Documentprintlog
    {
        $filename = "enrollment-form-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        $this->generatePdf('prints.enrollment-form', [
            'enrollment' => $enrollment->load([
                'student.addresses',
                'student.guardians',
                'course',
                'major',
                'term.academicYear',
                'enrolledSubjects.subject',
                'evaluatedByUser',
                'registrarProcessedByUser',
            ]),
        ], $filename);

        $printLog = Documentprintlog::create([
            'enrollmentId' => $enrollment->enrollmentId,
            'documentType' => DocumentType::EnrollmentForm,
            'printedDate' => now(),
            'printedBy' => $printedBy,
            'documentNumber' => 1,
        ]);

        return $printLog;
    }

    /**
     * Batch print class cards for a block.
     */
    public function batchPrintClassCards(Blocks $block, int $printedBy): array
    {
        $printLogs = [];

        foreach ($block->enrolledSubjects as $es) {
            $enrollment = $es->enrollment;
            if ($enrollment->enrollmentStatus->value !== 'enrolled') {
                continue;
            }

            $logs = $this->printClassCards($enrollment, $printedBy);
            $printLogs = array_merge($printLogs, $logs);
        }

        return $printLogs;
    }
}
