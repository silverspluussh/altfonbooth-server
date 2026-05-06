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
        Schema::create('subscriber_auth', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('subscriberid', 20);
            $table->string('authusername', 50)->unique();
            $table->string('authpassword', 255);
            $table->string('status', 15)->default('active');
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('subscriberid', 'fk_subscriber')
                  ->references('subscriberid')
                  ->on('subscribers')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriber_auth');
    }
};
