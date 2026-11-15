<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\SubscriberResource;
use App\Http\Resources\SubscriberAuthResource;
use App\Http\Resources\SubscriberDestResource;
use App\Models\AdminModel;
use App\Models\Country;
use App\Models\SubscribersModel;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberChannelModel;
use App\Models\SubscriberDestModel;
use App\Models\PrepaidCredit;
use App\Services\BoothApiService;
use App\Services\PrepaidCreditService;
use App\Services\SubscriberChannelService;
use App\Support\Msisdn;
use App\Support\SubscriberDomain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    private function booth(): BoothApiService
    {
        return app(BoothApiService::class);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Username and password required'], 400);
        }

        $admin = AdminModel::where('username', $request->username)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = auth('admin-api')->login($admin);
        $ttl = (int) config('jwt.ttl', 720);

        return response()->json([
            'status' => true,
            'token' => $token,
            'expires_in' => $ttl * 60,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            'admin' => new AdminResource($admin)
        ]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = auth('admin-api')->refresh();
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => false,
                'message' => 'Session expired. Please log in again.',
            ], 401);
        }

        $ttl = (int) config('jwt.ttl', 720);

        return response()->json([
            'status' => true,
            'message' => 'Session refreshed',
            'token' => $token,
            'expires_in' => $ttl * 60,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
        ]);
    }

    public function logout(): JsonResponse
    {
        try {
            auth('admin-api')->logout();
        } catch (\Throwable $exception) {
            // Ignore expired/blacklisted tokens; the client session is cleared.
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out',
        ]);
    }

    public function index(): JsonResponse
    {
        $admins = AdminModel::all();
        return response()->json(['data' => AdminResource::collection($admins)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'username' => 'required|string|unique:admins,username',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:super_admin,manager',
        ]);

        $admin = AdminModel::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Admin created successfully',
            'data' => new AdminResource($admin)
        ], 201);
    }

    public function destroy($id): JsonResponse
    {
        $admin = AdminModel::find($id);
        if (!$admin) {
            return response()->json(['message' => 'Admin not found'], 404);
        }

        if ($admin->isSuperAdmin() && AdminModel::where('role', 'super_admin')->count() <= 1) {
            return response()->json(['message' => 'Cannot delete the last Super Admin'], 400);
        }

        $admin->delete();
        return response()->json(['status' => true, 'message' => 'Admin deleted']);
    }

    public function listSubscribers(): JsonResponse
    {
        $subs = SubscribersModel::all();
        return response()->json(['data' => SubscriberResource::collection($subs)]);
    }

    public function showSubscriber($id): JsonResponse
    {
        $sub = SubscribersModel::where('subscriberid', $id)->first();
        if (!$sub) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }
        return response()->json(['data' => new SubscriberResource($sub)]);
    }

    public function storeSubscriber(Request $request): JsonResponse
    {
        $request->validate([
            'fullname' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:subscribers,username',
            'emailaddress' => 'required|email|unique:subscribers,emailaddress',
            'phonenumber' => 'nullable|string|max:20',
            'country_id' => 'nullable|integer',
            'has_mobile_account' => 'sometimes|boolean',
            'has_booth_account' => 'sometimes|boolean',
            'password' => 'required|string|min:6',
        ]);

        $service = app(SubscriberChannelService::class);
        $wantsMobile = $request->boolean('has_mobile_account');
        $wantsBooth = $request->boolean('has_booth_account');

        if (!$wantsMobile && !$wantsBooth) {
            return response()->json([
                'status' => false,
                'message' => 'Select at least one account type (mobile or booth).',
            ], 422);
        }

        $country = $wantsMobile ? $service->resolveCountry($request->all()) : null;
        if ($wantsMobile && !$country) {
            return response()->json([
                'status' => false,
                'message' => 'A valid country is required for a mobile account.',
            ], 422);
        }

        $sub = SubscribersModel::create([
            'subscriberid' => 'SUB' . strtoupper(Str::random(8)),
            'fullname' => $request->fullname,
            'username' => $request->username,
            'emailaddress' => $request->emailaddress,
            'phonenumber' => Msisdn::toStorage($request->phonenumber),
            'password' => Hash::make($request->password),
            'has_mobile_account' => false,
            'has_booth_account' => false,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        if ($wantsMobile) {
            $service->createChannel($sub, SubscriberChannelModel::MOBILE, $country);
        }
        if ($wantsBooth) {
            $service->createChannel($sub, SubscriberChannelModel::BOOTH);
        }

        return response()->json([
            'status' => true,
            'message' => 'Subscriber created successfully',
            'data' => new SubscriberResource($sub->fresh())
        ], 201);
    }

    public function updateSubscriber(Request $request, $id): JsonResponse
    {
        $sub = SubscribersModel::where('subscriberid', $id)->first();
        if (!$sub) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'username' => 'sometimes|string|max:255|unique:subscribers,username,' . $sub->recid . ',recid',
            'emailaddress' => 'sometimes|email|unique:subscribers,emailaddress,' . $sub->recid . ',recid',
            'phonenumber' => 'nullable|string|max:20',
            'country_id' => 'nullable|integer',
            'has_mobile_account' => 'sometimes|boolean',
            'has_booth_account' => 'sometimes|boolean',
            'password' => 'nullable|string|min:6',
            'switch_status' => 'nullable|string',
            'billing_acc_status' => 'nullable|string',
        ]);

        $service = app(SubscriberChannelService::class);
        $targetMobile = $request->has('has_mobile_account')
            ? $request->boolean('has_mobile_account')
            : (bool) $sub->has_mobile_account;
        $targetBooth = $request->has('has_booth_account')
            ? $request->boolean('has_booth_account')
            : (bool) $sub->has_booth_account;

        if (!$targetMobile && !$targetBooth) {
            return response()->json([
                'status' => false,
                'message' => 'A subscriber must have at least one account type.',
            ], 422);
        }

        $data = $request->only(['fullname', 'username', 'emailaddress', 'switch_status', 'billing_acc_status']);
        if ($request->has('phonenumber')) {
            $data['phonenumber'] = Msisdn::toStorage($request->input('phonenumber'));
        }
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        $sub->update($data);

        $mobileProfile = $sub->channelProfile(SubscriberChannelModel::MOBILE);
        $boothProfile = $sub->channelProfile(SubscriberChannelModel::BOOTH);

        if ($targetMobile) {
            $country = $service->resolveCountry([
                'country_id' => $request->input('country_id', $sub->country_id),
                'country' => $sub->country,
            ]);

            if (!$country) {
                return response()->json([
                    'status' => false,
                    'message' => 'A valid country is required for a mobile account.',
                ], 422);
            }

            if ($mobileProfile) {
                $countryChanged = (int) $mobileProfile->country_id !== (int) $country->id;
                if ($countryChanged) {
                    $domain = $service->domainFor(SubscriberChannelModel::MOBILE, $country);
                    SubscriberDomain::ensureRelay($domain);
                    $mobileProfile->update([
                        'country_id' => $country->id,
                        'domain' => $domain,
                    ]);
                    $service->syncDefaults($sub->fresh());
                    $service->provision($sub->fresh(), $mobileProfile->fresh());
                }
            } else {
                $service->createChannel($sub, SubscriberChannelModel::MOBILE, $country);
            }
        } elseif ($mobileProfile) {
            $service->removeChannel($sub, SubscriberChannelModel::MOBILE);
        }

        if ($targetBooth && !$boothProfile) {
            $service->createChannel($sub, SubscriberChannelModel::BOOTH);
        } elseif (!$targetBooth && $boothProfile) {
            $service->removeChannel($sub, SubscriberChannelModel::BOOTH);
        }

        $service->syncDefaults($sub->fresh());

        return response()->json([
            'status' => true,
            'message' => 'Subscriber updated successfully',
            'data' => new SubscriberResource($sub->fresh())
        ]);
    }

    public function deleteSubscriber($id): JsonResponse
    {
        $sub = SubscribersModel::where('subscriberid', $id)->first();
        if (!$sub) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $authUsernames = SubscriberAuthModel::where('subscriberid', $sub->subscriberid)->pluck('authusername');

        SubscriberDestModel::whereIn('authusername', $authUsernames)->delete();
        PrepaidCredit::whereIn('authusername', $authUsernames)->delete();
        SubscriberAuthModel::where('subscriberid', $sub->subscriberid)->delete();
        SubscriberChannelModel::where('subscriberid', $sub->subscriberid)->delete();
        $sub->delete();

        return response()->json(['status' => true, 'message' => 'Subscriber and all related data deleted']);
    }

    public function listAuthUsers(): JsonResponse
    {
        $auths = SubscriberAuthModel::all();

        $profiles = SubscriberChannelModel::query()
            ->get()
            ->keyBy(fn ($profile) => $profile->subscriberid . ':' . $profile->channel);

        $result = $auths->map(function ($auth) use ($profiles) {
            $response = $this->booth()->getBalance($auth->authusername);
            $profile = $profiles->get($auth->subscriberid . ':' . ($auth->channel ?: 'mobile'));

            return [
                'id' => $auth->id,
                'subscriberid' => $auth->subscriberid,
                'channel' => $auth->channel ?: 'mobile',
                'domain' => $profile?->domain,
                'authusername' => $auth->authusername,
                'balance' => $this->booth()->extractBalance($response) ?? 0.00,
                'status' => $auth->status,
                'created_at' => $auth->created_at,
                'updated_at' => $auth->updated_at,
            ];
        });

        return response()->json(['data' => $result]);
    }

    public function showAuthUser($id): JsonResponse
    {
        $auth = SubscriberAuthModel::find($id);
        if (!$auth) {
            return response()->json(['message' => 'SIP account not found'], 404);
        }

        $response = $this->booth()->getBalance($auth->authusername);
        $profile = SubscriberChannelModel::where('subscriberid', $auth->subscriberid)
            ->where('channel', $auth->channel ?: 'mobile')
            ->first();

        return response()->json(['data' => [
            'id' => $auth->id,
            'subscriberid' => $auth->subscriberid,
            'channel' => $auth->channel ?: 'mobile',
            'domain' => $profile?->domain,
            'authusername' => $auth->authusername,
            'balance' => $this->booth()->extractBalance($response) ?? 0.00,
            'status' => $auth->status,
            'created_at' => $auth->created_at,
            'updated_at' => $auth->updated_at,
        ]]);
    }

    public function storeAuthUser(Request $request): JsonResponse
    {
        $request->validate([
            'subscriberid' => 'required|exists:subscribers,subscriberid',
            'channel' => 'required|in:mobile,booth',
            'authusername' => 'required|regex:/^\d+$/|unique:subscriber_auth,authusername',
            'authpassword' => 'nullable|string|min:' . SubscriberAuthModel::PASSWORD_LENGTH . '|max:255',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        $subscriber = SubscribersModel::where('subscriberid', $request->subscriberid)->first();
        if (!$subscriber) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $profile = $subscriber->channelProfile($request->channel);
        if (!$profile) {
            return response()->json(['message' => 'The subscriber does not have that channel.'], 422);
        }

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $request->subscriberid,
            'channel' => $request->channel,
            'authusername' => $request->authusername,
            'authpassword' => $request->authpassword ?? Str::random(SubscriberAuthModel::PASSWORD_LENGTH),
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'status' => true,
            'message' => 'SIP account created successfully',
            'data' => new SubscriberAuthResource($auth)
        ], 201);
    }

    public function updateAuthUser(Request $request, $id): JsonResponse
    {
        $auth = SubscriberAuthModel::find($id);
        if (!$auth) {
            return response()->json(['message' => 'SIP account not found'], 404);
        }

        $request->validate([
            'channel' => 'sometimes|in:mobile,booth',
            'authusername' => 'sometimes|regex:/^\d+$/|unique:subscriber_auth,authusername,' . $id,
            'authpassword' => 'nullable|string|min:' . SubscriberAuthModel::PASSWORD_LENGTH . '|max:255',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        $data = $request->only(['channel', 'authpassword', 'status']);
        if ($request->has('authusername')) {
            $data['authusername'] = $request->authusername;
        }
        if ($request->filled('authpassword')) {
            $data['authpassword'] = $request->authpassword;
        }

        $auth->update($data);

        return response()->json([
            'status' => true,
            'message' => 'SIP account updated successfully',
            'data' => new SubscriberAuthResource($auth->fresh())
        ]);
    }

    public function deleteAuthUser($id): JsonResponse
    {
        $auth = SubscriberAuthModel::find($id);
        if (!$auth) {
            return response()->json(['message' => 'SIP account not found'], 404);
        }

        SubscriberDestModel::where('authusername', $auth->authusername)->delete();
        PrepaidCredit::where('authusername', $auth->authusername)->delete();
        $auth->delete();

        return response()->json(['status' => true, 'message' => 'SIP account and all related data deleted']);
    }

    public function listDestinations(): JsonResponse
    {
        $dests = SubscriberDestModel::all();
        return response()->json(['data' => SubscriberDestResource::collection($dests)]);
    }

    public function showDestination($id): JsonResponse
    {
        $dest = SubscriberDestModel::find($id);
        if (!$dest) {
            return response()->json(['message' => 'Destination not found'], 404);
        }
        return response()->json(['data' => new SubscriberDestResource($dest)]);
    }

    public function storeDestination(Request $request): JsonResponse
    {
        $request->validate([
            'subscriberid' => 'required|exists:subscribers,subscriberid',
            'authusername' => 'required|exists:subscriber_auth,authusername',
            'destination' => 'required|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $authBelongsToSubscriber = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $request->subscriberid)
            ->exists();

        if (!$authBelongsToSubscriber) {
            return response()->json(['message' => 'SIP account does not belong to this subscriber'], 422);
        }

        $dest = SubscriberDestModel::create([
            'subscriberid' => $request->subscriberid,
            'authusername' => $request->authusername,
            'destination' => $request->destination,
            'status' => $request->status ?? 'active',
        ]);

        $this->booth()->addDest([
            'accesscode' => $request->authusername,
            'destination' => $request->destination,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Destination created successfully',
            'data' => new SubscriberDestResource($dest)
        ], 201);
    }

    public function updateDestination(Request $request, $id): JsonResponse
    {
        $dest = SubscriberDestModel::find($id);
        if (!$dest) {
            return response()->json(['message' => 'Destination not found'], 404);
        }

        $request->validate([
            'destination' => 'sometimes|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $data = $request->only(['destination', 'status']);
        $dest->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Destination updated successfully',
            'data' => new SubscriberDestResource($dest->fresh())
        ]);
    }

    public function deleteDestination($id): JsonResponse
    {
        $dest = SubscriberDestModel::find($id);
        if (!$dest) {
            return response()->json(['message' => 'Destination not found'], 404);
        }

        $this->booth()->delDest([
            'accesscode' => $dest->authusername,
            'destination' => $dest->destination,
        ]);

        $dest->delete();

        return response()->json(['status' => true, 'message' => 'Destination deleted']);
    }

    public function listPurchaseHistory(): JsonResponse
    {
        $credits = PrepaidCredit::orderBy('created_at', 'desc')->get();
        return response()->json(['data' => $credits]);
    }

    public function addCredits(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|exists:subscriber_auth,authusername',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $transactionId = 'ADM_TXN_' . strtoupper(Str::random(12));

        $credit = app(PrepaidCreditService::class)->credit(
            $request->authusername,
            (float) $request->amount,
            $transactionId,
            'admin',
        );

        $externalBalance = $this->booth()->getBalance($request->authusername);
        $settled = $credit->status === PrepaidCredit::STATUS_COMPLETED;

        return response()->json([
            'status' => true,
            'message' => $settled
                ? 'Credits added successfully'
                : 'Credits queued; the top-up will be retried.',
            'pending' => !$settled,
            'credit_status' => $credit->status,
            'balance' => $this->booth()->extractBalance($externalBalance) ?? 'N/A',
            'transaction_id' => $transactionId,
        ], 201);
    }

    public function listCountries(): JsonResponse
    {
        $countries = Country::ordered()->get();

        return response()->json(['data' => $countries]);
    }

    public function storeCountry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:countries,name',
            'iso_code' => 'nullable|string|max:3|unique:countries,iso_code',
            'dial_code' => 'nullable|string|max:8',
            'domain' => ['required', 'string', 'max:100', 'unique:countries,domain', 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        $validated['domain'] = strtolower(trim($validated['domain']));
        $validated['iso_code'] = $validated['iso_code'] ? strtoupper($validated['iso_code']) : null;
        $country = Country::create($validated);

        SubscriberDomain::ensureRelay($country->domain);

        return response()->json([
            'status' => true,
            'message' => 'Country created successfully',
            'data' => $country,
        ], 201);
    }

    public function updateCountry(Request $request, $id): JsonResponse
    {
        $country = Country::find($id);
        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100|unique:countries,name,' . $country->id,
            'iso_code' => 'nullable|string|max:3|unique:countries,iso_code,' . $country->id,
            'dial_code' => 'nullable|string|max:8',
            'domain' => ['sometimes', 'string', 'max:100', 'unique:countries,domain,' . $country->id, 'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i'],
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        if (isset($validated['domain'])) {
            $validated['domain'] = strtolower(trim($validated['domain']));
        }
        if (array_key_exists('iso_code', $validated)) {
            $validated['iso_code'] = $validated['iso_code'] ? strtoupper($validated['iso_code']) : null;
        }

        $country->update($validated);

        if (isset($validated['domain'])) {
            SubscriberDomain::ensureRelay($country->domain);
        }

        return response()->json([
            'status' => true,
            'message' => 'Country updated successfully',
            'data' => $country->fresh(),
        ]);
    }

    public function deleteCountry($id): JsonResponse
    {
        $country = Country::find($id);
        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        $inUse = SubscriberChannelModel::where('country_id', $country->id)->exists();
        if ($inUse) {
            return response()->json([
                'status' => false,
                'message' => 'This country is used by existing mobile accounts. Deactivate it instead.',
            ], 409);
        }

        $country->delete();

        return response()->json(['status' => true, 'message' => 'Country deleted']);
    }

    public function applyCountryDomain($id): JsonResponse
    {
        $country = Country::find($id);
        if (!$country) {
            return response()->json(['message' => 'Country not found'], 404);
        }

        $service = app(SubscriberChannelService::class);
        $profiles = SubscriberChannelModel::where('country_id', $country->id)
            ->where('channel', SubscriberChannelModel::MOBILE)
            ->get();

        SubscriberDomain::ensureRelay($country->domain);

        foreach ($profiles as $profile) {
            $profile->update(['domain' => $country->domain]);
            $subscriber = $profile->subscriber;

            if ($subscriber) {
                $service->syncDefaults($subscriber);
                $service->provision($subscriber->fresh(), $profile->fresh());
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Country domain applied to mobile accounts',
            'updated' => $profiles->count(),
        ]);
    }

    public function dashboard(): JsonResponse
    {
        $totalSubscribers = SubscribersModel::count();
        $totalAuthUsers = SubscriberAuthModel::count();
        $totalDestinations = SubscriberDestModel::count();
        $totalCreditsAmount = PrepaidCredit::where('status', 'completed')->sum('amount');
        $totalTransactions = PrepaidCredit::where('status', 'completed')->count();
        $recentSubscribers = SubscribersModel::orderBy('regdatetime', 'desc')->take(5)->get();
        $recentTransactions = PrepaidCredit::orderBy('created_at', 'desc')->take(10)->get();

        return response()->json([
            'status' => true,
            'data' => [
                'total_subscribers' => $totalSubscribers,
                'total_auth_users' => $totalAuthUsers,
                'total_destinations' => $totalDestinations,
                'total_credits_amount' => (float) $totalCreditsAmount,
                'total_transactions' => $totalTransactions,
                'recent_subscribers' => SubscriberResource::collection($recentSubscribers),
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $admin = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:admins,email,' . $admin->id,
            'password' => 'nullable|string|min:6',
        ]);

        $data = $request->only(['name', 'email']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $admin->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => new AdminResource($admin->fresh())
        ]);
    }
}
