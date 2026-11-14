<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Allowed addresses (.env)
    |--------------------------------------------------------------------------
    |
    | Addresses or CIDR ranges (IPv4 and IPv6), comma separated, each one with an
    | optional label after "=". Example:
    |   IP_RESTRICT_IPS="203.0.113.10=Office,198.51.100.7=Alice,2001:db8:1:2::/64=Alice (IPv6)"
    | Empty: no restriction at all (public production, tests).
    |
    | Addresses added from the admin page or the console are appended to this list,
    | but do not switch the restriction on by themselves.
    |
    */

    'ips' => env('IP_RESTRICT_IPS', ''),

    // .env entries that can never be removed from the admin page: those whose label starts with
    // one of these names ("Alice" also protects "Alice (IPv6)"). An added entry is never protected.
    'protected' => env('IP_RESTRICT_PROTECTED', ''),

    // Always allowed (php artisan serve). Behind a reverse proxy, configure TrustProxies first:
    // otherwise every request comes from 127.0.0.1 and passes.
    'local' => array('127.0.0.1', '::1'),

    // Paths always open ($request->is() patterns): health check, webhooks…
    'except' => array('up'),

    // Added / hidden entries (JSON). Keep it out of the deployed folder: it must survive deployments.
    'file' => storage_path('app/ip-restrict.json'),

    // Prepend the middleware to the global HTTP stack automatically. Set to false to register
    // Wave\SecurityIpRestrict\Http\Middleware\RestrictIp yourself (route group, alias…).
    'global' => env('IP_RESTRICT_GLOBAL', true),

    // View of the 403 page (receives $ip).
    'forbidden_view' => 'ip-restrict::forbidden',

    /*
    |--------------------------------------------------------------------------
    | Admin page (add / remove / restore entries)
    |--------------------------------------------------------------------------
    |
    | Off by default. When the restriction is off (empty IP_RESTRICT_IPS), the page is
    | protected only by "middleware": add your own authentication there.
    | Laravel 5.1: no "web" group and no "throttle" middleware, use array() for both.
    |
    */

    'admin' => array(
        'enabled' => env('IP_RESTRICT_ADMIN', false),
        'path' => 'ip-restrict',
        'middleware' => array('web'),
        'write_middleware' => array('throttle:20,1'),
    ),
);
