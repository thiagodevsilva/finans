<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->timestamp('balance_stale_at')->nullable()->after('name');
            $table->decimal('balance_stale_adjustment', 15, 2)->default(0)->after('balance_stale_at');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn(['balance_stale_at', 'balance_stale_adjustment']);
        });
    }
};
