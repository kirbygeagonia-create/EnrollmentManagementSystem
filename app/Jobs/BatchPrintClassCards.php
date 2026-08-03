<?php

namespace App\Jobs;

use App\Models\Blocks;
use App\Services\PrintService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BatchPrintClassCards implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Blocks $block,
        private int $printedBy
    ) {}

    public function handle(PrintService $printService): void
    {
        $printService->batchPrintClassCards($this->block, $this->printedBy);
    }
}
