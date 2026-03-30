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


    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Internal API token for Python collectors
    'internal_api_token' => env('INTERNAL_API_TOKEN'),

    'ai_report_commentary' => [
        'python_binary' => env('PYTHON_BIN', 'python'),
        'module' => env('AI_REPORT_COMMENTARY_MODULE', 'havas_collectors.ai.report_platform_section_commentary'),
        'api_url' => env('LARAVEL_API_URL'),
    ],

    'meta_ads' => [
        'client_id' => env('META_APP_ID'),
        'client_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'scopes' => array_values(array_filter(array_map(
            static fn (string $scope): string => trim($scope),
            explode(',', (string) env('META_SCOPES', 'ads_read,ads_management'))
        ))),
    ],

    'auth' => [
        'allowed_email_domains' => array_values(array_filter(array_map(
            static fn (string $domain): string => strtolower(trim($domain)),
            explode(',', (string) env('AUTH_ALLOWED_EMAIL_DOMAINS', 'havasmad.com'))
        ))),
    ],

];
