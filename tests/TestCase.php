<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\ConfiguresLicense;

abstract class TestCase extends BaseTestCase
{
    use ConfiguresLicense;

    protected bool $configureValidLicenseByDefault = true;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->configureValidLicenseByDefault && Schema::hasTable('settings')) {
            $this->configureValidLicense();
        }
    }
}
