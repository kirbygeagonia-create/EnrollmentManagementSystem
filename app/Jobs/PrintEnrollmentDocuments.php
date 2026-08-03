<?php

namespace App\Jobs;

use App\Models\Enrollments;
use App\Services\PrintService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PrintEnrollmentDocuments implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Enrollments $enrollment,
        private int $printedBy,
        private array $documentTypes = ['certificate', 'classCards', 'subjectLoad']
    ) {}

    public function handle(PrintService $printService): void
    {
        foreach ($this->documentTypes as $type) {
            match ($type) {
                'certificate' => $printService->printEnrollmentCertificate($this->enrollment, $this->printedBy),
                'classCards' => $printService->printClassCards($this->enrollment, $this->printedBy),
                'subjectLoad' => $printService->printSubjectLoad($this->enrollment, $this->printedBy),
                default => null,
            };
        }
    }
}
