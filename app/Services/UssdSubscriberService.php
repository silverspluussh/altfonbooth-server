<?php

namespace App\Services;

use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Support\Msisdn;
use Illuminate\Support\Collection;

class UssdSubscriberService
{
    public function localMsisdn(string $value): ?string
    {
        return Msisdn::normalize($value);
    }

    public function internationalMsisdn(string $value): ?string
    {
        return Msisdn::toInternational($value);
    }

    public function resolve(string $localMsisdn): ?SubscribersModel
    {
        return SubscribersModel::where('phonenumber', $localMsisdn)
            ->orderByDesc('regdatetime')
            ->first();
    }

    public function status(SubscribersModel $subscriber): string
    {
        return $subscriber->billing_acc_status
            ?: ($subscriber->switch_status ?: 'active');
    }

    public function isActive(SubscribersModel $subscriber): bool
    {
        $status = strtolower(trim($this->status($subscriber)));

        return in_array($status, ['1', 'active', 'enabled', 'on', 'true'], true);
    }

    public function accountsFor(SubscribersModel $subscriber): Collection
    {
        $default = (string) ($subscriber->authusername ?? '');

        return SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)
            ->orderBy('authusername')
            ->get()
            ->sortBy(function (SubscriberAuthModel $account) use ($default) {
                return ($account->authusername === $default ? '0-' : '1-') . $account->authusername;
            })
            ->values();
    }

    public function accountFor(SubscribersModel $subscriber, string $authusername): ?SubscriberAuthModel
    {
        return SubscriberAuthModel::where('authusername', $authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->first();
    }

    public function accountSummaries(SubscribersModel $subscriber): Collection
    {
        return $this->accountsFor($subscriber)->map(function (SubscriberAuthModel $account) use ($subscriber) {
            return [
                'authusername' => $account->authusername,
                'isDefault' => $account->authusername === $subscriber->authusername,
                'status' => $account->status ?: 'active',
            ];
        })->values();
    }
}
