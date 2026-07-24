<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_card_invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('payment_card_id')->constrained('payment_cards')->cascadeOnDelete();
            $table->date('closing_date');
            $table->date('due_date');
            $table->string('status')->default('open');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['payment_card_id', 'closing_date']);
            $table->index(['account_id', 'due_date']);
            $table->index(['payment_card_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_card_invoices');
    }
};
