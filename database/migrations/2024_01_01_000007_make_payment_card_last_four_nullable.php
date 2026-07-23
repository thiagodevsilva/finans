<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE payment_cards MODIFY last_four VARCHAR(4) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payment_cards ALTER COLUMN last_four DROP NOT NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite ignores NOT NULL changes in many setups; recreate not needed for tests with fresh migrate.
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("UPDATE payment_cards SET last_four = '0000' WHERE last_four IS NULL");
            DB::statement('ALTER TABLE payment_cards MODIFY last_four VARCHAR(4) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement("UPDATE payment_cards SET last_four = '0000' WHERE last_four IS NULL");
            DB::statement('ALTER TABLE payment_cards ALTER COLUMN last_four SET NOT NULL');
        }
    }
};
