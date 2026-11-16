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
 *   4. when IP_RESTRICT_IPS is set, only the allowed addresses pass (refusals are logged).
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

        if (! $this->ips->isActive() || $this->ips->isAllowed($ip)) {
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
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $patterns
     * @return bool
     */
    private function is($request, array $patterns)
    {
        return count($patterns) > 0 && call_user_func_array(array($request, 'is'), $patterns);
    }
}
