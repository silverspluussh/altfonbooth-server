<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $channel = $this->resource->active_channel ?? null;

        if (!$channel) {
            try {
                $channel = auth('api')->payload()->get('channel');
            } catch (\Throwable $exception) {
                $channel = null;
            }
        }

        $profile = $channel
            ? $this->channels()->with(['defaultAuth', 'country'])->where('channel', $channel)->first()
            : null;

        $auth = $profile?->defaultAuth ?: $this->auth;
        $domain = $profile?->domain ?: $this->domain;
        $country = $profile?->country?->name ?: $this->country;

        $channels = $this->channels()
            ->with(['defaultAuth', 'country'])
            ->get()
            ->map(function ($item) {
                return [
                    'channel' => $item->channel,
                    'domain' => $item->domain,
                    'country_id' => $item->country_id,
                    'country' => $item->country?->name,
                    'authusername' => $item->defaultAuth?->authusername,
                    'status' => $item->status,
                ];
            })
            ->values()
            ->all();

        [$firstname, $lastname] = $this->splitFullname((string) $this->fullname);

        return [
            'subscriberid' => $this->subscriberid,
            'firstname' => $firstname,
            'lastname' => $lastname,
            'fullname' => $this->fullname,
            'username' => $this->username,
            'phonenumber' => $this->phonenumber,
            'emailaddress' => $this->emailaddress,
            'country' => $country,
            'country_id' => $profile?->country_id ?: $this->country_id,
            'domain' => $domain,
            'channel' => $channel,
            'has_mobile_account' => (bool) $this->has_mobile_account,
            'has_booth_account' => (bool) $this->has_booth_account,
            'channels' => $channels,
            'authusername' => $auth?->authusername ?: $this->authusername,
            'authpassword' => $auth?->authpassword,
            'switch_status' => $this->switch_status,
            'billing_acc_status' => $this->billing_acc_status,
            'regdatetime' => $this->regdatetime,
        ];
    }

    private function splitFullname(string $fullname): array
    {
        $parts = preg_split('/\s+/', trim($fullname), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
