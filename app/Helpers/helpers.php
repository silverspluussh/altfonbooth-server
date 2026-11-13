<?php

if (!function_exists('send_sms_mnotify')) {

    function send_sms_mnotify($to, $message)
    {
        $apiKey = config('services.mnotify.key');
        $sender = config('services.mnotify.sender');

        // Canonicalize Ghanaian numbers to 233XXXXXXXXX so a malformed or
        // legacy stored value (e.g. 2330XXXXXXXXX) still reaches the gateway
        // in a format it accepts.
        $recipient = \App\Support\Msisdn::toInternational((string) $to);

        if ($recipient === null) {
            $recipient = preg_replace('/[^0-9]/', '', (string) $to) ?? '';
        }

        $to = $recipient;

        try {
            $response = \Illuminate\Support\Facades\Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->post("https://api.mnotify.com/api/sms/quick?key={$apiKey}", [
                    'recipient' => [$to],
                    'sender' => $sender,
                    'message' => $message,
                ]);

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error('SMS sending failed: ' . $response->body());
            }

            return $response->body();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SMS sending failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('send_email_otp')) {

    function send_email_otp($email, $otp)
    {
        try {
            \Illuminate\Support\Facades\Mail::to($email)->queue(new \App\Mail\OtpMail($otp));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Email sending failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('queue_otp_sms')) {

    /**
     * Dispatch the SMS off the request path so a slow/unreachable gateway
     * can never block signup, login or password-reset requests.
     */
    function queue_otp_sms($to, $message)
    {
        try {
            \App\Jobs\SendOtpSmsJob::dispatch($to, $message);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('SMS dispatch failed: ' . $e->getMessage());
        }
    }
}
