<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_relay', function (Blueprint $table) {
            $table->id('recid');
            $table->string('domain', 50);
            $table->string('port', 10)->nullable();
            $table->string('protocol', 10)->nullable();
            $table->string('outboundproxy', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_relay');
    }
};
