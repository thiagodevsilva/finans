<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('cnpj', 14);
            $table->string('name');
            $table->timestamps();

            $table->unique(['account_id', 'cnpj']);
            $table->index(['account_id', 'user_id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('recurring_bills', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('payment_cards', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::table('recurring_bills', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::table('payment_cards', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::dropIfExists('companies');
    }
};
