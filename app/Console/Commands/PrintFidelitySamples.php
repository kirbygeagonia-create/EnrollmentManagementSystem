<?php

namespace App\Console\Commands;

use App\Models\Blocks;
use App\Models\Enrollments;
use App\Models\Studentclearances;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

/**
 * Stage 4 print-fidelity scaffold: renders every print template (clearance
 * slip, enrollment form, enrollment certificate, class card, subject load,
 * block & schedule) as a PDF from real database data into
 * storage/app/prints/fidelity/ so they can be visually compared against the
 * reference images in EnrollmentSystem/Documentation/Images/.
 *
 * Read-only: does NOT write Documentprintlog rows.
 */
class PrintFidelitySamples extends Command
{
    protected $signature = 'ems:print-fidelity {--enrollment= : Specific enrollment ID to use}';

    protected $description = 'Render sample PDFs of all print templates for fidelity comparison';

    public function handle(): int
    {
        $chromeCandidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        ];

        $chrome = null;
        foreach ($chromeCandidates as $candidate) {
            if (file_exists($candidate)) {
                $chrome = $candidate;
                break;
            }
        }

        if (! $chrome) {
            $this->error('Chrome/Edge not found — Browsershot cannot render PDFs.');

            return self::FAILURE;
        }

        $outDir = storage_path('app/prints/fidelity');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $enrollment = null;
        if ($this->option('enrollment')) {
            $enrollment = Enrollments::with([
                'student.addresses', 'student.guardians',
                'course', 'major', 'term.academicYear',
                'studentassessments.charges.feeType',
                'enrolledSubjects.subject',
                'evaluatedByUser', 'registrarProcessedByUser',
            ])->find($this->option('enrollment'));
        }

        if (! $enrollment) {
            $enrollment = Enrollments::with([
                'student.addresses', 'student.guardians',
                'course', 'major', 'term.academicYear',
                'studentassessments.charges.feeType',
                'enrolledSubjects.subject',
                'evaluatedByUser', 'registrarProcessedByUser',
            ])->whereHas('enrolledSubjects')->latest('enrollmentId')->first();
        }

        if (! $enrollment) {
            $this->error('No enrollment with subjects found. Seed some data first (e.g. run the E2E walkthrough tests).');

            return self::FAILURE;
        }

        $this->info("Using enrollment #{$enrollment->enrollmentId} ({$enrollment->student?->lastName}, {$enrollment->enrolledSubjects->count()} subjects)");

        $templates = [
            'enrollment-form' => fn () => view('prints.enrollment-form', ['enrollment' => $enrollment->load(['student.addresses', 'student.guardians', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'evaluatedByUser', 'registrarProcessedByUser'])]),
            'enrollment-certificate' => fn () => view('prints.enrollment-certificate', ['enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'registrarProcessedByUser'])]),
            'subject-load' => fn () => view('prints.enrollment-certificate', ['enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'enrolledSubjects.subject', 'registrarProcessedByUser'])]),
        ];

        // Class cards: one per subject of the chosen enrollment
        foreach ($enrollment->enrolledSubjects as $i => $es) {
            $subject = $es->subject;
            $templates["class-card-{$i}"] = fn () => view('prints.class-card', [
                'enrollment' => $enrollment->load(['student', 'course', 'major', 'term.academicYear', 'registrarProcessedByUser']),
                'subject' => $subject,
                'schedule' => $es->load(['schedule.room', 'schedule.instructor', 'schedule.meetings'])->schedule,
            ]);
        }

        // Block schedule: first block with schedules if any
        $block = Blocks::with(['course', 'term.academicYear', 'schedules.subject', 'schedules.room', 'schedules.instructor', 'schedules.meetings'])
            ->whereHas('schedules')->first();
        if ($block) {
            $templates['block-schedule'] = fn () => view('prints.block-schedule', ['block' => $block]);
        } else {
            $this->warn('No block with schedules found — skipping block-schedule template.');
        }

        // Clearance slip: most recent approved clearance if any
        $clearance = Studentclearances::with(['student', 'clearancePeriod.term.academicYear', 'approvals.requirement.office', 'approvals.approvedByUser', 'receivedByUser'])
            ->latest('studentClearanceId')->first();
        if ($clearance) {
            $templates['clearance-slip'] = fn () => view('prints.clearance-slip', ['clearance' => $clearance]);
        } else {
            $this->warn('No clearance record found — skipping clearance-slip template.');
        }

        $rendered = 0;
        foreach ($templates as $name => $viewClosure) {
            $file = "{$outDir}/{$name}.pdf";
            try {
                $html = $viewClosure()->render();
                Browsershot::html($html)
                    ->setChromePath($chrome)
                    ->setOption('landscape', str_starts_with($name, 'class-card') || $name === 'block-schedule')
                    ->setOption('format', 'A4')
                    ->setOption('margin', ['top' => '15mm', 'right' => '15mm', 'bottom' => '15mm', 'left' => '15mm'])
                    ->save($file);
                $this->info("  rendered {$name}.pdf");
                $rendered++;
            } catch (\Throwable $e) {
                Log::error("Print fidelity failed for {$name}", ['error' => $e->getMessage()]);
                $this->error("  FAILED {$name}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info("Done: {$rendered}/".count($templates).' rendered in '.str_replace(base_path(), '.', $outDir));
        $this->line('Compare against: EnrollmentSystem/Documentation/Images/ (Clearance Slip, Class Card, Subject Load, Class Block and Schedule).');

        return self::SUCCESS;
    }
}
