<?php

return [
    'auto_face_login' => env('AUTO_FACE_LOGIN', false),
    'field_rules' => [
        'email' => ['required', 'email', 'exists:users,email'],
        'mobile' => ['required', 'regex:/^09\d{9}$/', 'exists:users,mobile'],
        'user_id' => ['required', 'integer', 'exists:users,id'],
        'id_value' => ['required', 'string'],
        'id_type' => ['required', 'string'],
    ],
    'field_extraction' => [
        'confidence' => env('FIELD_EXTRACTION_CONFIDENCE', 'high'),
    ],
    'result_cache_ttl' => env('RESULT_CACHE_TTL', 30),
    'payment' => [
        'server' => [
//            'url' => env('PAYMENT_SERVER_URL', 'https://fibi.disburse.cash/api/generate-qr'),
            'url' => env('PAYMENT_SERVER_URL', 'https://fibi.seqrcode.net/api/generate-qr'),
            'token' => env('PAYMENT_SERVER_TOKEN')
        ],
        'qr-code' => [
            'amount' => env('PAYMENT_AMOUNT', 50),
            'increment' => env('PAYMENT_INCREMENT', 50)
        ],
    ],
    'system' => [
        'user' => [
            'id_type' => env('SYSTEM_USER_ID_TYPE', 'phl_dl'),
            'id_value' => env('SYSTEM_USER_ID_VALUE', 'N01-87-049586'),
        ],
        'allowed_as_vendor' => env('ALLOWED_AS_VENDOR', false),
    ]
];
