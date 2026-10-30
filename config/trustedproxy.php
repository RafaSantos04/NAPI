<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs or CIDRs of the load balancers in front of the app,
    | read by Laravel's TrustProxies middleware. Login counters and the
    | per-IP limits depend on the real client IP (ADR-0013, FIND-030).
    |
    | Unset means no proxy is trusted and X-Forwarded-For is ignored, except
    | on Laravel Cloud, Forge and Vapor hosts, where the framework trusts the
    | platform's balancer. Never "*" by hand: any client could then pick its
    | own IP. Set here, not in bootstrap/app.php, so it survives config:cache.
    |
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
