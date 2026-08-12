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
    ],

    'booth' => [
        'base_url' => env('BOOTH_API_BASE_URL', 'http://63.250.47.51/altfonapp'),
        'provision_url' => env('BOOTH_PROVISION_URL', env('BOOTH_API_BASE_URL', 'http://63.250.47.51/altfonapp') . '/passjson.php'),
    ],

];
