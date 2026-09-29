<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        'model' => 'gemini-2.5-flash', // 使用するモデル
    ],
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY', ''),
        'static_key' => env('GOOGLE_MAPS_STATIC_API_KEY') ?: env('GOOGLE_MAPS_API_KEY', ''),
        'marker_icon_url' => env('GOOGLE_MAPS_MARKER_ICON_URL', ''),
    ],

    'comfyui' => [
        'base_url' => env('COMFYUI_BASE_URL', 'http://10.210.17.220:8188'),
    ],
];
