<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubscriberResource;
use App\Models\Country;
use App\Models\SubscriberChannelModel;
use App\Models\SubscribersModel;
use App\Models\SubscribersTempModel;
use App\Services\ChannelSetupTokenService;
use App\Services\SubscriberChannelService;
use App\Support\Msisdn;
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
        $channel = $this->resolveChannel($request);
        $channels = app(SubscriberChannelService::class);

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:50',
            'password' => 'required|string|min:8|max:100',
            'confirm_password' => 'sometimes|string|same:password',
            'phonenumber' => 'required|string|max:20',
            'fullname' => 'nullable|string|max:255',
            'firstname' => 'nullable|string|max:255',
            'lastname' => 'nullable|string|max:255',
            'emailaddress' => 'nullable|email|max:180',
            'email' => 'nullable|email|max:180',
            'country' => 'nullable|string|max:100',
            'country_id' => 'nullable|integer',
            'country_code' => 'nullable|string|max:3',
            'channel' => 'nullable|in:mobile,booth',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Validation errors', 'errors' => $validator->errors()], 422);
        }

        $country = $channels->resolveCountry($request->all());
        if ($channel === SubscriberChannelModel::MOBILE && !$country) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => ['country_id' => ['Please select a valid country.']],
            ], 422);
        }

        $fullname = $this->resolveFullname($request);
        $emailaddress = $request->input('emailaddress') ?? $request->input('email');
        $phonenumber = Msisdn::toStorage($request->input('phonenumber'));
        $phoneCandidates = Msisdn::storageCandidates($request->input('phonenumber'));

        if ($phonenumber === null) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => ['phonenumber' => ['A valid phone number is required.']],
            ], 422);
        }

        // One identity can have both channels, but never twice within the same channel.
        $existingSubscriber = SubscribersModel::where('username', $request->username)
            ->orWhereIn('phonenumber', $phoneCandidates)
            ->when($emailaddress, fn ($q) => $q->orWhere('emailaddress', $emailaddress))
            ->first();

        if ($existingSubscriber) {
            $message = $existingSubscriber->hasChannel($channel)
                ? 'An account with these details already exists. Please sign in instead.'
                : 'An account with these details already exists. Sign in to create a ' . $channel . ' account.';

            return response()->json(['status' => false, 'message' => $message], 409);
        }

        $otp = sprintf('%06d', random_int(0, 999999));
        $domain = $channels->domainFor($channel, $country);

        $attributes = [
            'channel' => $channel,
            'fullname' => $fullname,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'phonenumber' => $phonenumber,
            'emailaddress' => $emailaddress,
            'country' => $channel === SubscriberChannelModel::MOBILE ? $country?->name : null,
            'country_id' => $channel === SubscriberChannelModel::MOBILE ? $country?->id : null,
            'domain' => $domain,
            'otp' => $otp,
            'otp_expiration' => Carbon::now()->addMinutes(self::OTP_DEFAULT_TTL_MINUTES),
            'status' => 'pending',
        ];

        // Reuse a still-pending registration (missed/expired OTP, re-submit) instead
        // of failing on a duplicate row that the user cannot clear themselves.
        $pending = SubscribersTempModel::where('channel', $channel)
            ->where(function ($query) use ($request, $phoneCandidates, $emailaddress) {
                $query->where('username', $request->username)
                    ->orWhereIn('phonenumber', $phoneCandidates)
                    ->when($emailaddress, fn ($q) => $q->orWhere('emailaddress', $emailaddress));
            })
            ->latest()
            ->first();

        if ($pending) {
            $pending->update($attributes);
        } else {
            SubscribersTempModel::create([
                'subscriberid' => 'TEMP_' . strtoupper(Str::random(10)),
            ] + $attributes);
        }

        queue_otp_sms($phonenumber, "Your OTP Code: $otp");

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
        $phoneCandidates = Msisdn::storageCandidates($request->phonenumber);

        $temp = SubscribersTempModel::query()
            ->when($phoneCandidates !== [], fn ($q) => $q->whereIn('phonenumber', $phoneCandidates))
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

        $channel = $temp->channel ?: SubscriberChannelModel::MOBILE;

        $existing = SubscribersModel::where('username', $temp->username)
            ->orWhereIn('phonenumber', Msisdn::storageCandidates($temp->phonenumber))
            ->first();
        if ($existing) {
            $message = $existing->hasChannel($channel)
                ? 'Subscriber already registered'
                : 'An account already exists. Please sign in to create your ' . $channel . ' account.';

            return response()->json(['status' => false, 'message' => $message], 409);
        }

        $channels = app(SubscriberChannelService::class);
        $country = $channel === SubscriberChannelModel::MOBILE
            ? $channels->resolveCountry([
                'country_id' => $temp->country_id,
                'country' => $temp->country,
            ])
            : null;

        if ($channel === SubscriberChannelModel::MOBILE && !$country) {
            return response()->json([
                'status' => false,
                'message' => 'A valid country is required.',
            ], 422);
        }

        $subscriberId = 'SUB_' . strtoupper(Str::random(10));
        $subscriber = SubscribersModel::create([
            'subscriberid' => $subscriberId,
            'fullname' => $temp->fullname,
            'username' => $temp->username,
            'password' => $temp->password,
            'phonenumber' => Msisdn::toStorage($temp->phonenumber) ?? $temp->phonenumber,
            'emailaddress' => $temp->emailaddress,
            'country' => $country?->name,
            'country_id' => $country?->id,
            'domain' => $temp->domain,
            'has_mobile_account' => false,
            'has_booth_account' => false,
            'authusername' => null,
            'switch_status' => 'active',
            'billing_acc_status' => 'active',
        ]);

        $channels->createChannel($subscriber, $channel, $country, $otp);

        $subscriber = $subscriber->fresh();
        $subscriber->active_channel = $channel;

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
            'username' => 'required|string|max:50',
            'password' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Username/Email and password required'], 400);
        }

        $channel = $this->resolveChannel($request);
        $identifier = trim($request->username);
        $phoneCandidates = Msisdn::storageCandidates($identifier);

        $tempUser = SubscribersTempModel::where('channel', $channel)
            ->where(function ($query) use ($identifier, $phoneCandidates) {
                $query->where('username', $identifier)
                    ->orWhere('emailaddress', $identifier)
                    ->when($phoneCandidates !== [], fn ($q) => $q->orWhereIn('phonenumber', $phoneCandidates));
            })
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

            queue_otp_sms($tempUser->phonenumber, "Your new OTP Code: $code");
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

        if (!$subscriber->hasChannel($channel)) {
            $setupToken = app(ChannelSetupTokenService::class)->issue($subscriber, $channel);
            $message = $channel === SubscriberChannelModel::MOBILE
                ? 'You do not have a mobile account yet. Please create one.'
                : 'You do not have a booth account yet. Please create one.';

            return response()->json([
                'status' => false,
                'message' => $message,
                'error' => [
                    'code' => 403,
                    'message' => $message,
                    'reason' => $channel . '_account_required',
                    'details' => [
                        'channel' => $channel,
                        'setup_token' => $setupToken,
                    ],
                ],
                'reason' => $channel . '_account_required',
            ], 403);
        }

        $token = auth('api')->claims(['channel' => $channel])->login($subscriber);
        $ttl = (int) config('jwt.ttl', 720);

        $subscriber->active_channel = $channel;

        return response()->json([
            'status' => true,
            'message' => 'Login successful',
            'token' => $token,
            'expires_in' => $ttl * 60,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            'user' => new SubscriberResource($subscriber)
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        try {
            $channel = null;

            if ($rawToken = $request->bearerToken()) {
                try {
                    $claims = app('tymon.jwt.provider.jwt')->decode($rawToken);
                    $channel = $claims['channel'] ?? null;
                } catch (\Throwable $exception) {
                    $channel = null;
                }
            }

            $token = $channel
                ? auth('api')->claims(['channel' => $channel])->refresh()
                : auth('api')->refresh();
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
            auth('api')->logout();
        } catch (\Throwable $exception) {
            // The token may already be expired or blacklisted; clearing the
            // client session is still the correct outcome.
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out',
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
            'phonenumber' => 'required_without:emailaddress,email|string|max:20',
            'emailaddress' => 'required_without:phonenumber|nullable|email|max:180',
            'email' => 'required_without:phonenumber|nullable|email|max:180',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => 'Phone number required'], 400);
        }

        $subscriber = null;

        if ($request->filled('phonenumber')) {
            $subscriber = SubscribersModel::whereIn(
                'phonenumber',
                Msisdn::storageCandidates($request->phonenumber)
            )->first();
        } else {
            $email = $request->input('email') ?? $request->input('emailaddress');
            $subscriber = SubscribersModel::where('emailaddress', $email)->first();
        }

        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Account not found'], 404);
        }

        $token = sprintf('%06d', random_int(0, 999999));
        $subscriber->update([
            'password_reset_token' => $token,
            'password_reset_expiration' => Carbon::now()->addHour(),
        ]);

        queue_otp_sms($subscriber->phonenumber, "Your password reset code: $token");
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

    public function createChannel(Request $request): JsonResponse
    {
        $tokens = app(ChannelSetupTokenService::class);
        $channels = app(SubscriberChannelService::class);
        $channel = strtolower(trim((string) $request->input('channel', $this->resolveChannel($request))));
        if (!in_array($channel, [SubscriberChannelModel::MOBILE, SubscriberChannelModel::BOOTH], true)) {
            return response()->json(['status' => false, 'message' => 'Invalid channel'], 422);
        }

        $setup = $tokens->consume((string) $request->bearerToken());
        if (!$setup) {
            return response()->json(['status' => false, 'message' => 'This setup link is invalid or has expired.'], 401);
        }

        if (($setup['channel'] ?? null) !== $channel) {
            return response()->json(['status' => false, 'message' => 'This setup link is for a different account type.'], 403);
        }

        $subscriber = SubscribersModel::where('subscriberid', $setup['subscriberid'])->first();
        if (!$subscriber) {
            return response()->json(['status' => false, 'message' => 'Account not found'], 404);
        }

        if ($subscriber->hasChannel($channel)) {
            return response()->json(['status' => false, 'message' => 'This account already has that channel.'], 409);
        }

        $country = $channel === SubscriberChannelModel::MOBILE
            ? $channels->resolveCountry($request->all())
            : null;

        if ($channel === SubscriberChannelModel::MOBILE && !$country) {
            return response()->json([
                'status' => false,
                'message' => 'Validation errors',
                'errors' => ['country_id' => ['Please select a valid country.']],
            ], 422);
        }

        try {
            $channels->createChannel($subscriber, $channel, $country);
        } catch (\RuntimeException $exception) {
            return response()->json(['status' => false, 'message' => $exception->getMessage()], 409);
        }

        $subscriber = $subscriber->fresh();
        $token = auth('api')->claims(['channel' => $channel])->login($subscriber);
        $ttl = (int) config('jwt.ttl', 720);
        $subscriber->active_channel = $channel;

        return response()->json([
            'status' => true,
            'message' => ucfirst($channel) . ' account created successfully',
            'token' => $token,
            'expires_in' => $ttl * 60,
            'expires_at' => now()->addMinutes($ttl)->toIso8601String(),
            'user' => new SubscriberResource($subscriber),
        ]);
    }

    private function resolveChannel(Request $request): string
    {
        $channel = strtolower(trim((string) $request->input('channel', 'booth')));

        return $channel === SubscriberChannelModel::MOBILE
            ? SubscriberChannelModel::MOBILE
            : SubscriberChannelModel::BOOTH;
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

}