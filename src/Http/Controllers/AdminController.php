<?php

namespace Wave\SecurityIpRestrict\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wave\SecurityIpRestrict\IpList;

/**
 * Admin page: someone who already has access can add and remove addresses (.env entries are
 * hidden, and can be restored), except the one in use and the protected ones.
 */
class AdminController extends Controller
{
    public function index(Request $request)
    {
        $ips = $this->ips();

        return view('ip-restrict::admin', array(
            'entries' => $ips->all(),
            'active' => $ips->isActive(),
            'myIp' => $request->ip(),
            'myIpAllowed' => $ips->isAllowed($request->ip()),
            'ok' => session('ip_restrict_ok'),
            'error' => session('ip_restrict_error'),
        ));
    }

    public function add(Request $request)
    {
        $ip = trim((string) $request->input('ip'));
        $label = trim((string) $request->input('label'));

        if (strlen($ip) > 64 || ! IpList::isValid($ip)) {
            return $this->back('error', 'invalid_ip', $ip, true);
        }
        if ($label === '' || mb_strlen($label) > 100) {
            return $this->back('error', 'label_required', $ip, true);
        }
        if (! $this->ips()->add($ip, $label, (string) $request->ip())) {
            return $this->back('error', 'already_listed', $ip, true);
        }

        return $this->back('ok', 'added', $ip);
    }

    public function remove(Request $request)
    {
        $ip = (string) $request->input('ip');

        switch ($this->ips()->remove($ip, $request->ip(), (string) $request->ip())) {
            case IpList::REMOVED:
                return $this->back('ok', 'removed', $ip);
            case IpList::PROTECTED_ENTRY:
                return $this->back('error', 'protected', $ip);
            case IpList::IN_USE:
                // Never lock yourself out: the entry covering the address in use stays.
                return $this->back('error', 'in_use', $ip);
            default:
                return $this->back('error', 'unknown', $ip);
        }
    }

    public function restore(Request $request)
    {
        $ip = (string) $request->input('ip');

        return $this->ips()->restore($ip)
            ? $this->back('ok', 'restored', $ip)
            : $this->back('error', 'not_hidden', $ip);
    }

    /**
     * @return IpList
     */
    private function ips()
    {
        return app('Wave\SecurityIpRestrict\IpList');
    }

    /**
     * @param  string  $type  ok|error
     * @param  string  $key  message key
     * @param  string  $ip
     * @param  bool  $withInput
     * @return \Illuminate\Http\RedirectResponse
     */
    private function back($type, $key, $ip, $withInput = false)
    {
        $redirect = redirect()->route('ip-restrict.index')
            ->with('ip_restrict_'.$type, trans('ip-restrict::messages.'.$key, array('ip' => $ip, 'mine' => request()->ip())));

        return $withInput ? $redirect->withInput() : $redirect;
    }
}
