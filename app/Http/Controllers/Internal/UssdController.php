<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Services\BoothApiService;
use App\Services\UssdSignupSmsService;
use App\Services\UssdSubscriberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UssdController extends Controller
{
    public function __construct(
        private BoothApiService $booth,
        private UssdSubscriberService $subscribers,
        private UssdSignupSmsService $signupSms,
    ) {
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:20'],
        ]);

        $local = $this->subscribers->localMsisdn($data['msisdn']);

        if ($local === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $matchCount = SubscribersModel::where('phonenumber', $local)->count();
        $duplicateMatches = max(0, $matchCount - 1);
        $subscriber = $this->subscribers->resolve($local);

        if (!$subscriber) {
            $this->signupSms->notify($local, UssdSignupSmsService::REASON_NOT_REGISTERED);

            return response()->json([
                'status' => true,
                'data' => [
                    'registered' => false,
                    'subscriber' => null,
                    'accounts' => [],
                    'duplicateMatches' => $duplicateMatches,
                ],
            ]);
        }

        $accounts = $this->subscribers->accountSummaries($subscriber);
        $active = $this->subscribers->isActive($subscriber);

        if (!$active) {
            $this->signupSms->notify($local, UssdSignupSmsService::REASON_INACTIVE);
        } elseif ($accounts->isEmpty()) {
            $this->signupSms->notify($local, UssdSignupSmsService::REASON_NO_NUMBER);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'registered' => true,
                'subscriber' => [
                    'subscriberid' => $subscriber->subscriberid,
                    'fullname' => $subscriber->fullname,
                    'status' => $this->subscribers->status($subscriber),
                    'defaultAuthusername' => $subscriber->authusername,
                ],
                'accounts' => $accounts,
                'duplicateMatches' => $duplicateMatches,
            ],
        ]);
    }

    public function accounts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:20'],
            'withBalance' => ['sometimes', 'boolean'],
        ]);

        $local = $this->subscribers->localMsisdn($data['msisdn']);

        if ($local === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $subscriber = $this->subscribers->resolve($local);

        if (!$subscriber) {
            return $this->error('NOT_REGISTERED', 'This phone number is not registered.', 404);
        }

        $accounts = $this->subscribers->accountsFor($subscriber);
        $withBalance = $request->boolean('withBalance');
        $balances = $withBalance
            ? $this->booth->getBalances($accounts->pluck('authusername')->all())
            : [];

        $items = $accounts->map(function (SubscriberAuthModel $account) use ($subscriber, $withBalance, $balances) {
            $item = [
                'authusername' => $account->authusername,
                'isDefault' => $account->authusername === $subscriber->authusername,
                'status' => $account->status ?: 'active',
            ];

            if ($withBalance) {
                $item['balance'] = $balances[$account->authusername] ?? null;
            }

            return $item;
        })->values();

        return response()->json([
            'status' => true,
            'data' => [
                'accounts' => $items,
            ],
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:20'],
            'authusername' => ['required', 'regex:/^\d+$/'],
        ]);

        $local = $this->subscribers->localMsisdn($data['msisdn']);

        if ($local === null) {
            return $this->error('INVALID_MSISDN', 'Enter a valid phone number.', 422);
        }

        $subscriber = $this->subscribers->resolve($local);

        if (!$subscriber) {
            return $this->error('NOT_REGISTERED', 'This phone number is not registered.', 404);
        }

        $account = $this->subscribers->accountFor($subscriber, $data['authusername']);

        if (!$account) {
            return $this->error('FORBIDDEN', 'This SIP number does not belong to you.', 403);
        }

        // Keep this short so the USSD balance screen answers inside the gateway budget.
        $response = $this->booth->getBalance($account->authusername, 3);
        $balance = $this->booth->extractBalance($response);

        if ($response === null || $balance === null || !is_numeric($balance)) {
            return $this->error('BOOTH_UNAVAILABLE', 'Balance is not available right now.', 502);
        }

        return response()->json([
            'status' => true,
            'data' => [
                'authusername' => $account->authusername,
                'balance' => (float) $balance,
            ],
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'status' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }
}
