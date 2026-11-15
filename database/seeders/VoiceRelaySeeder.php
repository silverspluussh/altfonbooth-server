<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VoiceRelaySeeder extends Seeder
{
    public function run(): void
    {
        $domains = [
            'ghana.altfon.net',
            'booth.altfon.net',
        ];

        foreach ($domains as $domain) {
            $relay = [
                'domain' => $domain,
                'port' => '5060',
                'protocol' => 'udp',
                'outboundproxy' => '203.161.38.171',
                'updated_at' => now(),
            ];

            $existing = DB::table('voice_relay')
                ->where('domain', $domain)
                ->first();

            if ($existing) {
                DB::table('voice_relay')
                    ->where('recid', $existing->recid)
                    ->update($relay);

                continue;
            }

            DB::table('voice_relay')->insert([
                $relay + ['created_at' => now()],
            ]);
        }
    }
}
