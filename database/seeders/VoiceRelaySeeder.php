<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoiceRelaySeeder extends Seeder
{
    public function run(): void
    {
        $relay = [
            'domain' => 'ghana.altfon.net',
            'port' => '5060',
            'protocol' => 'udp',
            'outboundproxy' => '203.161.38.171',
            'updated_at' => now(),
        ];

        $existing = DB::table('voice_relay')
            ->where('domain', 'ghana.altfon.net')
            ->first();

        if ($existing) {
            DB::table('voice_relay')
                ->where('recid', $existing->recid)
                ->update($relay);

            return;
        }

        DB::table('voice_relay')->insert([
            $relay + ['created_at' => now()],
        ]);
    }
}
