<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\SubscriberResource;
use App\Http\Resources\SubscriberAuthResource;
use App\Http\Resources\SubscriberDestResource;
use App\Models\AdminModel;
use App\Models\SubscribersModel;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberDestModel;
use App\Models\PrepaidCredit;
use App\Services\BoothApiService;
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

        return response()->json([
            'status' => true,
            'token' => $token,
            'admin' => new AdminResource($admin)
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
            'country' => 'nullable|string|max:100',
            'password' => 'required|string|min:6',
        ]);

        $subscriberid = 'SUB' . strtoupper(Str::random(8));

        $sub = SubscribersModel::create([
            'subscriberid' => $subscriberid,
            'fullname' => $request->fullname,
            'username' => $request->username,
            'emailaddress' => $request->emailaddress,
            'phonenumber' => $request->phonenumber,
            'country' => $request->country,
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Subscriber created successfully',
            'data' => new SubscriberResource($sub)
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
            'country' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6',
            'switch_status' => 'nullable|string',
            'billing_acc_status' => 'nullable|string',
        ]);

        $data = $request->only(['fullname', 'username', 'emailaddress', 'phonenumber', 'country', 'switch_status', 'billing_acc_status']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $sub->update($data);

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
        $sub->delete();

        return response()->json(['status' => true, 'message' => 'Subscriber and all related data deleted']);
    }

    public function listAuthUsers(): JsonResponse
    {
        $auths = SubscriberAuthModel::all();

        $result = $auths->map(function ($auth) {
            $response = $this->booth()->getBalance($auth->authusername);

            return [
                'id' => $auth->id,
                'subscriberid' => $auth->subscriberid,
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

        return response()->json(['data' => [
            'id' => $auth->id,
            'subscriberid' => $auth->subscriberid,
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
            'authusername' => 'required|regex:/^\d+$/|unique:subscriber_auth,authusername',
            'authpassword' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        $subscriberExists = SubscribersModel::where('subscriberid', $request->subscriberid)->exists();
        if (!$subscriberExists) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $request->subscriberid,
            'authusername' => $request->authusername,
            'authpassword' => $request->authpassword ?? Str::random(8),
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
            'authusername' => 'sometimes|regex:/^\d+$/|unique:subscriber_auth,authusername,' . $id,
            'authpassword' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,suspended',
        ]);

        $data = $request->only(['authpassword', 'status']);
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

        $credit = PrepaidCredit::create([
            'authusername' => $request->authusername,
            'amount' => $request->amount,
            'transaction_id' => $transactionId,
            'status' => 'completed',
        ]);

        $this->booth()->topup($request->authusername, $request->amount);

        $externalBalance = $this->booth()->getBalance($request->authusername);

        return response()->json([
            'status' => true,
            'message' => 'Credits added successfully',
            'balance' => $this->booth()->extractBalance($externalBalance) ?? 'N/A',
            'transaction_id' => $transactionId,
        ], 201);
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
