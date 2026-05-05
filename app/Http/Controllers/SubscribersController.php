<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriberAuthResource;
use App\Http\Resources\SubscriberDestResource;
use App\Http\Resources\SubscriberResource;
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
            'authusername' => 'required|string',
        ]);

        $subscriber = $request->user();
        
        // 1. Update the main subscriber record
        $subscriber->update(['authusername' => $request->authusername]);

        // 2. Update or Create the detailed auth record
        $existing = SubscriberAuthModel::where('subscriberid', $subscriber->subscriberid)->first();

        if ($existing) {
            $existing->update(['authusername' => $request->authusername]);
        } else {
            $plainPassword = Str::random(10);
            SubscriberAuthModel::create([
                'subscriberid' => $subscriber->subscriberid,
                'authusername' => $request->authusername,
                'authpassword' => $plainPassword,
                'status' => 'active'
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Auth username updated successfully in both user profile and auth records'
        ]);
    }


    public function listAuthUsers(): JsonResponse
    {
        $auths = SubscriberAuthModel::all();
        return response()->json(SubscriberAuthResource::collection($auths));
    }

    /**
     * Add a new auth user for a subscriber.
     */
    public function addAuthUser(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|string|unique:subscriber_auth,authusername',
            'password' => 'nullable|string'
        ]);

        $plainPassword = $request->password ?: Str::random(10);
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
            'authusername' => 'required|string',
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
            'authusername' => 'required|string',
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
}
