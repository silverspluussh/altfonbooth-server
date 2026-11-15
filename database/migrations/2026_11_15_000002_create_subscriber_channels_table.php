<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriber_channels', function (Blueprint $table) {
            $table->id();
            $table->string('subscriberid', 20)->index();
            $table->string('channel', 10);
            $table->foreignId('country_id')->nullable()->index();
            $table->string('domain', 100);
            $table->unsignedBigInteger('default_auth_id')->nullable();
            $table->string('status', 15)->default('active');
            $table->timestamps();

            $table->unique(['subscriberid', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriber_channels');
    }
};
