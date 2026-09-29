<?php

namespace Tests;

use Illuminate\Support\Facades\Redis;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->purgeRedisState();
    }

    protected function tearDown(): void
    {
        $this->purgeRedisState();

        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        parent::tearDown();
    }

    private function purgeRedisState(): void
    {
        if (class_exists(Redis::class)) {
            try {
                Redis::flushdb();
            } catch (\Throwable) {
                // Redis might not be running or connected during unit-only runs
            }
        }
    }
}
