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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SubscribersController extends Controller
{
    private function callBoothApi(string $endpoint, array $params): ?\Illuminate\Http\Client\Response
    {
        $baseUrl = rtrim(env('BOOTH_API_BASE_URL', 'http://63.250.47.51/altfonapp'), '/');

        try {
            $response = Http::asForm()
                ->timeout(10)
                ->connectTimeout(5)
                ->post("{$baseUrl}/{$endpoint}", $params);

            if (!$response->successful()) {
                \Log::warning('Booth API request failed', [
                    'endpoint' => $endpoint,
                    'params' => $params,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (\Exception $e) {
            \Log::error('Booth API request exception', [
                'endpoint' => $endpoint,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }


    public function index(): JsonResponse
    {
        // Protected: Only admins should see the full list. 
        // For now, we return empty or unauthorized to prevent data leaks.
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    public function show($id): JsonResponse
    {
        $subscriber = request()->user();
        
        // A subscriber should only be able to view their own profile.
        if ($subscriber->subscriberid != $id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json(new SubscriberResource($subscriber));
    }


    public function updateAuthUsername(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/|unique:subscriber_auth,authusername,' . ($request->user()->auth?->id ?? 'NULL'),
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
     * Delete a SIP account and all related destinations.
     */
    public function deleteAuthUser(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|string',
        ]);

        $subscriber = $request->user();
        $auth = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->first();

        if (!$auth) {
            return response()->json(['status' => false, 'message' => 'SIP account not found or unauthorized'], 404);
        }

        // Delete all related destinations
        SubscriberDestModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->delete();

        // Delete the SIP account
        $auth->delete();

        return response()->json([
            'status' => true,
            'message' => 'SIP account and all related destinations deleted'
        ]);
    }

    /**
     * Add a new auth user for a subscriber.
     * Auto-generates a unique 6-digit SIP number and a secure password.
     */
    public function addAuthUser(Request $request): JsonResponse
    {
        $subscriber = $request->user();

        // Generate a unique 6-digit auth username
        $maxAttempts = 20;
        $authusername = null;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $candidate = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            if (!SubscriberAuthModel::where('authusername', $candidate)->exists()) {
                $authusername = $candidate;
                break;
            }
        }

        if (!$authusername) {
            return response()->json(['status' => false, 'message' => 'Could not generate a unique SIP number. Please try again.'], 500);
        }

        // Auto-generate an 8-character alphanumeric password
        $authpassword = Str::random(8);

        $auth = SubscriberAuthModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $authusername,
            'authpassword' => $authpassword,
            'status' => 'active'
        ]);

        return response()->json([
            'status' => true,
            'message' => 'SIP Account created successfully',
            'authusername' => $authusername,
            'authpassword' => $authpassword
        ], 201);
    }


    public function addDest(Request $request): JsonResponse
    {
        $request->validate([
            'authusername' => 'required|regex:/^\d+$/',
            'destination' => 'required|string',
        ]);

        $subscriber = $request->user();

        // Security: Ensure the authusername belongs to the authenticated subscriber
        $authExists = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->exists();

        if (!$authExists) {
            return response()->json(['status' => false, 'message' => 'Unauthorized: This SIP account does not belong to you.'], 403);
        }

        $dest = SubscriberDestModel::create([
            'subscriberid' => $subscriber->subscriberid,
            'authusername' => $request->authusername,
            'destination' => $request->destination,
            'status' => 'active'
        ]);

        $this->callBoothApi('booth_add_dest.php', [
            'accesscode' => $request->authusername,
            'destination' => $request->destination,
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

        $this->callBoothApi('booth_del_dest.php', [
            'accesscode' => $request->authusername,
            'destination' => $request->destination,
        ]);

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

        $this->callBoothApi('booth_topup.php', [
            'accesscode' => $request->authusername,
            'amount' => $request->amount,
        ]);

        $externalBalance = $this->callBoothApi('booth_getbal.php', [
            'accesscode' => $request->authusername,
        ]);

        // 'balance' => $auth->fresh()->balance,
        return response()->json([
            'status' => true,
            'message' => 'Credits purchased successfully',
            'balance' => $externalBalance ? trim($externalBalance->body()) : 'N/A',
            'transaction_id' => $transactionId
        ]);
    }

    public function initializePayment(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'authusername' => 'required|exists:subscriber_auth,authusername',
                'amount' => 'required|numeric|min:1',
                'email' => 'nullable|email',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::warning('Payment initialization validation failed', [
                'errors' => $e->errors(),
                'input' => $request->only(['authusername', 'amount'])
            ]);
            return response()->json([
                'status' => false,
                'message' => 'Validation failed: ' . implode('; ', array_map(
                    fn($field, $msgs) => "$field: " . implode(', ', $msgs),
                    array_keys($e->errors()),
                    array_values($e->errors())
                ))
            ], 400);
        }

        $subscriber = $request->user();
        if (!$subscriber) {
            \Log::warning('Payment initialization attempted without authentication');
            return response()->json(['status' => false, 'message' => 'User not authenticated'], 401);
        }
        
        // Ensure the authusername belongs to the current subscriber
        $auth = SubscriberAuthModel::where('authusername', $request->authusername)
            ->where('subscriberid', $subscriber->subscriberid)
            ->first();

        if (!$auth) {
            return response()->json(['status' => false, 'message' => 'SIP account not found or unauthorized'], 404);
        }

        // Get email from request, subscriber profile, or fallback
        $email = $request->email ?? $subscriber->emailaddress;

        if (!$email) {
            return response()->json(['status' => false, 'message' => 'Email is required for payment. Please enter your email address or update it in Account Settings.'], 400);
        }

        $secretKey = config('services.paystack.secret_key');
        
        if (!$secretKey) {
            \Log::error('Paystack secret key not configured');
            return response()->json(['status' => false, 'message' => 'Payment configuration error'], 500);
        }
        
        try {
            $callbackUrl = config('app.frontend_url') . '/dashboard.html';
            
            $response = Http::withToken($secretKey)->post('https://api.paystack.co/transaction/initialize', [
                'amount' => $request->amount * 100, // Paystack expects amount in kobo
                'email' => $email,
                'callback_url' => $callbackUrl,
                'metadata' => [
                    'authusername' => $request->authusername,
                    'subscriberid' => $subscriber->subscriberid,
                ],
            ]);

            if ($response->successful()) {
                return response()->json([
                    'status' => true,
                    'data' => $response->json()['data']
                ]);
            }

            // Log error response from Paystack
            $errorData = $response->json();
            \Log::error('Paystack initialization failed', [
                'status' => $response->status(),
                'error' => $errorData
            ]);

            return response()->json([
                'status' => false,
                'message' => $errorData['message'] ?? 'Could not initialize payment with Paystack',
                'error' => $errorData
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Paystack API error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Payment service error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'reference' => 'required|string',
        ]);

        $secretKey = config('services.paystack.secret_key');
        
        if (!$secretKey) {
            \Log::error('Paystack secret key not configured');
            return response()->json(['status' => false, 'message' => 'Payment configuration error'], 500);
        }
        
        try {
            $response = Http::withToken($secretKey)->get("https://api.paystack.co/transaction/verify/{$request->reference}");

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if Paystack returned a success response
                if (!($responseData['status'] ?? false)) {
                    \Log::warning('Paystack verification returned false status', $responseData);
                    return response()->json([
                        'status' => false,
                        'message' => $responseData['message'] ?? 'Payment verification failed',
                    ], 400);
                }
                
                $data = $responseData['data'] ?? null;
                if (!$data) {
                    \Log::error('Paystack verification response missing data', $responseData);
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid payment verification response'
                    ], 400);
                }

                // Check if transaction was successful
                if (($data['status'] ?? null) !== 'success') {
                    \Log::warning('Payment not successful', ['transaction_status' => $data['status'] ?? 'unknown']);
                    return response()->json([
                        'status' => false,
                        'message' => 'Payment was not successful. Status: ' . ($data['status'] ?? 'unknown')
                    ], 400);
                }

                $authusername = $data['metadata']['authusername'] ?? null;
                if (!$authusername) {
                    \Log::error('Paystack response missing authusername in metadata', $data);
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid payment metadata'
                    ], 400);
                }

                $amount = $data['amount'] / 100; // Convert back from kobo

                // Check if this reference has already been processed to prevent double-crediting
                $existing = PrepaidCredit::where('transaction_id', $request->reference)->first();
                if ($existing) {
                    \Log::info('Payment already processed', ['reference' => $request->reference]);

                    $externalBalance = $this->callBoothApi('booth_getbal.php', [
                        'accesscode' => $authusername,
                    ]);

                    // $auth = SubscriberAuthModel::where('authusername', $authusername)->first();
                    return response()->json([
                        'status' => true,
                        'message' => 'Payment already processed',
                        // 'balance' => $auth?->balance ?? 0,
                        'balance' => $externalBalance ? trim($externalBalance->body()) : 'N/A',
                        'transaction_id' => $request->reference
                    ]);
                }

                // Credit the account
                $credit = PrepaidCredit::create([
                    'authusername' => $authusername,
                    'amount' => $amount,
                    'transaction_id' => $request->reference,
                    'status' => 'completed'
                ]);

                $this->callBoothApi('booth_topup.php', [
                    'accesscode' => $authusername,
                    'amount' => $amount,
                ]);

                $externalBalance = $this->callBoothApi('booth_getbal.php', [
                    'accesscode' => $authusername,
                ]);

                // Get the updated balance (must refresh the auth model to get updated balance)
                // $auth = SubscriberAuthModel::where('authusername', $authusername)->first();

                \Log::info('Payment verified and account credited', [
                    'authusername' => $authusername,
                    'amount' => $amount,
                    'reference' => $request->reference
                ]);

                return response()->json([
                    'status' => true,
                    'message' => 'Payment verified and account credited',
                    // 'balance' => $auth?->balance ?? 0,
                    'balance' => $externalBalance ? trim($externalBalance->body()) : 'N/A',
                    'transaction_id' => $request->reference
                ]);
            }
            
            // Handle unsuccessful HTTP response
            $errorData = $response->json();
            \Log::error('Paystack verification HTTP error', [
                'status' => $response->status(),
                'error' => $errorData
            ]);

            return response()->json([
                'status' => false,
                'message' => $errorData['message'] ?? 'Payment verification failed',
                'error' => $errorData
            ], 400);
        } catch (\Exception $e) {
            \Log::error('Paystack verification exception: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => false,
                'message' => 'Payment verification error: ' . $e->getMessage()
            ], 500);
        }
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

    public function getPaymentConfig(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'paystack_public_key' => ''
        ]);
    }

    public function getBalance(Request $request): JsonResponse
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

        $response = $this->callBoothApi('booth_getbal.php', [
            'accesscode' => $request->authusername,
        ]);

        return response()->json([
            'status' => true,
            'balance' => $response ? trim($response->body()) : 'N/A',
        ]);
    }
}
