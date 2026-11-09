<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ussd_payment_intents', function (Blueprint $table) {
            $table->boolean('requires_otp')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ussd_payment_intents', function (Blueprint $table) {
            $table->dropColumn('requires_otp');
        });
    }
};
