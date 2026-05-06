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
        Schema::create('subscribers_temp', function (Blueprint $table) {
            $table->integer('recid', true);
            $table->string('subscriberid', 20);
            $table->string('fullname', 60);
            $table->string('username', 50);
            $table->string('password', 100);
            $table->string('phonenumber', 20);
            $table->string('emailaddress', 50);
            $table->string('country', 30);
            $table->string('otp', 12);
            $table->dateTime('otp_expiration')->nullable();
            $table->string('verify_code', 30)->nullable();
            $table->string('status', 20);
            $table->timestamp('regdatetime')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscribers_temp');
    }
};
