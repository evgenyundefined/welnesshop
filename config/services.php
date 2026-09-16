<?php

// Тестовый и боевой контуры СДЭК различаются только адресом: учётные данные у
// них разные, и перепутать их — самая частая ошибка при подключении.
$cdekTest = filter_var(env('CDEK_TEST', false), FILTER_VALIDATE_BOOL);

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

    // Необязательное дублирование уведомлений о заказах в чат. Пусто — ничего
    // не отправляется, письма при этом уходят как обычно.
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'timeout' => (int) env('TELEGRAM_TIMEOUT', 10),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // СДЭК API v2. CDEK_TEST=true переключает на тестовый контур, у которого
    // своя учётная запись; CDEK_BASE_URL нужен только для локальной заглушки.
    'cdek' => [
        'test' => $cdekTest,
        'base_url' => rtrim((string) env(
            'CDEK_BASE_URL',
            $cdekTest ? 'https://api.edu.cdek.ru/v2' : 'https://api.cdek.ru/v2',
        ), '/'),
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
        // Чек по 54-ФЗ. Магазину с подключённой фискализацией платёж без чека
        // отклоняют, магазину без неё чек не нужен — отсюда выключатель.
        'receipt' => filter_var(env('YOOKASSA_RECEIPT', true), FILTER_VALIDATE_BOOL),
        // Ставка НДС: 1 — без НДС, 2 — 0%, 3 — 10%, 4 — 20%, 5 — 10/110, 6 — 20/120.
        'vat_code' => (int) env('YOOKASSA_VAT_CODE', 1),
        // Система налогообложения. Нужна, только если их несколько.
        'tax_system_code' => env('YOOKASSA_TAX_SYSTEM_CODE') === null
            ? null
            : (int) env('YOOKASSA_TAX_SYSTEM_CODE'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
