<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('user_id');
            $table->foreignUuid('created_by')->nullable()->after('is_shared')
                ->constrained('users')->nullOnDelete();
            $table->uuid('company_id')->nullable()->after('created_by');
            $table->index(['account_id', 'user_id', 'is_shared']);
            $table->index(['account_id', 'company_id']);
        });

        Schema::table('recurring_bills', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('user_id');
            $table->foreignUuid('created_by')->nullable()->after('is_shared')
                ->constrained('users')->nullOnDelete();
            $table->uuid('company_id')->nullable()->after('created_by');
            $table->index(['account_id', 'user_id', 'is_shared']);
        });

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('user_id');
            $table->foreignUuid('created_by')->nullable()->after('is_shared')
                ->constrained('users')->nullOnDelete();
            $table->uuid('company_id')->nullable()->after('created_by');
            $table->index(['account_id', 'user_id', 'is_shared']);
        });

        Schema::table('payment_cards', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->after('user_id');
            $table->index(['account_id', 'company_id']);
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->after('user_id');
            $table->index(['account_id', 'company_id']);
        });

        Schema::table('balance_anchors', function (Blueprint $table) {
            $table->foreignUuid('created_by')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->index(['account_id', 'user_id', 'as_of_date'], 'balance_anchors_account_user_date_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('balance_stale_at')->nullable()->after('pinned_dashboard_chart');
            $table->decimal('balance_stale_adjustment', 15, 2)->default(0)->after('balance_stale_at');
            $table->timestamp('balance_stale_dismissed_at')->nullable()->after('balance_stale_adjustment');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['account_id', 'user_id', 'is_shared']);
            $table->dropIndex(['account_id', 'company_id']);
            $table->dropColumn(['is_shared', 'created_by', 'company_id']);
        });

        Schema::table('recurring_bills', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['account_id', 'user_id', 'is_shared']);
            $table->dropColumn(['is_shared', 'created_by', 'company_id']);
        });

        Schema::table('installment_plans', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['account_id', 'user_id', 'is_shared']);
            $table->dropColumn(['is_shared', 'created_by', 'company_id']);
        });

        Schema::table('payment_cards', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropIndex(['account_id', 'company_id']);
            $table->dropColumn('company_id');
        });

        Schema::table('balance_anchors', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex('balance_anchors_account_user_date_index');
            $table->dropColumn('created_by');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'balance_stale_at',
                'balance_stale_adjustment',
                'balance_stale_dismissed_at',
            ]);
        });
    }
};
