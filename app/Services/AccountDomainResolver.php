<?php

namespace App\Services;

use App\Models\SubscriberAuthModel;
use App\Models\SubscriberChannelModel;
use App\Models\SubscribersModel;
use App\Support\SubscriberDomain;

class AccountDomainResolver
{
    public function forAuthUsername(?string $authusername): string
    {
        $authusername = trim((string) $authusername);

        if ($authusername === '') {
            return SubscriberDomain::DEFAULT_DOMAIN;
        }

        return $this->forAuth(
            SubscriberAuthModel::where('authusername', $authusername)->first()
        );
    }

    public function forAuth(?SubscriberAuthModel $auth): string
    {
        if (!$auth) {
            return SubscriberDomain::DEFAULT_DOMAIN;
        }

        $subscriber = SubscribersModel::where('subscriberid', $auth->subscriberid)->first();
        $channel = $auth->channel ?: SubscriberChannelModel::MOBILE;
        $profile = SubscriberChannelModel::where('subscriberid', $auth->subscriberid)
            ->where('channel', $channel)
            ->first();

        return $this->pick($profile?->domain, $subscriber, $channel);
    }

    /**
     * @param  array<int, string>  $authusernames
     * @return array<string, string>
     */
    public function forAuths(array $authusernames): array
    {
        $authusernames = array_values(array_unique(array_filter(array_map('strval', $authusernames))));

        if ($authusernames === []) {
            return [];
        }

        $auths = SubscriberAuthModel::whereIn('authusername', $authusernames)
            ->get()
            ->keyBy('authusername');

        $subscriberIds = $auths->pluck('subscriberid')->unique()->values()->all();

        $subscribers = SubscribersModel::whereIn('subscriberid', $subscriberIds)
            ->get()
            ->keyBy('subscriberid');

        $profiles = SubscriberChannelModel::whereIn('subscriberid', $subscriberIds)
            ->get()
            ->keyBy(fn (SubscriberChannelModel $profile) => $profile->subscriberid . ':' . $profile->channel);

        $domains = [];

        foreach ($authusernames as $authusername) {
            $auth = $auths->get($authusername);
            if (!$auth) {
                $domains[$authusername] = SubscriberDomain::DEFAULT_DOMAIN;
                continue;
            }

            $channel = $auth->channel ?: SubscriberChannelModel::MOBILE;
            $profile = $profiles->get($auth->subscriberid . ':' . $channel);

            $domains[$authusername] = $this->pick(
                $profile?->domain,
                $subscribers->get($auth->subscriberid),
                $channel,
            );
        }

        return $domains;
    }

    private function pick(?string $profileDomain, ?SubscribersModel $subscriber, string $channel): string
    {
        $domain = strtolower(trim((string) $profileDomain));
        if ($domain !== '') {
            return $domain;
        }

        $domain = strtolower(trim((string) $subscriber?->domain));
        if ($domain !== '') {
            return $domain;
        }

        if ($channel === SubscriberChannelModel::BOOTH) {
            return SubscriberDomain::BOOTH_DOMAIN;
        }

        $country = trim((string) $subscriber?->country);

        return $country !== ''
            ? SubscriberDomain::fromCountry($country)
            : SubscriberDomain::DEFAULT_DOMAIN;
    }
}
