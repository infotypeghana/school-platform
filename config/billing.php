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
    | Currency
    |--------------------------------------------------------------------------
    | ISO 4217 currency code used in payment initialisations and display labels.
    | Change to 'NGN', 'KES', etc. when deploying to other markets.
    */
    'currency' => env('BILLING_CURRENCY', 'GHS'),

    /*
    |--------------------------------------------------------------------------
    | SMS Brand Prefix
    |--------------------------------------------------------------------------
    | Short prefix prepended to outbound subscription SMS notifications,
    | e.g. "SchoolMS: Your subscription expires in 7 days."
    | Reads APP_NAME by default so white-label deployments need no extra config.
    */
    'sms_brand_prefix' => env('BILLING_SMS_PREFIX', env('APP_NAME', 'SchoolMS')),

    /*
    |--------------------------------------------------------------------------
    | Subscription Packages
    |--------------------------------------------------------------------------
    | Per-student pricing is stored in the subscription_packages table and
    | managed via the Super Admin → Packages UI.  There are no hardcoded plan
    | amounts here; the table is the single source of truth.
    */
];
