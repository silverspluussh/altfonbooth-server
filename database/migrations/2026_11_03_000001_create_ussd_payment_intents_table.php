<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ussd_payment_intents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->string('subscriberid', 20);
            $table->string('authusername', 50);
            $table->string('msisdn', 20);
            $table->decimal('amount', 10, 2);
            $table->string('network', 20)->nullable();
            $table->string('provider', 30)->default('fake');
            $table->string('external_ref', 128)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason', 255)->nullable();
            $table->unsignedTinyInteger('topup_attempts')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_intent_status');
            $table->index(['msisdn', 'created_at'], 'idx_intent_msisdn_created');
            $table->index('authusername', 'idx_intent_auth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ussd_payment_intents');
    }
};
