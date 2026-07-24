<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        // Garante isolamento ANTES do bootstrap ler/.env — testes nunca usam MySQL do app.
        $this->forceIsolatedTestingDatabase();

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->assertIsolatedTestingDatabase($app);

        return $app;
    }

    private function forceIsolatedTestingDatabase(): void
    {
        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
    }

    private function assertIsolatedTestingDatabase(Application $app): void
    {
        $default = $app['config']->get('database.default');
        $driver = $app['config']->get("database.connections.{$default}.driver");
        $database = (string) $app['config']->get("database.connections.{$default}.database");

        $forbiddenNames = ['levita', 'levita_test', 'production', 'prod', 'forge'];

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                "Testes recusados: conexão deve ser sqlite in-memory, recebeu driver [{$driver}] / database [{$database}]. ".
                'Nunca rode a suite contra MySQL do app.'
            );
        }

        if ($database !== ':memory:') {
            throw new RuntimeException(
                "Testes recusados: SQLite deve ser :memory:, recebeu [{$database}]."
            );
        }

        if (in_array(strtolower($database), $forbiddenNames, true)) {
            throw new RuntimeException(
                "Testes recusados: database [{$database}] é proibido."
            );
        }

        if (! extension_loaded('pdo_sqlite')) {
            throw new RuntimeException(
                'Extensão pdo_sqlite ausente. Instale php8.1-sqlite3 para rodar os testes '.
                '(SQLite in-memory — não usa o MySQL do Levita).'
            );
        }
    }
}
