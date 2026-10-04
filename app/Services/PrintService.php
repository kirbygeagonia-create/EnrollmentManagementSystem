<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\EnrolledSubjectStatus;
use App\Models\Blocks;
use App\Models\Documentprintlog;
use App\Models\Enrolledsubjects;
use App\Models\Enrollments;
use App\Models\Studentclearances;
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
        // the issuance that produced it. A slip is drawn by a student, so the
        // student is the key — an enrollment may not exist for the period yet.
        $printLog = $this->recordIssue(
            $clearance->termEnrollment()?->enrollmentId,
            DocumentType::ClearanceSlip,
            $printedBy,
            $clearance->studentId
        );

        $path = $this->generatePdf('prints.clearance-slip', [
            'clearance' => $clearance->load(['student.enrollments.course', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'approvals.approvedByUser', 'receivedByUser']),
            'documentNumber' => $printLog->documentNumber,
        ], $filename);

        return new PrintedDocument($printLog, $path);
    }

    /**
     * Record one issuance of a document — a window print and a PDF download are the
     * same event to the trail. The number is one above the highest already issued for
     * that document under the subject it was issued to, so "Certificate #3" is the third
     * copy ever issued against that enrollment and "Slip #2" is the second slip this
     * student has been handed.
     *
     * Which column identifies the subject depends on the document: a clearance slip is
     * drawn by a student in a period and may exist before any enrollment does, a block
     * schedule covers a block rather than the student whose row happened to be first,
     * and everything else is issued against one enrollment. Logging each to its own
     * subject is what makes the count mean "copies of this paper", and the enrollment-
     * scoped groups are additionally unique by database index
     * (`uq_documentprintlog_issue`); the student- and block-scoped groups are held to
     * their sequence by the row lock below, since MySQL compares NULL keys as unrelated.
     *
     * A number already printed by an unattributable row is skipped. Those rows (the 21
     * on live `ems`, kept untouched by ruling 6) carry no key, so no scope counts them
     * and no index can compare against them — without the skip, a fresh student's second
     * slip would be issued the same number as a historical one and the ledger would hold
     * the same document number twice. The skip is the only half of that collision this
     * can fix without rewriting history: copies already printed keep the number they were
     * issued with.
     */
    public function recordIssue(
        ?int $enrollmentId,
        DocumentType $documentType,
        int $printedBy,
        ?int $studentId = null,
        ?int $blockId = null
    ): Documentprintlog {
        [$scopeColumn, $scopeValue] = $this->issueScope($documentType, $enrollmentId, $studentId, $blockId);

        // The next number comes from a count of the rows already on file, so two desks
        // printing the same document at the same instant would both read the same
        // count and the unique index would reject the second insert. Locking the
        // group's rows for the length of the write makes read-then-insert one step.
        return DB::transaction(function () use ($enrollmentId, $studentId, $blockId, $documentType, $printedBy, $scopeColumn, $scopeValue): Documentprintlog {
            $issue = Documentprintlog::where('documentType', $documentType);

            if ($scopeValue === null) {
                $issue->whereNull($scopeColumn);
            } else {
                $issue->where($scopeColumn, $scopeValue);
            }

            // The scope's own highest number, not its row count: a group that has already
            // been pushed past a reserved number must keep climbing from where it is,
            // or the second copy would be issued the same number as the first.
            $number = ((int) $issue->lockForUpdate()->max('documentNumber')) + 1;

            $reserved = Documentprintlog::where('documentType', $documentType)
                ->whereNull('enrollmentId')
                ->whereNull('studentId')
                ->whereNull('blockId')
                ->pluck('documentNumber')
                ->map(fn ($n) => (int) $n)
                ->all();

            while (in_array($number, $reserved, true)) {
                $number++;
            }

            return Documentprintlog::create([
                'enrollmentId' => $enrollmentId,
                'studentId' => $studentId,
                'blockId' => $blockId,
                'documentType' => $documentType,
                'printedDate' => now(),
                'printedBy' => $printedBy,
                'documentNumber' => $number,
            ]);
        });
    }

    /**
     * The column a document's copies are counted against, and the value it takes there.
     *
     * A slip or roster whose own key was not supplied is counted against the enrollment
     * it does name: falling back to the enrollment is a count of a real subject, while
     * counting the key-less rows would silently continue the pile of unattributable
     * issuance rows this exists to retire.
     *
     * @return array{0: string, 1: int|null}
     */
    private function issueScope(
        DocumentType $documentType,
        ?int $enrollmentId,
        ?int $studentId,
        ?int $blockId
    ): array {
        return match ($documentType) {
            DocumentType::ClearanceSlip => $studentId !== null
                ? ['studentId', $studentId]
                : ['enrollmentId', $enrollmentId],
            DocumentType::BlockSchedule => $blockId !== null
                ? ['blockId', $blockId]
                : ['enrollmentId', $enrollmentId],
            default => ['enrollmentId', $enrollmentId],
        };
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
        $printLog = $this->recordIssue($enrollment->enrollmentId, DocumentType::Certificate, $printedBy, $enrollment->studentId);

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
            $this->recordIssue($enrollment->enrollmentId, DocumentType::ClassCard, $printedBy, $enrollment->studentId),
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
            $this->recordIssue($enrollment->enrollmentId, DocumentType::SubjectLoad, $printedBy, $enrollment->studentId),
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

        // A roster is one document covering one block. It used to be logged against the
        // first enrollment in that block, which numbered a block's roster as if it were
        // one student's paper and left it with no enrollment at all when the block was
        // still empty.
        return new PrintedDocument(
            $this->recordIssue(null, DocumentType::BlockSchedule, $printedBy, null, $block->blockId),
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
            $this->recordIssue($enrollment->enrollmentId, DocumentType::EnrollmentForm, $printedBy, $enrollment->studentId),
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
