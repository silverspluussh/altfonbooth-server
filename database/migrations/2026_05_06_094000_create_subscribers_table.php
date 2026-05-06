<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscribers', function (Blueprint $table) {
            $table->integer('recid', true);
            $table->string('subscriberid', 20)->unique();
            $table->string('fullname', 60);
            $table->string('username', 50);
            $table->string('password', 100);
            $table->string('phonenumber', 20);
            $table->string('emailaddress', 180);
            $table->string('country', 30);
            $table->string('authusername', 20)->nullable();
            $table->string('switch_status', 15)->nullable();
            $table->string('billing_acc_status', 15)->nullable();
            $table->string('password_reset_token', 50)->nullable();
            $table->dateTime('password_reset_expiration')->nullable();
            $table->timestamp('regdatetime')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers');
    }
};
