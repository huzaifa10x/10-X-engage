<?php

/*
|--------------------------------------------------------------------------
| WhatsApp Business Platform configuration
|--------------------------------------------------------------------------
|
| Every value maps to a variable used by Meta's Postman collections
| (Embedded Signup, WhatsApp Cloud API, Business Management API).
|
*/

return [

    'graph' => [
        'base_url' => env('WHATSAPP_GRAPH_BASE_URL', 'https://graph.facebook.com'),
        'version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
        'timeout' => (int) env('WHATSAPP_GRAPH_TIMEOUT', 30),
    ],

    'app' => [
        'id' => env('WHATSAPP_APP_ID'),
        'secret' => env('WHATSAPP_APP_SECRET'),
    ],

    'embedded_signup' => [
        // Facebook Login for Business configuration ID
        'config_id' => env('WHATSAPP_CONFIG_ID'),
        // Passed to FB.login() extras.version (v4 current, v2 deprecated 15 Oct 2026)
        'version' => env('WHATSAPP_EMBEDDED_SIGNUP_VERSION', 'v4'),
        // Optional: extras.features / extras.featureType / extras.setup prefill
        'features' => array_filter(explode(',', (string) env('WHATSAPP_EMBEDDED_SIGNUP_FEATURES', ''))),
        'feature_type' => env('WHATSAPP_EMBEDDED_SIGNUP_FEATURE_TYPE'),
        // Exchangeable token code TTL is 30 seconds (Meta docs) – exchanged server side immediately.
    ],

    'partner' => [
        // tech_provider | solution_partner
        'type' => env('WHATSAPP_PARTNER_TYPE', 'tech_provider'),
        'business_id' => env('WHATSAPP_BUSINESS_ID'),
        'system_user_token' => env('WHATSAPP_SYSTEM_USER_TOKEN'),
        'system_user_id' => env('WHATSAPP_SYSTEM_USER_ID'),
        // Permissions granted when assigning the system user to a client WABA
        'system_user_tasks' => ['MANAGE'],
        'credit_line_id' => env('WHATSAPP_CREDIT_LINE_ID'),
        'default_waba_currency' => env('WHATSAPP_DEFAULT_WABA_CURRENCY', 'USD'),
    ],

    'webhook' => [
        'verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'verify_signature' => (bool) env('WHATSAPP_WEBHOOK_VERIFY_SIGNATURE', true),
        // Subscription fields you must enable in App Dashboard > WhatsApp > Configuration
        'fields' => [
            'messages',
            'message_template_status_update',
            'account_update',
            'account_review_update',
            'phone_number_name_update',
            'phone_number_quality_update',
        ],
    ],

    'media' => [
        // Cloud API media upload limits (bytes) – from the Cloud API collection "Media" folder.
        'limits' => [
            'image' => 5 * 1024 * 1024,
            'video' => 16 * 1024 * 1024,
            'audio' => 16 * 1024 * 1024,
            'document' => 100 * 1024 * 1024,
            'sticker' => 100 * 1024,
        ],
        'mime_types' => [
            'image' => ['image/jpeg', 'image/png'],
            'video' => ['video/mp4', 'video/3gpp'],
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'document' => [
                'text/plain', 'application/pdf', 'application/vnd.ms-powerpoint', 'application/msword',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
            'sticker' => ['image/webp'],
        ],
    ],

    'templates' => [
        'categories' => ['MARKETING', 'UTILITY', 'AUTHENTICATION'],
        'header_formats' => ['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT', 'LOCATION'],
        'button_types' => ['QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'COPY_CODE', 'OTP'],
        'languages' => [
            'en' => 'English', 'en_US' => 'English (US)', 'en_GB' => 'English (UK)', 'ar' => 'Arabic',
            'ur' => 'Urdu', 'hi' => 'Hindi', 'fr' => 'French', 'de' => 'German', 'es' => 'Spanish',
            'pt_BR' => 'Portuguese (BR)', 'it' => 'Italian', 'tr' => 'Turkish', 'ru' => 'Russian',
            'id' => 'Indonesian', 'ms' => 'Malay', 'zh_CN' => 'Chinese (CN)', 'bn' => 'Bengali',
            'fil' => 'Filipino', 'nl' => 'Dutch', 'fa' => 'Persian',
        ],
    ],
];
