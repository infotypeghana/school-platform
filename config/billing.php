<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    | Number of days schools have after term end_date before full lock kicks in.
    */
    'grace_period_days' => env('BILLING_GRACE_DAYS', 5),

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */
    'default_gateway' => env('BILLING_GATEWAY', 'paystack'),

    /*
    |--------------------------------------------------------------------------
    | Subscription Cache TTL (minutes)
    |--------------------------------------------------------------------------
    */
    'cache_ttl_minutes' => env('BILLING_CACHE_TTL', 5),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    */
    'plans' => [
        'basic' => [
            'name'   => 'Basic',
            'amount' => env('PLAN_BASIC_AMOUNT', 500.00),  // GHS
        ],
        'standard' => [
            'name'   => 'Standard',
            'amount' => env('PLAN_STANDARD_AMOUNT', 850.00),
        ],
        'premium' => [
            'name'   => 'Premium',
            'amount' => env('PLAN_PREMIUM_AMOUNT', 1200.00),
        ],
    ],
];
