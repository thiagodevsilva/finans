<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStillOnIsolatedSqlite();
    }

    private function assertStillOnIsolatedSqlite(): void
    {
        $default = config('database.default');
        $driver = config("database.connections.{$default}.driver");
        $database = (string) config("database.connections.{$default}.database");

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Abortado: teste tentou usar [{$driver}:{$database}]. Apenas sqlite :memory: é permitido."
            );
        }
    }
}
