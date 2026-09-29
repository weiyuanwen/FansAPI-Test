<?php

namespace Tests;

if (class_exists(\Orchestra\Testbench\TestCase::class)) {
    abstract class AbstractTestCaseBridge extends \Orchestra\Testbench\TestCase {}
} else {
    abstract class AbstractTestCaseBridge extends \PHPUnit\Framework\TestCase {}
}

abstract class TestCase extends AbstractTestCaseBridge
{
    protected function getPackageProviders($app): array
    {
        return [
            \Laravel\Scout\ScoutServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        if (method_exists($this, 'loadMigrationsFrom')) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    protected function tearDown(): void
    {
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        parent::tearDown();
    }
}
