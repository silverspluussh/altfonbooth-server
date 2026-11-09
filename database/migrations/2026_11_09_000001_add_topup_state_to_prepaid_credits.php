<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prepaid_credits', function (Blueprint $table) {
            $table->string('source', 30)->default('unknown')->after('status');
            $table->unsignedTinyInteger('topup_attempts')->default(0)->after('source');
            $table->string('failure_reason', 255)->nullable()->after('topup_attempts');
            $table->timestamp('paid_at')->nullable()->after('failure_reason');
            $table->timestamp('last_attempt_at')->nullable()->after('paid_at');

            $table->index('status', 'idx_prepaid_credits_status');
        });
    }

    public function down(): void
    {
        Schema::table('prepaid_credits', function (Blueprint $table) {
            $table->dropIndex('idx_prepaid_credits_status');
            $table->dropColumn([
                'source',
                'topup_attempts',
                'failure_reason',
                'paid_at',
                'last_attempt_at',
            ]);
        });
    }
};
