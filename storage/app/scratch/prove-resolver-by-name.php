<?php
/**
 * Does desk_signer_for() resolve the OWNING desk, or the number it was handed?
 *
 * The rehearsal proved the pipeline runs and the self-check fires. This proves the semantics of the
 * replacement, which the copied dataset cannot: it still carries the two boxes the old literal wrote.
 * Asserted for every office that owns a box, against the scratch copy, reading live config-free.
 */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

require $root.'/tools/seed_full_demo_pipeline_functions.php';
