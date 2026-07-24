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
            $table->foreignUuid('credit_card_invoice_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('credit_card_invoices')
                ->nullOnDelete();

            $table->foreignUuid('installment_plan_id')
                ->nullable()
                ->after('credit_card_invoice_id')
                ->constrained('installment_plans')
                ->nullOnDelete();

            $table->unsignedSmallInteger('installment_number')->nullable()->after('installment_plan_id');

            $table->foreignUuid('recurring_bill_id')
                ->nullable()
                ->after('installment_number')
                ->constrained('recurring_bills')
                ->nullOnDelete();

            $table->string('status')->default('confirmed')->after('recurring_bill_id');

            $table->index(['account_id', 'status']);
            $table->index(['installment_plan_id', 'installment_number']);
        });

        // Em MySQL, instalações antigas tinham category_id NOT NULL.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });

            DB::statement('ALTER TABLE transactions MODIFY category_id CHAR(36) NULL');

            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_card_invoice_id');
            $table->dropConstrainedForeignId('installment_plan_id');
            $table->dropColumn('installment_number');
            $table->dropConstrainedForeignId('recurring_bill_id');
            $table->dropColumn('status');
        });
    }
};
