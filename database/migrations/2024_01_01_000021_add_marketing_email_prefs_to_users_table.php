<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_emails_opted_in')->default(true)->after('onboarding_status');
            $table->timestamp('marketing_unsubscribed_at')->nullable()->after('marketing_emails_opted_in');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['marketing_emails_opted_in', 'marketing_unsubscribed_at']);
        });
    }
};
