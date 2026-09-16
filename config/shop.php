<?php

return [
    'brand' => env('SHOP_BRAND', 'agelesscode'),

    'currency' => env('SHOP_CURRENCY', 'RUB'),

    'products_per_page' => (int) env('SHOP_PRODUCTS_PER_PAGE', 12),

    'max_products_per_page' => (int) env('SHOP_MAX_PRODUCTS_PER_PAGE', 60),

    'orders_per_page' => (int) env('SHOP_ORDERS_PER_PAGE', 10),

    'max_orders_per_page' => (int) env('SHOP_MAX_ORDERS_PER_PAGE', 50),

    'max_item_quantity' => (int) env('SHOP_MAX_ITEM_QUANTITY', 99),

    'admin_per_page' => (int) env('SHOP_ADMIN_PER_PAGE', 20),

    'max_admin_per_page' => (int) env('SHOP_MAX_ADMIN_PER_PAGE', 100),

    'images' => [
        'max_per_product' => (int) env('SHOP_MAX_PRODUCT_IMAGES', 10),
        'max_kilobytes' => (int) env('SHOP_MAX_IMAGE_KILOBYTES', 5120),
    ],

    'footer_products' => (int) env('SHOP_FOOTER_PRODUCTS', 10),

    // Вес для товара, у которого он не заполнен: перевозчик не считает
    // невесомую посылку, а нулевой вес сорвал бы расчёт целиком.
    'default_product_weight_grams' => (int) env('SHOP_DEFAULT_PRODUCT_WEIGHT_GRAMS', 300),

    // Требования к паролю задаются здесь и применяются везде через
    // Password::defaults() — и при регистрации, и при смене из админки.
    'password_min_length' => (int) env('SHOP_PASSWORD_MIN_LENGTH', 5),

    // Выключатели интеграций, отдельные от их настроек: оплату и СДЭК просят
    // отключить на время, не теряя ключи и не разбирая подключение. Значение
    // false здесь — это «подключено, но недоступно покупателю».
    'integrations' => [
        'online_payment' => filter_var(env('SHOP_ONLINE_PAYMENT_ENABLED', false), FILTER_VALIDATE_BOOL),
        'cdek' => filter_var(env('SHOP_CDEK_ENABLED', false), FILTER_VALIDATE_BOOL),
    ],

    // Куда уходит уведомление о новом заказе. Пусто — письмо магазину не
    // отправляется, письмо покупателю уходит в любом случае.
    'orders_email' => env('SHOP_ORDERS_EMAIL'),

    'cart_session_key' => 'cart_token',

    'order_number_prefix' => env('SHOP_ORDER_NUMBER_PREFIX', 'WLN'),

    // Seeded on first run so the panel is reachable; change the password in .env.
    'admin' => [
        'name' => env('SHOP_ADMIN_NAME', 'Администратор'),
        'email' => env('SHOP_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SHOP_ADMIN_PASSWORD', 'Password1'),
    ],
];
