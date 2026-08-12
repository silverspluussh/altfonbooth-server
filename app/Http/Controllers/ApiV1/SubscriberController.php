<?php

namespace App\Http\Controllers\ApiV1;

use App\Http\Controllers\SubscribersController;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Services\BoothApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriberController
{
    public function subscriber(Request $request): JsonResponse
    {
        $subscriber = $request->user();

        return response()->json([
            'status' => true,
            'data' => [
                'subscriberid' => $subscriber->subscriberid,
                'firstname' => $subscriber->firstname ?? null,
                'lastname' => $subscriber->lastname ?? null,
                'fullname' => $subscriber->fullname,
                'username' => $subscriber->username,
                'emailaddress' => $subscriber->emailaddress,
                'phonenumber' => $subscriber->phonenumber,
                'country' => $subscriber->country,
                'authusername' => $subscriber->authusername,
                'authpassword' => $subscriber->auth?->authpassword,
            ],
        ]);
    }

    public function subscribers(Request $request): JsonResponse
    {
        $subscribers = SubscribersModel::all();

        $data = $subscribers->map(function ($subscriber) {
            [$firstname, $lastname] = $this->splitFullname((string) $subscriber->fullname);

            return [
                'fullname' => ucwords(trim("$firstname $lastname")),
                'emailaddress' => $subscriber->emailaddress,
                'phonenumber' => $subscriber->phonenumber,
                'authusername' => $subscriber->authusername,
            ];
        });

        return response()->json(['status' => true, 'data' => $data]);
    }

    public function subscriberList(Request $request): JsonResponse
    {
        return app(SubscribersController::class)->index($request);
    }

    public function allContacts(Request $request): JsonResponse
    {
        return app(SubscribersController::class)->allContacts($request);
    }

    public function voiceRelays(Request $request): JsonResponse
    {
        return app(SubscribersController::class)->voiceRelays($request);
    }

    public function checkBalance(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
        ]);

        $subscriber = $request->user();

        $authExists = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->exists();

        if (!$authExists) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This SIP account does not belong to you.'], 403);
        }

        $response = app(BoothApiService::class)->getBalance($request->authusername);

        $decoded = $response ? json_decode($response->body(), true) : null;

        if (!$response || !$decoded) {
            return response()->json(['status' => false, 'message' => 'API call failed'], 502);
        }

        return response()->json(['status' => true, 'data' => $decoded]);
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        return app(SubscribersController::class)->verifyPayment($request);
    }

    public function paymentLink(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $subscriber = $request->user();

        $authExists = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->exists();

        if (!$authExists) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This SIP account does not belong to you.'], 403);
        }

        $url = rtrim(config('app.frontend_url'), '/')
            . '/dashboard.html?buy=1'
            . '&authusername=' . urlencode($request->authusername)
            . '&amount=' . urlencode($request->amount);

        return response()->json([
            'status' => true,
            'data' => ['url' => $url],
        ]);
    }

    private function splitFullname(string $fullname): array
    {
        $parts = preg_split('/\s+/', trim($fullname), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}