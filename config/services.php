<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'mnotify' => [
        'key' => env('MNOTIFY_API_KEY'),
        'sender' => env('MNOTIFY_SENDER'),
    ],

    'paystack' => [
        'secret_key' => env('PAYSTACK_SECRET_KEY'),
        'public_key' => env('PAYSTACK_PUBLIC_KEY', ''),
        'base_url' => rtrim(env('PAYSTACK_BASE_URL', 'https://api.paystack.co'), '/'),
        'currency' => strtoupper(env('PAYSTACK_CURRENCY', 'GHS')),
        'timeout' => (int) env('PAYSTACK_TIMEOUT', 3),
        'fallback_email' => env('PAYSTACK_FALLBACK_EMAIL', ''),
        'mobile_callback_url' => env('PAYSTACK_MOBILE_CALLBACK_URL')
            ?: rtrim(env('APP_URL') ?: 'http://localhost', '/') . '/payment-complete',
    ],

    'booth' => [
        'base_url' => env('BOOTH_API_BASE_URL', 'http://63.250.47.51/altfonapp'),
        'provision_url' => env('BOOTH_PROVISION_URL', env('BOOTH_API_BASE_URL', 'http://63.250.47.51/altfonapp') . '/passjson.php'),
    ],

    'ussd' => [
        'internal_token' => env('USSD_INTERNAL_TOKEN'),
        'internal_ip_allowlist' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('USSD_INTERNAL_IP_ALLOWLIST', ''))
        ))),
        'internal_throttle_per_minute' => (int) env('USSD_INTERNAL_THROTTLE_PER_MINUTE', 1200),
        'recharge_enabled' => filter_var(env('USSD_RECHARGE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
        'recharge_min' => (float) env('USSD_RECHARGE_MIN', 1),
        'recharge_max' => (float) env('USSD_RECHARGE_MAX', 500),
        'recharge_rate_limit_max' => (int) env('USSD_RECHARGE_RATE_LIMIT_MAX', 5),
        'recharge_rate_limit_window_seconds' => (int) env('USSD_RECHARGE_RATE_LIMIT_WINDOW_SECONDS', 3600),
        'payment_provider' => env('USSD_PAYMENT_PROVIDER', 'fake'),
        'fake_payment_outcome' => env('USSD_FAKE_PAYMENT_OUTCOME', 'success'),
        'fake_payment_delay_seconds' => (int) env('USSD_FAKE_PAYMENT_DELAY_SECONDS', 2),
        'signup_url' => env('USSD_SIGNUP_URL', 'https://www.altfon.com'),
        'signup_sms_ttl_seconds' => (int) env('USSD_SIGNUP_SMS_TTL_SECONDS', 86400),
        'signup_sms_delay_seconds' => (int) env('USSD_SIGNUP_SMS_DELAY_SECONDS', 5),
    ],

];
