<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests don't exercise the asset pipeline; stub Vite so views that use
        // @vite don't require a built manifest (public/build/manifest.json).
        $this->withoutVite();
    }
}
