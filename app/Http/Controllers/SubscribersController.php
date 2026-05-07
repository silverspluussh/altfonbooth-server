<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriberAuthResource;
use App\Http\Resources\SubscriberDestResource;
use App\Http\Resources\SubscriberResource;
use App\Models\PrepaidCredit;
use App\Models\SubscriberAuthModel;
use App\Models\SubscriberDestModel;
use App\Models\SubscribersModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SubscribersController extends Controller
{

    public function index(): JsonResponse
    {
        $subs = SubscribersModel::all();
        return response()->json(SubscriberResource::collection($subs));
    }


    public function show($id): JsonResponse
    {
        $sub = SubscribersModel::find($id);
        if (!$sub) {
            return response()->json(['message' => 'Subscriber not found'], 404);
        }
        return response()->json(new SubscriberResource($sub));
    }


    public function updateAuthUsername(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
        ]);

        $subscriber = $request->user();
        
        // 1. Update the main subscriber record
        $subscriber->update(['authusername' => $request->authusername]);

        // 2. Update or Create the detailed auth record
        $existing = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)->first();

        if ($existing) {
            $existing->update(['authusername' => $request->authusername]);
        } else {
            SubscriberAuthModel::create([
                'subscriberid' => $subscriber->subscriberid,
                'authusername' => $request->authusername,
                'authpassword' => '',
                'status' => 'active'
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Auth username updated successfully in both user profile and auth records'
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $subscriber = $request->user();
        
        $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'emailaddress' => 'sometimes|email|unique:subscribers,emailaddress,' . $subscriber->subscriberid . ',subscriberid',
            'password' => 'sometimes|nullable|string|min:6'
        ]);

        $data = $request->only(['fullname', 'emailaddress']);
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $subscriber->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'user' => new SubscriberResource($subscriber)
        ]);
    }

    public function updateAuthAccount(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:subscriber_auth,id',
            'authusername' => 'required|regex:/^\d+$/',
            'authpassword' => 'nullable|string'
        ]);

        $subscriber = $request->user();
        $auth = SubscriberAuthModel::where('id', $request->id)
            ->where('subscriberid', $subscriber->subscriberid)
            ->first();

        if (!$auth) {
            return response()->json(['status' => false, 'message' => 'SIP account not found or unauthorized'], 404);
        }

        // Check if new authusername is taken by someone else
        $taken = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('id', '!=', $request->id)
            ->exists();
        
        if ($taken) {
            return response()->json(['status' => false, 'message' => 'This SIP Number is already taken'], 422);
        }

        $auth->update([
            'authusername' => $request->authusername,
            'authpassword' => $request->authpassword ?? ''
        ]);

        return response()->json([
            'status' => true,
            'message' => 'SIP Account updated successfully'
        ]);
    }


    public function listAuthUsers(Request $request): JsonResponse
    {
        $subscriber = $request->user();
        $auths = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)->get();
        return response()->json(SubscriberAuthResource::collection($auths));
    }

    /**
     * Add a new auth user for a subscriber.
     */
    public function addAuthUser(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/|unique:subscriber_auth,authusername',
            'password' => 'nullable|string'
        ]);

        $plainPassword = $request->password ?? '';
        $subscriber = $request->user();

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $request->authusername,
            'authpassword' => $plainPassword,
            'status' => 'active'
        ]);

        return response()->json([
            'status' => true,
            'authusername' => $request->authusername,
            'authpassword' => $plainPassword
        ], 201);
    }


    public function addDest(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
            'destination' => 'required|string',
        ]);

        $subscriber = $request->user();

        $dest = SubscriberDestModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $request->authusername,
            'destination' => $request->destination,
            'status' => 'active'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Destination added successfully',
            'data' => [
                'id' => $dest->id,
                'authusername' => $dest->authusername,
                'destination' => $dest->destination,
                'status' => $dest->status
            ]
        ], 201);
    }


    public function deleteDest(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
            'destination' => 'required|string',
        ]);

        $subscriber = $request->user();

        $deleted = SubscriberDestModel::where('subscriberid', $subscriber->subscriberid)
            ->where('authusername', $request->authusername)
            ->where('destination', $request->destination)
            ->delete();

        return response()->json([
            'status' => true,
            'message' => $deleted ? 'Destination deleted' : 'Not found'
        ]);
    }


    public function myDestinations(Request $request): JsonResponse
    {
        $subscriber = $request->user();
        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $list = SubscriberDestModel::where('subscriberid', $subscriber->subscriberid)->get();

        return response()->json([
            'status' => true,
            'count' => count($list),
            'destinations' => SubscriberDestResource::collection($list)
        ]);
    }

    public function purchaseCredits(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|exists:subscriber_auth,authusername',
            'amount' => 'required|numeric|min:1',
        ]);

        $subscriber = $request->user();
        
        // Ensure the authusername belongs to the current subscriber
        $auth = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->first();

        if (!$auth) {
            return response()->json(['status' => false, 'message' => 'SIP account not found or unauthorized'], 404);
        }

        $transactionId = 'TXN_' . strtoupper(Str::random(12));

        $credit = PrepaidCredit::create([
            'authusername' => $request->authusername,
            'amount' => $request->amount,
            'transaction_id' => $transactionId,
            'status' => 'completed'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Credits purchased successfully',
            'balance' => $auth->fresh()->balance,
            'transaction_id' => $transactionId
        ]);
    }

    public function getPurchaseHistory(Request $request): JsonResponse
    {
        $subscriber = $request->user();
        
        $authUsernames = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)
            ->pluck('authusername');
            
        $history = PrepaidCredit::whereIn('authusername', $authUsernames)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'status' => true,
            'data' => $history
        ]);
    }
}
