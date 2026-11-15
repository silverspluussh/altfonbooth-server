<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscribers', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('country')->index();
            $table->boolean('has_mobile_account')->default(false)->after('country_id');
            $table->boolean('has_booth_account')->default(false)->after('has_mobile_account');
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->string('country', 30)->nullable()->change();
        });

        Schema::table('subscribers_temp', function (Blueprint $table) {
            $table->string('channel', 10)->default('mobile')->after('subscriberid')->index();
            $table->foreignId('country_id')->nullable()->after('country')->index();
            $table->string('domain', 100)->nullable()->after('country_id');
        });

        Schema::table('subscribers_temp', function (Blueprint $table) {
            $table->string('country', 30)->nullable()->change();
        });

        Schema::table('subscriber_auth', function (Blueprint $table) {
            $table->string('channel', 10)->default('mobile')->after('subscriberid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('subscriber_auth', function (Blueprint $table) {
            $table->dropColumn('channel');
        });

        Schema::table('subscribers_temp', function (Blueprint $table) {
            $table->dropColumn(['channel', 'country_id', 'domain']);
        });

        Schema::table('subscribers', function (Blueprint $table) {
            $table->dropColumn(['country_id', 'has_mobile_account', 'has_booth_account']);
        });
    }
};
