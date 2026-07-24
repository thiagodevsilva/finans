<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('estimated_amount', 12, 2);
            $table->unsignedTinyInteger('day_of_month');
            $table->string('frequency')->default('monthly');
            $table->string('payment_method')->nullable();
            $table->foreignUuid('payment_card_id')->nullable()->constrained('payment_cards')->nullOnDelete();
            $table->foreignUuid('bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['account_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_bills');
    }
};
