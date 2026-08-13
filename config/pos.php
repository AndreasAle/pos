<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Optional Modules
    |--------------------------------------------------------------------------
    |
    | Switches for parts of the app that only make sense when this is run as a
    | multi-tenant SaaS. A single restaurant using the POS for itself has no use
    | for subscription plans, a platform balance, or withdrawal approvals, so
    | they are off by default: the routes are not registered at all and the
    | sidebar entries disappear.
    |
    | Turn one back on by setting the matching key in .env, then running
    | `php artisan config:cache`.
    |
    */

    'features' => [

        // Staff activity trail. Useful on its own, off by default to keep the
        // sidebar focused; set FEATURE_AUDIT_LOG=true to bring it back.
        'audit_log' => env('FEATURE_AUDIT_LOG', false),

        // Subscription plans and the paywall that goes with them. While this is
        // false the subscription gate also stops running — otherwise an expired
        // tenant would be locked out with no page left to renew from.
        'subscription' => env('FEATURE_SUBSCRIPTION', false),

        // Merchant balance, withdrawal requests, and the admin queue for them.
        'balance' => env('FEATURE_BALANCE', false),

    ],

];
