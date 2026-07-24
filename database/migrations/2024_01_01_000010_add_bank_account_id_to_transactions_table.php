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
            $table->foreignUuid('bank_account_id')
                ->nullable()
                ->after('payment_card_id')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });

        // Entradas / transferências de fatura não usam forma de pagamento.
        // Em instalações novas a coluna já nasce nullable (migration 000006).
        // Em MySQL legado ainda pode estar NOT NULL.
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE transactions MODIFY payment_method VARCHAR(255) NULL');
        }

        DB::table('transactions')
            ->where('type', 'income')
            ->update([
                'payment_method' => null,
                'payment_card_id' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('transactions')
            ->whereNull('payment_method')
            ->update(['payment_method' => 'cash']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transactions MODIFY payment_method VARCHAR(255) NOT NULL DEFAULT 'cash'");
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
