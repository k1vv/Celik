<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        // Verified working against a real API key as of this session.
        // 'gemini-2.5-flash'/'gemini-2.5-flash-lite' both 404 as "no longer
        // available to new users" despite still being listed by the models
        // endpoint - Google's error response itself pointed at this model.
        // 'gemini-flash-latest' (the "current recommended" alias) was
        // consistently 503 (overloaded) across retries at time of testing.
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
    ],

    'n8n' => [
        'submission_webhook_url' => env('N8N_SUBMISSION_WEBHOOK_URL'),
        'secret' => env('N8N_WEBHOOK_SECRET'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
