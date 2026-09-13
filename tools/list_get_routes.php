<?php
// Temporary: dump all named GET routes as "name<TAB>uri" lines.
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (app('router')->getRoutes()->getRoutes() as $r) {
    if (! in_array('GET', (array) $r->methods())) {
        continue;
    }
    if (! $r->getName()) {
        continue;
    }
    echo $r->getName(), "\t", $r->uri(), "\n";
}
