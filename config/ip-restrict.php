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

    /*
    |--------------------------------------------------------------------------
    | Denied addresses (.env)
    |--------------------------------------------------------------------------
    |
    | Same format as "ips". A denied address is refused even when it is allowed, and even when
    | the restriction is off (empty IP_RESTRICT_IPS). Local addresses are never denied.
    | Addresses denied from the admin page, the console or the automatic blocking are added
    | to the JSON file ("file"), optionally for a limited time.
    |   IP_RESTRICT_DENY="198.51.100.0/24=Scraper,203.0.113.66=Spam"
    |
    */

    'deny' => env('IP_RESTRICT_DENY', ''),

    // .env entries that can never be removed from the admin page: those whose label starts with
    // one of these names ("Alice" also protects "Alice (IPv6)"). An added entry is never protected.
    'protected' => env('IP_RESTRICT_PROTECTED', ''),

    // Always allowed (php artisan serve). Behind a reverse proxy, configure TrustProxies first:
    // otherwise every request comes from 127.0.0.1 and passes.
    'local' => array('127.0.0.1', '::1'),

    // Paths always open ($request->is() patterns): health check, webhooks…
    'except' => array('up'),

    // Scope of the allow list ($request->is() patterns, comma separated). Empty: the whole
    // application. Set, e.g. "admin,admin/*": only these paths are restricted to the allowed
    // addresses, the rest of the site stays public. The deny list, the attempts log and the
    // automatic blocking still cover every path. The admin page (admin.path) is always in scope.
    'paths' => env('IP_RESTRICT_PATHS', ''),

    // Added / hidden entries (JSON). Keep it out of the deployed folder: it must survive deployments.
    'file' => storage_path('app/ip-restrict.json'),

    // Prepend the middleware to the global HTTP stack automatically. Set to false to register
    // Wave\SecurityIpRestrict\Http\Middleware\RestrictIp yourself (route group, alias…).
    'global' => env('IP_RESTRICT_GLOBAL', true),

    // View of the 403 page (receives $ip, and $reason: not_allowed or denied).
    'forbidden_view' => 'ip-restrict::forbidden',

    /*
    |--------------------------------------------------------------------------
    | Attack attempts log
    |--------------------------------------------------------------------------
    |
    | One JSON line per attempt in "dir"/YYYY-MM-DD.jsonl (keep it out of the deployed folder).
    | Recorded: failed logins and lockouts (Laravel auth events, 5.2+), requests for "probes"
    | paths, requests refused by the allow list ("not_allowed"), and what your code reports with
    | app(\Wave\SecurityIpRestrict\Attempts::class)->recordRequest($request, 'custom', 'why').
    | Local addresses are never logged; requests from denied addresses are not logged either.
    |
    */

    'attempts' => array(
        'enabled' => env('IP_RESTRICT_ATTEMPTS', true),
        'dir' => storage_path('app/ip-restrict-attempts'),
        'keep_days' => 30,
        // Day file size past which only the blockings are still written (flood protection).
        'max_day_bytes' => 10 * 1024 * 1024,
        // Also write each attempt to the Laravel log (warning level).
        'laravel_log' => env('IP_RESTRICT_ATTEMPTS_LOG', false),
        // Listen to Illuminate\Auth\Events\Failed and Lockout.
        'login_failed' => true,
        // Credential field logged with a failed login (never the password). null: none.
        'login_field' => 'email',
        // Log the requests refused by the allow list (they are never counted for blocking).
        'not_allowed' => true,
        // Paths that scanners probe ($request->is() patterns). The request goes on normally.
        'probes' => array(
            'wp-login.php', 'wp-admin', 'wp-admin/*', 'wp-content/*', 'wp-includes/*', 'xmlrpc.php',
            '.env', '.env.*', '*/.env', '.git', '.git/*', 'phpmyadmin', 'phpmyadmin/*', 'pma/*',
            'cgi-bin/*', 'vendor/phpunit/*', 'boaform/*', 'config.php', 'admin.php', 'shell.php',
        ),
    ),

    /*
    |--------------------------------------------------------------------------
    | Automatic blocking
    |--------------------------------------------------------------------------
    |
    | An address with "threshold" attempts of the counted "types" within "window" minutes is
    | denied for "ban" minutes (0: until unblocked from the admin page or the console).
    | Addresses on the allow list (and local ones) are never blocked.
    |
    */

    'auto_block' => array(
        'enabled' => env('IP_RESTRICT_AUTO_BLOCK', true),
        'types' => array('login_failed', 'lockout', 'probe', 'custom'),
        'threshold' => (int) env('IP_RESTRICT_AUTO_BLOCK_THRESHOLD', 5),
        'window' => (int) env('IP_RESTRICT_AUTO_BLOCK_WINDOW', 10),
        'ban' => (int) env('IP_RESTRICT_AUTO_BLOCK_BAN', 60),
    ),

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
