<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('type'); // income | expense | transfer
            $table->decimal('amount', 12, 2);
            $table->string('description');
            $table->date('date');
            $table->timestamps();

            $table->index('account_id');
            $table->index('date');
            $table->index('type');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
