<?php

return [
    'campaign_url' => env('CAMPAIGN_URL', 'https://kwyc-check.net/campaign-checkin/9ccef822-4209-4e0a-bb40-232da5cafdf1'),
    'credential' => [
        'base_url' => env("HYPERVERGE_APP_BASEURL", "https://sgp.idv.hyperverge.co/v1"),
        'appId' => env("HYPERVERGE_API_ID"),
        'appKey' => env("HYPERVERGE_API_KEY"),
        'defaultFileStorePath' => env("HYPERVERGE_DEFAULT_FILESTORE", 'image')
    ],
];
