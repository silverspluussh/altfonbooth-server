<?php

namespace App\Support;

use App\Models\VoiceRelay;

final class SubscriberDomain
{
    public const DEFAULT_DOMAIN = 'ghana.altfon.net';

    public static function fromCountry(?string $country): string
    {
        $normalized = strtolower(trim((string) $country));
        $normalized = preg_replace('/[^a-z0-9-]+/', '', $normalized) ?? '';

        if ($normalized === '') {
            return self::DEFAULT_DOMAIN;
        }

        return $normalized . '.altfon.net';
    }

    /**
     * Keep the voice_relay table in sync with the per-user domain. The user's
     * domain is the source of truth; the relay row only carries the shared
     * port/protocol/outbound proxy values for other consumers.
     */
    public static function ensureRelay(string $domain): VoiceRelay
    {
        $domain = strtolower(trim($domain));

        $existing = VoiceRelay::query()
            ->whereRaw('LOWER(domain) = ?', [$domain])
            ->first();

        if ($existing) {
            return $existing;
        }

        $template = VoiceRelay::query()->orderBy('recid')->first();

        return VoiceRelay::create([
            'domain' => $domain,
            'port' => $template?->port ?? '5060',
            'protocol' => $template?->protocol ?? 'udp',
            'outboundproxy' => $template?->outboundproxy ?? '203.161.38.171',
        ]);
    }
}
