<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriberResource;
use App\Models\SubscriberAuthModel;
use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use App\Models\VoiceRelay;
use App\Services\BoothApiService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    private const OTP_DEFAULT_TTL_MINUTES = 10;
    private const OTP_REISSUE_TTL_MINUTES = 30;

    public function signup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50|unique:subscribers,username|unique:subscribers_temp,username',
            'password' => 'required|string|min:8|max:100',
            'confirm_password' => 'sometimes|string|same:password',
            'phonenumber' => 'required|string|max:20|unique:subscribers,phonenumber|unique:subscribers_temp,phonenumber',
            'fullname' => 'nullable|string|max:255',
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'emailaddress' => 'nullable|email|max:180|unique:subscribers,emailaddress|unique:subscribers_temp,emailaddress',
            'email' => 'nullable|email|max:180|unique:subscribers,emailaddress|unique:subscribers_temp,emailaddress',
            'country' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation errors', 'errors' => $validator->errors()], 422);
        }

        $fullname = $this->resolveFullname($request);
        $emailaddress = $request->input('emailaddress') ?? $request->input('email');

        $otp = sprintf('%06d', random_int(0, 999999));

        $tempSubscriber = SubscribersTempModel::create([
            'subscriberid' => 'TEMP_' . strtoupper(Str::random(10)),
            'fullname' => $fullname,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'phonenumber' => $request->phonenumber,
            'emailaddress' => $emailaddress,
            'country' => $request->country,
            'otp' => $otp,
            'otp_expiration' => Carbon::now()->addMinutes(self::OTP_DEFAULT_TTL_MINUTES),
            'status' => 'pending',
        ]);

        send_sms_mnotify($request->phonenumber, "Your OTP Code: $otp");

        if ($emailaddress) {
            send_email_otp($emailaddress, $otp);
        }

        return response()->json([
            'status' => true,
            'message' => 'Temp subscriber created. OTP sent',
            'debug_otp' => $otp,

        ], 201);
    }

    public function verify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phonenumber' => 'required_without:subscriberid|string',
            'subscriberid' => 'required_without:phonenumber|string',
            'otp' => 'required_without:code|string',
            'code' => 'required_without:otp|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Phone/Subscriber and OTP required'], 400);
        }

        $otp = $request->input('otp') ?? $request->input('code');

        $temp = SubscribersTempModel::query()
            ->when($request->phonenumber, fn ($q) => $q->where('phonenumber', $request->phonenumber))
            ->when($request->subscriberid, fn ($q) => $q->where('subscriberid', $request->subscriberid))
            ->where('otp', $otp)
            ->where('status', 'pending')
            ->latest()
            ->first();

        if (!$temp) {
            return response()->json(['status' => false, 'message' => 'Invalid OTP'], 404);
        }

        if (Carbon::parse($temp->otp_expiration)->isPast()) {
            return response()->json(['status' => false, 'message' => 'OTP expired'], 400);
        }

        $existing = SubscribersModel::where('username', $temp->username)
            ->orWhere('phonenumber', $temp->phonenumber)
            ->first();
        if ($existing) {
            return response()->json(['status' => false, 'message' => 'Subscriber already registered'], 409);
        }

        $subscriberId = 'SUB_' . strtoupper(Str::random(10));
        $subscriber = SubscribersModel::create([
            'subscriberid' => $subscriberId,
            'fullname' => $temp->fullname,
            'username' => $temp->username,
            'password' => $temp->password,
            'phonenumber' => $temp->phonenumber,
            'emailaddress' => $temp->emailaddress,
            'country' => $temp->country,
            'authusername' => null,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        $auth = $this->createDefaultSipAccount($subscriber);
        $this->provisionSubscriber($subscriber, $auth, $otp);

        $temp->delete();

        return response()->json([
            'status' => true,
            'message' => 'Subscriber verified and created',
            'data' => new SubscriberResource($subscriber->fresh())
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Username/Email and password required'], 400);
        }

        $identifier = trim($request->username);

        $tempUser = SubscribersTempModel::where('username', $identifier)
            ->orWhere('emailaddress', $identifier)
            ->orWhere('phonenumber', $identifier)
            ->latest()
            ->first();

        // If a pending registration exists, re-issue an OTP so the user can verify it.
        if ($tempUser) {
            $code = sprintf('%06d', random_int(0, 999999));
            $expiration = Carbon::now()->addMinutes(self::OTP_REISSUE_TTL_MINUTES);

            $tempUser->update([
                'otp' => $code,
                'otp_expiration' => $expiration,
            ]);

            send_sms_mnotify($tempUser->phonenumber, "Your new OTP Code: $code");
            if ($tempUser->emailaddress) {
                send_email_otp($tempUser->emailaddress, $code);
            }

            return response()->json([
                'status' => true,
                'data' => ['otp_code' => $code],
                'message' => 'A new OTP has been sent to your sms and email successfully',
            ]);
        }

        $subscriber = SubscribersModel::where(function ($query) use ($identifier) {
            $query->where('username', $identifier)
                ->orWhere('emailaddress', $identifier);
        })->first();

        if (!$subscriber || !Hash::check($request->password, $subscriber->password)) {
            return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
        }

        $token = auth('api')->login($subscriber);

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => new SubscriberResource($subscriber)
        ]);
    }

    public function regenerateOtp(Request $request, string $email): JsonResponse
    {
        $validator = Validator::make(['email' => $email], [
            'email' => 'required|email|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Invalid email address'], 422);
        }

        $tempUser = SubscribersTempModel::where('emailaddress', $email)
            ->orWhere('username', $email)
            ->latest()
            ->first();

        if (!$tempUser) {
            return response()->json(['status' => false, 'message' => 'Pending registration not found'], 404);
        }

        $code = sprintf('%06d', random_int(0, 999999));
        $expiration = Carbon::now()->addMinutes(self::OTP_REISSUE_TTL_MINUTES);

        $tempUser->update([
            'otp' => $code,
            'otp_expiration' => $expiration,
        ]);

        send_sms_mnotify($tempUser->phonenumber, "Your new OTP Code: $code");
        if ($tempUser->emailaddress) {
            send_email_otp($tempUser->emailaddress, $code);
        }

        return response()->json([
            'status' => true,
            'data' => ['otp_code' => $code],
            'message' => 'A new OTP has been sent to your sms and email successfully',
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

        $email = $request->input('email') ?? $request->input('emailaddress');

        $subscriber = SubscribersModel::where('emailaddress', $email)->first();

        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Email not found'], 404);
        }

        $token = sprintf('%06d', random_int(0, 999999));
        $subscriber->update([
            'password_reset_token' => $token,
            'password_reset_expiration' => Carbon::now()->addHour(),
        ]);

        send_sms_mnotify($subscriber->phonenumber, "Your password reset code: $token");
        if ($subscriber->emailaddress) {
            send_email_otp($subscriber->emailaddress, "Your password reset code is: $token");
        }

        return response()->json([
            'status' => true,
            'message' => 'Password reset code sent to your email/phone'
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'confirm_password' => 'sometimes|string|same:password',
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

    private function resolveFullname(Request $request): ?string
    {
        if ($request->filled('fullname')) {
            return $request->fullname;
        }

        $firstname = trim((string) $request->input('firstname'));
        $lastname = trim((string) $request->input('lastname'));

        return trim("$firstname $lastname");
    }

    private function createDefaultSipAccount(SubscribersModel $subscriber): SubscriberAuthModel
    {
        $authusername = $this->generateUniqueSipNumber();
        $authpassword = Str::random(8);

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $authusername,
            'authpassword' => $authpassword,
            'status' => 'active',
        ]);

        $subscriber->update(['authusername' => $authusername]);

        return $auth;
    }

    private function generateUniqueSipNumber(): string
    {
        $maxAttempts = 20;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            if (!SubscriberAuthModel::where('authusername', $candidate)->exists()) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not generate a unique SIP number');
    }

    private function provisionSubscriber(SubscribersModel $subscriber, SubscriberAuthModel $auth, string $code): void
    {
        $relay = $this->resolveRelayForCountry($subscriber->country);

        $payload = [
            'email' => $subscriber->emailaddress,
            'name' => $subscriber->fullname,
            'phone' => $subscriber->phonenumber,
            'authusername' => $auth->authusername,
            'authpassword' => $auth->authpassword,
            'domain' => $relay->domain ?? '',
            'code' => $code,
        ];

        app(BoothApiService::class)->provision(config('services.booth.provision_url'), $payload);
    }

    private function resolveRelayForCountry(?string $country): ?VoiceRelay
    {
        if ($country) {
            $relay = VoiceRelay::where('domain', 'like', "%{$country}%")->first();
            if ($relay) {
                return $relay;
            }
        }

        return VoiceRelay::first();
    }
}