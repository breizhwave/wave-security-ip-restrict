<?php

namespace Wave\SecurityIpRestrict\Http\Middleware;

use Closure;
use Wave\SecurityIpRestrict\Attempts;
use Wave\SecurityIpRestrict\IpList;

/**
 * Order of the checks:
 *   1. "except" paths pass;
 *   2. a denied address (deny list) is refused, even when the restriction is off;
 *   3. a request for a "probes" path is logged as an attempt (and may block the address);
 *   4. when IP_RESTRICT_IPS is set, only the allowed addresses pass (refusals are logged), on the
 *      whole application or, with IP_RESTRICT_PATHS, on these paths (and the admin page) only.
 * Local addresses always pass.
 *
 * Only covers requests that go through Laravel: files served directly by the web server from
 * public/ stay readable (see README, "Static files").
 */
class RestrictIp
{
    /** @var IpList */
    private $ips;

    /** @var Attempts */
    private $attempts;

    public function __construct(IpList $ips, Attempts $attempts)
    {
        $this->ips = $ips;
        $this->attempts = $attempts;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->is($request, (array) config('ip-restrict.except', array()))) {
            return $next($request);
        }
        $ip = $request->ip();
        if ($this->ips->isDenied($ip)) {
            return $this->forbidden($ip, 'denied');
        }

        $logged = false;
        if ($this->is($request, (array) config('ip-restrict.attempts.probes', array()))) {
            $logged = true;
            if ($this->attempts->recordRequest($request, 'probe') === Attempts::BLOCKED) {
                return $this->forbidden($ip, 'denied');
            }
        }

        if (! $this->ips->isActive() || ! $this->inScope($request) || $this->ips->isAllowed($ip)) {
            return $next($request);
        }
        if (! $logged && config('ip-restrict.attempts.not_allowed', true)) {
            $this->attempts->recordRequest($request, 'not_allowed');
        }

        return $this->forbidden($ip, 'not_allowed');
    }

    /**
     * @param  string|null  $ip
     * @param  string  $reason  not_allowed or denied
     * @return \Illuminate\Http\Response
     */
    private function forbidden($ip, $reason)
    {
        return response()->view((string) config('ip-restrict.forbidden_view', 'ip-restrict::forbidden'), array('ip' => $ip, 'reason' => $reason), 403);
    }

    /**
     * Is the allow list applied to this path? (config "paths", empty: everywhere)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function inScope($request)
    {
        $patterns = self::scope();

        return count($patterns) === 0 || $this->is($request, $patterns);
    }

    /**
     * Patterns of the allow list scope, with the admin page added; empty: the whole application.
     *
     * @return array
     */
    public static function scope()
    {
        $paths = config('ip-restrict.paths', '');
        $patterns = array_values(array_filter(array_map('trim', is_array($paths) ? $paths : explode(',', (string) $paths)), 'strlen'));
        if (count($patterns) > 0 && config('ip-restrict.admin.enabled')) {
            $admin = trim((string) config('ip-restrict.admin.path', 'ip-restrict'), '/');
            $patterns[] = $admin;
            $patterns[] = $admin.'/*';
        }

        return $patterns;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $patterns
     * @return bool
     */
    private function is($request, array $patterns)
    {
        return count($patterns) > 0 && call_user_func_array(array($request, 'is'), $patterns);
    }
}
