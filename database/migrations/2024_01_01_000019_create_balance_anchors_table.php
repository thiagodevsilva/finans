<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balance_anchors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('as_of_date');
            $table->string('source', 32);
            $table->string('checkin_month', 7)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'as_of_date']);
            $table->index(['account_id', 'checkin_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_anchors');
    }
};
