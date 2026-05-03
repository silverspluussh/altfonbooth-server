<?php

namespace App\Http\Controllers;

use App\Http\Resources\AdminResource;
use App\Http\Resources\SubscriberResource;
use App\Http\Resources\SubscriberAuthResource;
use App\Models\AdminModel;
use App\Models\SubscribersModel;
use App\Models\SubscriberAuthModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends Controller
{
    /**
     * Admin Login.
     */
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

        $token = $admin->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'status' => true,
            'token' => $token,
            'admin' => new AdminResource($admin)
        ]);
    }

    /**
     * List all admins (Super Admin only).
     */
    public function index(): JsonResponse
    {
        $admins = AdminModel::all();
        return response()->json(AdminResource::collection($admins));
    }

    /**
     * Create a new admin (Super Admin only).
     */
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

    /**
     * Delete an admin (Super Admin only).
     */
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

    /**
     * List all subscribers (Admin access).
     */
    public function listSubscribers(): JsonResponse
    {
        $subs = SubscribersModel::all();
        return response()->json(SubscriberResource::collection($subs));
    }

    /**
     * List all auth users (Admin access).
     */
    public function listAuthUsers(): JsonResponse
    {
        $auths = SubscriberAuthModel::all();
        return response()->json(SubscriberAuthResource::collection($auths));
    }
}
