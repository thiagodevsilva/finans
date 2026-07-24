<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Nullable: entradas e pagamentos de fatura (transfer) não usam forma de pagamento.
            $table->string('payment_method')->nullable()->default('cash')->after('date');
            $table->foreignUuid('payment_card_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('payment_cards')
                ->nullOnDelete();
        });

        DB::table('transactions')->whereNull('payment_method')->update(['payment_method' => 'cash']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_card_id');
            $table->dropColumn('payment_method');
        });
    }
};
