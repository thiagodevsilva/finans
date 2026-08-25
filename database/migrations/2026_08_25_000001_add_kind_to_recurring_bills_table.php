<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recurring_bills', 'kind')) {
            Schema::table('recurring_bills', function (Blueprint $table) {
                $table->string('kind')->default('fixed')->after('description');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE recurring_bills MODIFY day_of_month TINYINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recurring_bills', 'kind')) {
            Schema::table('recurring_bills', function (Blueprint $table) {
                $table->dropColumn('kind');
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE recurring_bills MODIFY day_of_month TINYINT UNSIGNED NOT NULL');
        }
    }
};
