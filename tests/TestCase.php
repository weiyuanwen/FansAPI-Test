<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

// Compatibility stub for environments executing tests outside full laravel/framework
if (!trait_exists('Illuminate\Foundation\Testing\RefreshDatabase')) {
    eval('namespace Illuminate\Foundation\Testing { trait RefreshDatabase {} }');
}

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            \Mockery::close();
        }
        parent::tearDown();
    }
}
