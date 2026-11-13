<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo users
    |--------------------------------------------------------------------------
    |
    | UserSeeder is restricted to local and testing. Its password still comes
    | from the environment so publishing the repository never publishes a
    | credential that can open the administrative area.
    |
    */

    'seed' => [
        'user_password' => env('NAPI_SEED_USER_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production bootstrap administrator
    |--------------------------------------------------------------------------
    |
    | The deploy entrypoint uses these values only when no active
    | administrator exists. It never resets an existing account password, so
    | the bootstrap secret can be removed after the first successful login.
    |
    */

    'bootstrap' => [
        'admin_name' => env('NAPI_BOOTSTRAP_ADMIN_NAME', 'NAPI Administrator'),
        'admin_email' => env('NAPI_BOOTSTRAP_ADMIN_EMAIL'),
        'admin_password' => env('NAPI_BOOTSTRAP_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Lab
    |--------------------------------------------------------------------------
    |
    | The Security Lab runs deliberately vulnerable code against synthetic
    | data, side by side with the protected version (ADR-0010). It is off
    | unless explicitly enabled: when disabled its routes answer 404, its
    | menu entry is hidden and its use cases refuse to run.
    |
    */

    'lab' => [
        'enabled' => (bool) env('SECURITY_LAB_ENABLED', false),
    ],

];
