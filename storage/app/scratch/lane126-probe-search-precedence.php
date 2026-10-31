<?php

/**
 * What SQL does the staff directory's search actually build?
 *
 * `Admin/UserManagementController::index()` chains its filters as
 *   ->when($officeId, fn ($q) => $q->where('officeId', $id))
 *   ->when($search,   fn ($q) => $q->where('firstName','like',…)->orWhere('lastName','like',…))
 * and every `where`/`orWhere` a closure adds sits at the SAME boolean level as the filters before it, because
 * `when()` does not wrap the closure's constraints in a group. So the directory's office filter and its search are
 * joined by an OR, not an AND, and `officeId=3&search=juan` can answer with staff from every office. This prints the
 * two shapes side by side — the one the handler writes, and the grouped one — from the framework's own grammar, not
 * from a reading of it, and asks live `ems` how many rows each returns.
 *
 * Read-only: it compiles and selects, it never writes.
 *
 * usage: php storage/app/scratch/lane126-probe-search-precedence.php
 */

use App\Models\Staffusers;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (array_slice($argv ?? [], 1) !== []) {
    fwrite(STDERR, 'REFUSED: this probe reads no flags; it asks the connection the application is configured to use.'."\n");

    exit(1);
}

$base = Staffusers::query();

// The discriminating pair is chosen FROM the rows, not invented: an office that holds someone, and a search term
// belonging to someone in a DIFFERENT office. If the office filter is being ORed away, the ungrouped shape answers
// that other person while the grouped shape answers nobody — a row difference, which is a measurement rather than
// a reading of the grammar.
$officeA = (clone $base)->whereNotNull('officeId')->value('officeId');
$other = (clone $base)->where('officeId', '!=', $officeA)->whereNotNull('officeId')->first();
if ($officeA === null || $other === null) {
    fwrite(STDERR, "REFUSED: the directory needs a staff row in one office and a named row in another to discriminate.\n");

    exit(1);
}
$term = substr($other->lastName, 0, 3);

printf("office filter  : officeId = %d\n", $officeA);
printf("search term    : %s (the surname of a %s, who sits in office %d)\n",
    $term, $other->lastName, $other->officeId);

// The handler's own shape, transcribed from the file it lives in.
$asWritten = (clone $base)
    ->where('officeId', $officeA)
    ->where('firstName', 'like', "%{$term}%")
    ->orWhere('lastName', 'like', "%{$term}%")
    ->orWhere('username', 'like', "%{$term}%");

// The shape that groups the search's own alternatives.
$grouped = (clone $base)
    ->where('officeId', $officeA)
    ->where(function ($q) use ($term): void {
        $q->where('firstName', 'like', "%{$term}%")
            ->orWhere('lastName', 'like', "%{$term}%")
            ->orWhere('username', 'like', "%{$term}%");
    });

printf("\nconnection : %s\n", config('database.default'));
printf("database   : %s\n", config('database.connections.'.config('database.default').'.database'));
printf("\nas written : %s\n", $asWritten->toSql());
printf("rows       : %d  [%s]\n", $asWritten->count(), implode(', ', $asWritten->pluck('username')->all()));
printf("\ngrouped    : %s\n", $grouped->toSql());
printf("rows       : %d  [%s]\n", $grouped->count(), implode(', ', $grouped->pluck('username')->all()));

$offices = (clone $base)->where('officeId', $officeA)->count();
printf("\noffice %d alone : %d\n", $officeA, $offices);
printf("\nverdict : %s\n", $asWritten->toSql() === $grouped->toSql()
    ? 'the two shapes are the same SQL — the claim is refuted'
    : sprintf('the office filter does not survive the search: filtering office %d for "%s" answers %d row(s) while the grouped shape answers %d, and that office holds %d in total',
        $officeA, $term, $asWritten->count(), $grouped->count(), $offices));

exit(0);
