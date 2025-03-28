<?php

return [
    'auto_face_login' => env('AUTO_FACE_LOGIN', false),
    'field_rules' => [
        'email' => ['required', 'email', 'exists:users,email'],
        'mobile' => ['required', 'regex:/^09\d{9}$/', 'exists:users,mobile'],
        'user_id' => ['required', 'integer', 'exists:users,id'],
    ],
];
