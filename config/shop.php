<?php

return [
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

    'cart_session_key' => 'cart_token',

    'order_number_prefix' => env('SHOP_ORDER_NUMBER_PREFIX', 'WLN'),

    // Seeded on first run so the panel is reachable; change the password in .env.
    'admin' => [
        'name' => env('SHOP_ADMIN_NAME', 'Администратор'),
        'email' => env('SHOP_ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('SHOP_ADMIN_PASSWORD', 'Password1'),
    ],
];
