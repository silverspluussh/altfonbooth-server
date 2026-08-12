<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoiceRelaySeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('voice_relay')->exists()) {
            return;
        }

        DB::table('voice_relay')->insert([
            [
                'domain' => '192.168.1.10',
                'port' => '443',
                'protocol' => 'ws',
                'outboundproxy' => '192.168.1.11',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}