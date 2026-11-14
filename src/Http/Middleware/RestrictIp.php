<?php

namespace Wave\SecurityIpRestrict\Http\Middleware;

use Closure;
use Wave\SecurityIpRestrict\IpList;

/**
 * Whole application restricted to the allowed addresses (IpList), when IP_RESTRICT_IPS is set.
 * Local server and the "except" paths always pass.
 *
 * Only covers requests that go through Laravel: files served directly by the web server from
 * public/ stay readable (see README, "Static files").
 */
class RestrictIp
{
    /** @var IpList */
    private $ips;

    public function __construct(IpList $ips)
    {
        $this->ips = $ips;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->ips->isActive() || $this->isExcepted($request) || $this->ips->isAllowed($request->ip())) {
            return $next($request);
        }

        return response()->view((string) config('ip-restrict.forbidden_view', 'ip-restrict::forbidden'), array('ip' => $request->ip()), 403);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    private function isExcepted($request)
    {
        $patterns = (array) config('ip-restrict.except', array());

        return count($patterns) > 0 && call_user_func_array(array($request, 'is'), $patterns);
    }
}
