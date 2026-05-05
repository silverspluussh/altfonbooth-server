<?php

if (!function_exists('send_sms_mnotify')) {

    function send_sms_mnotify($to, $message)
    {
        $apiKey = config('services.mnotify.key');
        $sender = config('services.mnotify.sender');

        // Format phone number (e.g., 024XXXXXXX to 23324XXXXXXX)
        $to = preg_replace('/[^0-9]/', '', $to);
        if (str_starts_with($to, '0') && strlen($to) == 10) {
            $to = '233' . substr($to, 1);
        }

        try {
            $response = \Illuminate\Support\Facades\Http::post("https://api.mnotify.com/api/sms/quick?key=$apiKey", [
                'recipient' => [$to], // mNotify often expects an array of recipients
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
