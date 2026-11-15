<?php

namespace App\Services;

use App\Models\Country;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberChannelModel;
use App\Models\SubscribersModel;
use App\Support\Msisdn;
use App\Support\SubscriberDomain;
use Illuminate\Support\Str;

class SubscriberChannelService
{
    public function resolveCountry(array $input): ?Country
    {
        $countryId = $input['country_id'] ?? null;
        if ($countryId !== null && $countryId !== '') {
            return Country::active()->find($countryId);
        }

        $code = trim((string) ($input['country_code'] ?? $input['iso_code'] ?? ''));
        if ($code !== '') {
            return Country::active()
                ->whereRaw('LOWER(iso_code) = ?', [strtolower($code)])
                ->first();
        }

        $name = trim((string) ($input['country'] ?? ''));
        if ($name !== '') {
            return Country::active()
                ->whereRaw('LOWER(name) = ?', [strtolower($name)])
                ->first();
        }

        return null;
    }

    public function domainFor(string $channel, ?Country $country = null): string
    {
        if ($channel === SubscriberChannelModel::BOOTH) {
            return SubscriberDomain::BOOTH_DOMAIN;
        }

        $domain = trim((string) ($country?->domain ?? ''));

        return $domain !== ''
            ? $domain
            : SubscriberDomain::fromCountry($country?->name);
    }

    public function createChannel(
        SubscribersModel $subscriber,
        string $channel,
        ?Country $country = null,
        ?string $provisionCode = null,
    ): SubscriberChannelModel {
        if (!in_array($channel, [SubscriberChannelModel::MOBILE, SubscriberChannelModel::BOOTH], true)) {
            throw new \InvalidArgumentException('Invalid channel.');
        }

        if ($subscriber->channelProfile($channel)) {
            throw new \RuntimeException('This account already has that channel.');
        }

        if ($channel === SubscriberChannelModel::MOBILE && !$country) {
            throw new \InvalidArgumentException('A country is required for a mobile account.');
        }

        $domain = $this->domainFor($channel, $country);
        SubscriberDomain::ensureRelay($domain);

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => $channel,
            'authusername' => $this->generateUniqueSipNumber(),
            'authpassword' => Str::random(SubscriberAuthModel::PASSWORD_LENGTH),
            'status' => 'active',
        ]);

        $profile = SubscriberChannelModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'channel' => $channel,
            'country_id' => $channel === SubscriberChannelModel::MOBILE ? $country?->id : null,
            'domain' => $domain,
            'default_auth_id' => $auth->id,
            'status' => 'active',
        ]);

        $subscriber->update([
            $channel === SubscriberChannelModel::MOBILE ? 'has_mobile_account' : 'has_booth_account' => true,
        ]);

        $this->syncDefaults($subscriber->fresh());

        $this->provision($subscriber->fresh(), $profile->fresh(), $provisionCode);

        return $profile->fresh(['defaultAuth', 'country']);
    }

    public function provision(SubscribersModel $subscriber, SubscriberChannelModel $profile, ?string $code = null): void
    {
        $auth = $profile->defaultAuth;
        if (!$auth) {
            return;
        }

        $payload = [
            'email' => $subscriber->emailaddress,
            'name' => $subscriber->fullname,
            'phone' => Msisdn::toInternational((string) $subscriber->phonenumber) ?? $subscriber->phonenumber,
            'authusername' => $auth->authusername,
            'authpassword' => $auth->authpassword,
            'domain' => $profile->domain,
            'code' => $code,
        ];

        app(BoothApiService::class)->provision(config('services.booth.provision_url'), $payload);
    }

    public function syncDefaults(SubscribersModel $subscriber): void
    {
        $mobile = $subscriber->channelProfile(SubscriberChannelModel::MOBILE);
        $booth = $subscriber->channelProfile(SubscriberChannelModel::BOOTH);

        $update = [
            'has_mobile_account' => $mobile !== null,
            'has_booth_account' => $booth !== null,
        ];

        if ($mobile) {
            $update['country_id'] = $mobile->country_id;
            $update['country'] = $mobile->country?->name;
            $update['domain'] = $mobile->domain;
            $update['authusername'] = $mobile->defaultAuth?->authusername;
        } elseif ($booth) {
            $update['country_id'] = null;
            $update['country'] = null;
            $update['domain'] = $booth->domain;
            $update['authusername'] = $booth->defaultAuth?->authusername;
        }

        $subscriber->update($update);
    }

    public function removeChannel(SubscribersModel $subscriber, string $channel): void
    {
        $profile = $subscriber->channelProfile($channel);
        if (!$profile) {
            return;
        }

        $authIds = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)
            ->where('channel', $channel)
            ->pluck('id');

        \App\Models\SubscriberDestModel::whereIn(
            'authusername',
            SubscriberAuthModel::whereIn('id', $authIds)->pluck('authusername')
        )->delete();

        \App\Models\PrepaidCredit::whereIn(
            'authusername',
            SubscriberAuthModel::whereIn('id', $authIds)->pluck('authusername')
        )->delete();

        SubscriberAuthModel::whereIn('id', $authIds)->delete();
        $profile->delete();

        $this->syncDefaults($subscriber->fresh());
    }

    private function generateUniqueSipNumber(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $candidate = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            if (!SubscriberAuthModel::where('authusername', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not generate a unique SIP number');
    }
}
