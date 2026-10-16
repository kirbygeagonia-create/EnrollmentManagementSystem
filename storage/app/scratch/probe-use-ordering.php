<?php

// Probe: does a `use` alias declared AFTER its first textual use still apply at line 1?
// tools/seed_demo_data.php does exactly this — `$app->make(Kernel::class)` on line 11,
// `use Illuminate\Contracts\Console\Kernel;` on line 20 — and it dies with
// "Class Kernel does not exist", so the answer is presumably no. Written down, not assumed.

echo 'first use of the alias : ', Kernel::class, "\n";

use Illuminate\Contracts\Console\Kernel;

echo 'second use of the alias: ', Kernel::class, "\n";
