<?php

return [

    // Stripe API 密钥（生产环境必须设置）
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'public_key' => env('STRIPE_PUBLIC_KEY'),

    // Stripe Webhook 签名密钥
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    // 价格 ID 映射：plan key => Stripe price_id
    'prices' => [
        'pro' => env('STRIPE_PRICE_PRO'),
        'enterprise' => env('STRIPE_PRICE_ENTERPRISE'),
    ],

    // 成功/取消页面 URL（返回套餐订阅页）
    'success_url' => env('APP_URL') . '/settings/subscription?checkout=success',
    'cancel_url' => env('APP_URL') . '/settings/subscription?checkout=canceled',
];
