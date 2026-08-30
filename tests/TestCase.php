<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CI backend jobs (SQLite/MySQL) do not run `npm run build`, so
        // public/build/manifest.json does not exist there. Tests that render
        // Blade views with @vite would otherwise die with
        // ViteManifestNotFoundException (audit §3.1 CI green build).
        $this->withoutVite();
    }
}
