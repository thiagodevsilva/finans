<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payment_cards', 'type')) {
            return;
        }

        Schema::table('payment_cards', function (Blueprint $table) {
            $table->string('type')->default('credit')->after('brand'); // credit | debit
        });

        DB::table('payment_cards')->whereNull('type')->update(['type' => 'credit']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payment_cards', 'type')) {
            return;
        }

        Schema::table('payment_cards', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
