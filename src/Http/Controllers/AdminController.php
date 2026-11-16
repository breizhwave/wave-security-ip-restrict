<?php

namespace Wave\SecurityIpRestrict\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Wave\SecurityIpRestrict\Attempts;
use Wave\SecurityIpRestrict\IpList;

/**
 * Admin page: someone who already has access can add and remove addresses (.env entries are
 * hidden, and can be restored), except the one in use and the protected ones; deny and unblock
 * addresses; read the attack attempts log by day.
 */
class AdminController extends Controller
{
    public function index(Request $request)
    {
        $ips = $this->ips();

        return view('ip-restrict::admin', array(
            'entries' => $ips->all(),
            'denied' => $ips->denied(),
            'myIpDenied' => $ips->isDenied($request->ip()),
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

    public function deny(Request $request)
    {
        $ip = trim((string) $request->input('ip'));
        $label = trim((string) $request->input('label'));
        $minutes = (int) $request->input('minutes');

        if (strlen($ip) > 64 || ! IpList::isValid($ip)) {
            return $this->back('error', 'invalid_ip', $ip, true);
        }
        if ($label === '' || mb_strlen($label) > 100) {
            return $this->back('error', 'label_required', $ip, true);
        }
        if ($minutes < 0 || $minutes > 525600) {
            $minutes = 0;
        }

        switch ($this->ips()->deny($ip, $label, (string) $request->ip(), $request->ip(), $minutes ?: null)) {
            case IpList::DENIED:
                return $this->back('ok', 'denied', $ip);
            case IpList::ALREADY:
                return $this->back('error', 'already_denied', $ip, true);
            case IpList::IN_USE:
                return $this->back('error', 'deny_in_use', $ip, true);
            default:
                return $this->back('error', 'deny_protected', $ip, true);
        }
    }

    public function undeny(Request $request)
    {
        $ip = (string) $request->input('ip');

        switch ($this->ips()->undeny($ip)) {
            case IpList::REMOVED:
                return $this->back('ok', 'undenied', $ip);
            case IpList::ENV_ENTRY:
                return $this->back('error', 'deny_env', $ip);
            default:
                return $this->back('error', 'not_denied', $ip);
        }
    }

    public function attempts(Request $request)
    {
        $attempts = $this->attemptsLog();
        $days = $attempts->days();
        $date = (string) $request->input('date');
        if (! Attempts::isDate($date)) {
            $date = count($days) > 0 ? $days[0]['date'] : date('Y-m-d');
        }
        $ipFilter = trim((string) $request->input('ip'));

        $entries = $attempts->forDay($date);
        $summary = Attempts::summary($entries);
        $ips = $this->ips();
        foreach ($summary as $i => $row) {
            $summary[$i]['deny_entry'] = $ips->denyEntryFor($row['ip']);
        }
        if ($ipFilter !== '') {
            $entries = array_values(array_filter($entries, function ($entry) use ($ipFilter) {
                return $entry['ip'] === $ipFilter;
            }));
        }

        return view('ip-restrict::attempts', array(
            'days' => $days,
            'date' => $date,
            'ipFilter' => $ipFilter,
            'summary' => $summary,
            'entries' => array_slice($entries, 0, 500),
            'total' => count($entries),
            'myIp' => $request->ip(),
            'auto' => (array) config('ip-restrict.auto_block', array()),
            'ok' => session('ip_restrict_ok'),
            'error' => session('ip_restrict_error'),
        ));
    }

    /**
     * @return Attempts
     */
    private function attemptsLog()
    {
        return app('Wave\SecurityIpRestrict\Attempts');
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
        // Actions sent from the attempts page go back to the same day.
        $date = (string) request()->input('date');
        $redirect = request()->input('from') === 'attempts'
            ? redirect()->route('ip-restrict.attempts', Attempts::isDate($date) ? array('date' => $date) : array())
            : redirect()->route('ip-restrict.index');
        $redirect = $redirect
            ->with('ip_restrict_'.$type, trans('ip-restrict::messages.'.$key, array('ip' => $ip, 'mine' => request()->ip())));

        return $withInput ? $redirect->withInput() : $redirect;
    }
}
