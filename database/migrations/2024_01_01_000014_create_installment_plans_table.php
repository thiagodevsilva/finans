<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUuid('payment_card_id')->constrained('payment_cards')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('total_amount', 12, 2);
            $table->unsignedSmallInteger('installments_count');
            $table->date('purchase_date');
            $table->date('first_installment_date');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
