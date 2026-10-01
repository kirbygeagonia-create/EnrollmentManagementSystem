<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Models\Blocks;
use App\Models\Documentprintlog;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Studentclearances;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class PrintService
{
    /**
     * Generate PDF from Blade template using Browsershot.
     */
    public function generatePdf(string $template, array $data, string $filename, bool $landscape = false): string
    {
        $html = view($template, $data)->render();

        $path = storage_path("app/prints/{$filename}");

        // Ensure directory exists
        if (! file_exists(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $browser = Browsershot::html($html);

        // Browsershot only knows where Chrome lives if the npm `puppeteer` package was
        // installed; on the school machines it is not, so point at the system browser.
        if ($chrome = ChromiumLocator::locate()) {
            $browser->setChromePath($chrome);
        }

        $browser
            ->setOption('landscape', $landscape)
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
    public function printClearanceSlip(Studentclearances $clearance, int $printedBy): PrintedDocument
    {
        $filename = "clearance-slip-{$clearance->studentClearanceId}-".now()->format('YmdHis').'.pdf';

        // The number has to exist before the page renders: it is printed on the
        // slip, and a slip with no number on its face cannot be traced back to
        // the issuance that produced it.
        $printLog = $this->recordIssue(
            $clearance->termEnrollment()?->enrollmentId,
            DocumentType::ClearanceSlip,
            $printedBy
        );

        $path = $this->generatePdf('prints.clearance-slip', [
            'clearance' => $clearance->load(['student.enrollments.course', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'approvals.approvedByUser', 'receivedByUser']),
            'documentNumber' => $printLog->documentNumber,
        ], $filename);

        return new PrintedDocument($printLog, $path);
    }

    /**
     * Record one issuance of a document — a window print and a PDF download are the
     * same event to the trail. The number is the running count of that type for the
     * enrollment, so "Certificate #3" is the third copy ever issued to that student,
     * and it is unique per enrollment and document type by database constraint.
     */
    public function recordIssue(?int $enrollmentId, DocumentType $documentType, int $printedBy): Documentprintlog
    {
        // The next number comes from a count of the rows already on file, so two desks
        // printing the same document at the same instant would both read the same
        // count and the unique index would reject the second insert. Locking the
        // group's rows for the length of the write makes read-then-insert one step.
        return DB::transaction(function () use ($enrollmentId, $documentType, $printedBy): Documentprintlog {
            $issuedBefore = Documentprintlog::where('documentType', $documentType)
                ->when(
                    $enrollmentId,
                    fn (Builder $query) => $query->where('enrollmentId', $enrollmentId),
                    fn (Builder $query) => $query->whereNull('enrollmentId')
                )
                ->lockForUpdate()
                ->count('printLogId');

            return Documentprintlog::create([
                'enrollmentId' => $enrollmentId,
                'documentType' => $documentType,
                'printedDate' => now(),
                'printedBy' => $printedBy,
                'documentNumber' => $issuedBefore + 1,
            ]);
        });
    }

    /**
     * Print enrollment certificate.
     */
    public function printEnrollmentCertificate(Enrollments $enrollment, int $printedBy): PrintedDocument
    {
        $filename = "certificate-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        // Issued before the page renders so the copy carries its own number, the same
        // way the clearance slip does: a certificate with no number on its face cannot
        // be tied back to the issuance that produced it.
        $printLog = $this->recordIssue($enrollment->enrollmentId, DocumentType::Certificate, $printedBy);

        $path = $this->generatePdf('prints.enrollment-certificate', [
            'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'registrarProcessedByUser']),
            'documentNumber' => $printLog->documentNumber,
        ], $filename);

        return new PrintedDocument($printLog, $path);
    }

    /**
     * Print class cards (one PDF per confirmed subject).
     *
     * @return array<int, PrintedDocument>
     */
    public function printClassCards(Enrollments $enrollment, int $printedBy): array
    {
        $documents = [];

        foreach ($this->cardSubjects($enrollment) as $es) {
            $documents[] = $this->printClassCard($enrollment, $es, $printedBy);
        }

        return $documents;
    }

    /**
     * The subjects a class card is issued for. Dropped and still-proposed rows are
     * not registered subjects of the term, so they get no card — this is the same
     * set the class-card screen lists.
     *
     * @return Collection<int, Enrolledsubjects>
     */
    public function cardSubjects(Enrollments $enrollment): Collection
    {
        if (! $enrollment->relationLoaded('enrolledSubjects')) {
            $enrollment->load('enrolledSubjects');
        }

        return $enrollment->enrolledSubjects
            ->filter(fn (Enrolledsubjects $es) => $es->status === EnrolledSubjectStatus::Confirmed)
            ->values();
    }

    /**
     * Print a single class card.
     *
     * The card is identified on its face by subject, schedule and student, so the log
     * number counts issuances like every other document does — "class card copy #4 of
     * this enrollment", which is what makes an over-printed student visible.
     */
    public function printClassCard(
        Enrollments $enrollment,
        Enrolledsubjects $enrolledSubject,
        int $printedBy
    ): PrintedDocument {
        $filename = "class-card-{$enrollment->enrollmentId}-{$enrolledSubject->subjectId}-".now()->format('YmdHis').'.pdf';

        $path = $this->generatePdf('prints.class-card', [
            'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'registrarProcessedByUser']),
            'subject' => $enrolledSubject->subject,
            'schedule' => $enrolledSubject->load(['schedule.room', 'schedule.instructor', 'schedule.meetings'])->schedule,
        ], $filename, true);

        return new PrintedDocument(
            $this->recordIssue($enrollment->enrollmentId, DocumentType::ClassCard, $printedBy),
            $path
        );
    }

    /**
     * Print subject load.
     */
    public function printSubjectLoad(Enrollments $enrollment, int $printedBy): PrintedDocument
    {
        $filename = "subject-load-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        $path = $this->generatePdf('prints.subject-load', [
            'enrollment' => $enrollment->load([
                'student', 'course', 'major', 'term.academicYear',
                'enrolledSubjects.subject',
                'enrolledSubjects.schedule.room',
                'enrolledSubjects.schedule.instructor',
                'enrolledSubjects.schedule.meetings',
                'registrarProcessedByUser',
            ]),
        ], $filename);

        return new PrintedDocument(
            $this->recordIssue($enrollment->enrollmentId, DocumentType::SubjectLoad, $printedBy),
            $path
        );
    }

    /**
     * Print block & schedule.
     */
    public function printBlockSchedule(Blocks $block, int $printedBy): PrintedDocument
    {
        $filename = "block-schedule-{$block->blockId}-".now()->format('YmdHis').'.pdf';

        $path = $this->generatePdf('prints.block-schedule', [
            'block' => $block->load(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings']),
        ], $filename, true);

        // Log to the first enrollment in this block
        $enrollmentId = $block->enrolledSubjects->first()?->enrollmentId;

        return new PrintedDocument(
            $this->recordIssue($enrollmentId, DocumentType::BlockSchedule, $printedBy),
            $path
        );
    }

    /**
     * Print enrollment form.
     */
    public function printEnrollmentForm(Enrollments $enrollment, int $printedBy): PrintedDocument
    {
        $filename = "enrollment-form-{$enrollment->enrollmentId}-".now()->format('YmdHis').'.pdf';

        $path = $this->generatePdf('prints.enrollment-form', [
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

        return new PrintedDocument(
            $this->recordIssue($enrollment->enrollmentId, DocumentType::EnrollmentForm, $printedBy),
            $path
        );
    }

    /**
     * Batch print class cards for a block.
     *
     * @return array<int, PrintedDocument>
     */
    public function batchPrintClassCards(Blocks $block, int $printedBy): array
    {
        $documents = [];

        foreach ($block->enrolledSubjects as $es) {
            $enrollment = $es->enrollment;
            if ($enrollment->enrollmentStatus->value !== 'enrolled') {
                continue;
            }

            $documents = array_merge($documents, $this->printClassCards($enrollment, $printedBy));
        }

        return $documents;
    }
}
