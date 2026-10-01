<?php

namespace App\Services;

use App\Models\Documentprintlog;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A rendered document PDF paired with the print-log row that records its
 * issuance, so a desk that downloads a file cannot skip the audit trail.
 */
class PrintedDocument
{
    public function __construct(
        public readonly Documentprintlog $log,
        public readonly string $path,
    ) {}

    /**
     * Stream the PDF as an attachment and remove it from disk once sent —
     * generated documents are transient, the print log is the record.
     */
    public function asDownload(?string $filename = null): BinaryFileResponse
    {
        $name = $filename ?? basename($this->path);

        // The name comes from data-entry fields (block names, ID numbers); a quote
        // or newline there would corrupt the Content-Disposition header.
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? $name;

        return response()
            ->download($this->path, $safe)
            ->deleteFileAfterSend();
    }
}
