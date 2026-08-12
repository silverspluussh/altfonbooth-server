<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BoothApiService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.booth.base_url', 'http://63.250.47.51/altfonapp'), '/');
    }

    public function call(string $endpoint, array $params): ?Response
    {
        $baseUrl = $this->baseUrl();

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post("{$baseUrl}/{$endpoint}", $params);

            if (!$response->successful()) {
                Log::warning('Booth API request failed', [
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Booth API request exception', [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getBalance(string $accesscode): ?Response
    {
        return $this->call('booth_getbal.php', ['accesscode' => $accesscode]);
    }

    public function topup(string $accesscode, string|int|float $amount): ?Response
    {
        return $this->call('booth_topup.php', [
            'accesscode' => $accesscode,
            'amount' => $amount,
        ]);
    }

    public function addDest(array $params): ?Response
    {
        return $this->call('booth_add_dest.php', $params);
    }

    public function delDest(array $params): ?Response
    {
        return $this->call('booth_del_dest.php', $params);
    }

    public function provision(string $endpointUrl, array $payload): ?Response
    {
        $url = $endpointUrl ?: $this->baseUrl() . '/passjson.php';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(10)
                ->connectTimeout(5)
                ->post($url, $payload);

            if (!$response->successful()) {
                Log::warning('Booth provisioning request failed', [
                    'url' => $url,
                    'payload' => $payload,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            Log::error('Booth provisioning request exception', [
                'url' => $url,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public function extractBalance(?Response $response): ?float
    {
        if (!$response) {
            return null;
        }

        $body = json_decode(trim($response->body()), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $body['balance'] ?? null;
    }
}