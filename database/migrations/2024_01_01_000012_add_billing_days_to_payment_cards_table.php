<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_cards', function (Blueprint $table) {
            $table->unsignedTinyInteger('closing_day')->nullable()->after('color');
            $table->unsignedTinyInteger('due_day')->nullable()->after('closing_day');
        });
    }

    public function down(): void
    {
        Schema::table('payment_cards', function (Blueprint $table) {
            $table->dropColumn(['closing_day', 'due_day']);
        });
    }
};
