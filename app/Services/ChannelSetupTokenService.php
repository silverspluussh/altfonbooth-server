<?php

namespace App\Services;

use App\Models\SubscribersModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChannelSetupTokenService
{
    private const TTL_MINUTES = 10;

    public function issue(SubscribersModel $subscriber, string $channel): string
    {
        $token = Str::random(64);

        Cache::put($this->key($token), [
            'subscriberid' => $subscriber->subscriberid,
            'channel' => $channel,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    public function consume(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $key = $this->key($token);
        $payload = Cache::get($key);

        if (!is_array($payload)) {
            return null;
        }

        Cache::forget($key);

        return $payload;
    }

    private function key(string $token): string
    {
        return 'channel_setup:' . hash('sha256', $token);
    }
}
