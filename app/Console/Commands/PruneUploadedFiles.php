<?php

namespace App\Console\Commands;

use App\Models\Documents;
use App\Models\Idrequests;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Report, and optionally clear, files on the private documents disk that no record points
 * at. Two things produce them: a desk retaking a face photo (the route now removes the one
 * it replaced, but anything older than that rule is still there) and a test or an import
 * that wrote onto the real disk instead of a fake one. The command exists because the folder
 * holds signed forms and student photographs, so "is anything unreferenced in there" has to
 * be answerable without anyone browsing it by hand — and because deleting files is not
 * something a report tool should do unless it is told to.
 */
class PruneUploadedFiles extends Command
{
    /**
     * The only folders this command may look at. The disk's root is shared with a
     * .gitignore that keeps the whole folder out of the repository, so scanning
     * everything on the disk would offer to delete the file that protects it.
     */
    private const FOLDERS = ['id-photos', 'admission-documents'];

    protected $signature = 'ems:uploads:prune
                            {--delete : remove the unreferenced files; without this flag nothing is touched}';

    protected $description = 'Report uploaded files no record references, and remove them only with --delete';

    public function handle(): int
    {
        $disk = Storage::disk('documents');

        $referenced = Idrequests::whereNotNull('cardPhotoPath')->pluck('cardPhotoPath')
            ->merge(Documents::whereNotNull('fileUrl')->pluck('fileUrl'))
            ->map(fn (string $path): string => trim($path, '/'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $onDisk = [];

        foreach (self::FOLDERS as $folder) {
            foreach ($disk->allFiles($folder) as $path) {
                $onDisk[] = $path;
            }
        }

        $orphans = array_values(array_diff($onDisk, $referenced));
        $missing = array_values(array_diff($referenced, $onDisk));
        $kept = count($onDisk) - count($orphans);

        $this->info('Files on the documents disk: '.count($onDisk)." ({$kept} referenced, ".count($orphans).' unreferenced)');
        $this->info('Records pointing at a file that is not on disk: '.count($missing));

        foreach ($missing as $path) {
            $this->line('  missing: '.$path);
        }

        if ($orphans === []) {
            $this->info('Nothing to prune.');

            return self::SUCCESS;
        }

        foreach ($orphans as $path) {
            $this->line('  unreferenced: '.$path.' ('.$disk->size($path).' bytes)');
        }

        if (! $this->option('delete')) {
            $this->warn('Dry run — pass --delete to remove these '.count($orphans).' file(s).');

            return self::SUCCESS;
        }

        $removed = 0;

        foreach ($orphans as $path) {
            if ($disk->delete($path)) {
                $removed++;
            } else {
                $this->error('The disk refused to remove '.$path);
            }
        }

        $this->info('Removed '.$removed.' of '.count($orphans).' unreferenced file(s).');

        return $removed === count($orphans) ? self::SUCCESS : self::FAILURE;
    }
}
