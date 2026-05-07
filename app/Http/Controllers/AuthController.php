<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriberResource;
use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:subscribers,username|unique:subscribers_temp,username',
            'password' => 'required|string|min:6',
            'phonenumber' => 'required|string|unique:subscribers,phonenumber|unique:subscribers_temp,phonenumber',
            'fullname' => 'nullable|string',
            'emailaddress' => 'nullable|email|unique:subscribers,emailaddress|unique:subscribers_temp,emailaddress',
            'country' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation errors', 'errors' => $validator->errors()], 422);
        }

        $otp = sprintf('%06d', random_int(0, 999999));

        $tempSubscriber = SubscribersTempModel::create([
            'subscriberid' => 'TEMP_' . strtoupper(Str::random(10)),
            'fullname' => $request->fullname,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'phonenumber' => $request->phonenumber,
            'emailaddress' => $request->emailaddress,
            'country' => $request->country,
            'otp' => $otp,
            'otp_expiration' => Carbon::now()->addMinutes(5),
            'status' => 'pending',
        ]);

        send_sms_mnotify($request->phonenumber, "Your OTP Code: $otp");

        // if ($request->emailaddress) {
        //     send_email_otp($request->emailaddress, $otp);
        // }

        return response()->json([
            'status' => true,
            'message' => 'Temp subscriber created. OTP sent',
            'debug_otp' => $otp,

        ], 201);
    }


    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phonenumber' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Phone and OTP required'], 400);
        }

        $temp = SubscribersTempModel::where('phonenumber', $request->phonenumber)
            ->where('otp', $request->otp)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$temp) {
            return response()->json(['status' => false, 'message' => 'Invalid OTP'], 404);
        }

        if (Carbon::parse($temp->otp_expiration)->isPast()) {
            return response()->json(['status' => false, 'message' => 'OTP expired'], 400);
        }

        // Move to subscribers table
        $subscriberId = 'SUB_' . strtoupper(Str::random(10));
        $subscriber = SubscribersModel::create([
            'subscriberid' => $subscriberId,
            'fullname' => $temp->fullname,
            'username' => $temp->username,
            'password' => $temp->password,
            'phonenumber' => $temp->phonenumber,
            'emailaddress' => $temp->emailaddress,
            'country' => $temp->country,
            'authusername' => $temp->username,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        $temp->delete();

        return response()->json([
            'status' => true,
            'message' => 'Subscriber verified and created',
            'data' => new SubscriberResource($subscriber)
        ]);
    }


    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Username/Email and password required'], 400);
        }

        $identifier = trim($request->username);
        $subscriber = SubscribersModel::where(function ($query) use ($identifier) {
            $query->where('username', $identifier)
                ->orWhere('emailaddress', $identifier);
        })->first();

        if (!$subscriber || !Hash::check($request->password, $subscriber->password)) {
            return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = $subscriber->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => new SubscriberResource($subscriber)
        ]);
    }


    public function requestPasswordReset(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Email required'], 400);
        }

        $subscriber = SubscribersModel::where('emailaddress', $request->email)->first();

        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Email not found'], 404);
        }

        $token = Str::random(32);
        $subscriber->update([
            'password_reset_token' => $token,
            'password_reset_expiration' => Carbon::now()->addHour(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password reset token sent to your email/phone'
        ]);
    }


    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Token and password required'], 400);
        }

        $subscriber = SubscribersModel::where('password_reset_token', $request->token)->first();

        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Invalid token'], 404);
        }

        if (Carbon::parse($subscriber->password_reset_expiration)->isPast()) {
            return response()->json(['status' => false, 'message' => 'Token expired'], 400);
        }

        $subscriber->update([
            'password' => Hash::make($request->password),
            'password_reset_token' => null,
            'password_reset_expiration' => null,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Password reset successfully'
        ]);
    }
}
