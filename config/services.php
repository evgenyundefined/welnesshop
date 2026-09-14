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

    // СДЭК API v2. Тестовый контур: https://api.edu.cdek.ru/v2 с аккаунтом
    // EMscd6r9JnFiQ3bLoyjJY6eM78JrJceI и паролем PjLZkKBHEiLK3YsjtNrt3TGNG0ahs3kG.
    'cdek' => [
        'base_url' => rtrim((string) env('CDEK_BASE_URL', 'https://api.cdek.ru/v2'), '/'),
        'account' => env('CDEK_ACCOUNT'),
        'password' => env('CDEK_PASSWORD'),
        'from_city_code' => env('CDEK_FROM_CITY_CODE') === null ? null : (int) env('CDEK_FROM_CITY_CODE'),
        'shipment_point' => env('CDEK_SHIPMENT_POINT'),
        'timeout' => (int) env('CDEK_TIMEOUT', 10),
        'package' => [
            'length' => (int) env('CDEK_PACKAGE_LENGTH', 20),
            'width' => (int) env('CDEK_PACKAGE_WIDTH', 15),
            'height' => (int) env('CDEK_PACKAGE_HEIGHT', 10),
        ],
    ],

    // ЮKassa. Тестовый магазин заводится в личном кабинете, у него свои
    // shop_id и секретный ключ, платежи на нём не списывают деньги.
    'yookassa' => [
        'base_url' => rtrim((string) env('YOOKASSA_BASE_URL', 'https://api.yookassa.ru/v3'), '/'),
        'shop_id' => env('YOOKASSA_SHOP_ID'),
        'secret_key' => env('YOOKASSA_SECRET_KEY'),
        'timeout' => (int) env('YOOKASSA_TIMEOUT', 15),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
