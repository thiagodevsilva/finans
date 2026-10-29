<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recurring_bills', 'suspended_from')) {
            Schema::table('recurring_bills', function (Blueprint $table) {
                $table->date('suspended_from')->nullable()->after('active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recurring_bills', 'suspended_from')) {
            Schema::table('recurring_bills', function (Blueprint $table) {
                $table->dropColumn('suspended_from');
            });
        }
    }
};
