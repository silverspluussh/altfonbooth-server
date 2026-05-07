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
        Schema::create('prepaid_credits', function (Blueprint $table) {
            $table->id();
            $table->string('authusername');
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->unique();
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->foreign('authusername')->references('authusername')->on('subscriber_auth')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prepaid_credits');
    }
};
