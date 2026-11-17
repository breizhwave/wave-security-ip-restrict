<?php

namespace Wave\SecurityIpRestrict;

/**
 * Who can reach what with the current configuration, for the admin page (pure PHP 5.6+).
 *
 * Rows are kinds of visitors (local, allowed, other, denied), columns are areas of the site
 * (always open paths, restricted paths or the whole application, the rest of the site, the
 * admin page). Each cell is a state: open, login, admin_open, restricted, blocked or off.
 * Also says which README use case the configuration matches, and what looks wrong.
 */
class AccessOverview
{
    const README = 'https://github.com/breizhwave/wave-security-ip-restrict#';

    /** README anchors of the use cases */
    private static $anchors = array(
        1 => '1-private-application-staging-intranet-client-preview',
        2 => '2-public-site-back-office-for-the-team-only-automatic-blocking-everywhere',
        3 => '3-public-site-deny-list-and-automatic-blocking-only-like-fail2ban',
        4 => '4-behind-a-reverse-proxy-or-a-cdn',
        'admin' => 'who-can-open-the-admin-page',
    );

    /**
     * @param  array  $s  active (bool), scope (array, without the admin page), except (array),
     *                    global (bool), admin_enabled (bool), admin_path (string), admin_middleware (array),
     *                    auto_block (bool), attempts (bool), denied_count (int),
     *                    my_ip (string|null), my_row (local|allowed|denied|other), forwarded_for (string|null)
     * @return array use_case, columns, rows, warnings, my_row
     */
    public static function build(array $s)
    {
        $s += array(
            'active' => false, 'scope' => array(), 'except' => array(), 'global' => true,
            'admin_enabled' => false, 'admin_path' => 'ip-restrict', 'admin_middleware' => array(),
            'auto_block' => false, 'attempts' => false, 'denied_count' => 0,
            'my_ip' => null, 'my_row' => 'other', 'forwarded_for' => null,
        );
        $s['scope'] = array_values(array_filter((array) $s['scope'], 'strlen'));
        $hasAuth = self::hasAuth((array) $s['admin_middleware']);

        $columns = array();
        if (count((array) $s['except']) > 0) {
            $columns[] = array('key' => 'except', 'paths' => implode(', ', (array) $s['except']));
        }
        if (count($s['scope']) > 0) {
            $columns[] = array('key' => 'scoped', 'paths' => implode(', ', $s['scope']));
            $columns[] = array('key' => 'public', 'paths' => '');
        } else {
            $columns[] = array('key' => $s['global'] ? 'app' : 'app_middleware', 'paths' => '');
        }
        if (! $s['global']) {
            $columns[] = array('key' => 'unguarded', 'paths' => '');
        }
        $columns[] = array('key' => 'admin', 'paths' => '/'.trim((string) $s['admin_path'], '/').'/*');

        $rows = array();
        foreach (array('local', 'allowed', 'other', 'denied') as $row) {
            $cells = array();
            foreach ($columns as $column) {
                $cells[$column['key']] = self::cell($row, $column['key'], $s, $hasAuth);
            }
            $rows[] = array('key' => $row, 'cells' => $cells, 'mine' => $row === $s['my_row']);
        }

        return array(
            'use_case' => self::useCase($s),
            'columns' => $columns,
            'rows' => $rows,
            'warnings' => self::warnings($s, $hasAuth),
            'my_row' => $s['my_row'],
        );
    }

    /**
     * @param  string|int  $key  1 to 4, or "admin"
     * @return string
     */
    public static function link($key)
    {
        return self::README.(isset(self::$anchors[$key]) ? self::$anchors[$key] : 'use-cases');
    }

    /**
     * Does one of the admin middleware look like authentication (auth, auth:…, auth.basic, can:…)?
     *
     * @param  array  $middleware
     * @return bool
     */
    public static function hasAuth(array $middleware)
    {
        foreach ($middleware as $name) {
            if (is_string($name) && preg_match('/^(auth\b|can:|password\.confirm|verified)/', $name)) {
                return true;
            }
            if (is_string($name) && preg_match('/Authenticate/', $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $row
     * @param  string  $column
     * @param  array  $s
     * @param  bool  $hasAuth
     * @return string open, login, admin_open, restricted, blocked or off
     */
    private static function cell($row, $column, array $s, $hasAuth)
    {
        if ($column === 'admin' && ! $s['admin_enabled']) {
            return 'off';
        }
        // "except" paths skip every check, and routes without the middleware are never checked.
        if ($column === 'except' || $column === 'unguarded') {
            return 'open';
        }
        if ($row === 'denied') {
            return 'blocked';
        }
        $covered = in_array($column, array('app', 'app_middleware', 'scoped', 'admin'), true);
        if ($covered && $s['active'] && $row === 'other') {
            return 'restricted';
        }
        if ($column === 'admin') {
            if ($hasAuth) {
                return 'login';
            }

            return $s['active'] || $row === 'local' ? 'open' : 'admin_open';
        }

        return 'open';
    }

    /**
     * @param  array  $s
     * @return array number (1, 2, 3 or null), key, notes (message keys)
     */
    private static function useCase(array $s)
    {
        $notes = array();
        $protects = $s['auto_block'] && $s['attempts'];
        if (! $s['global']) {
            return array('number' => null, 'key' => 'case_custom', 'notes' => array());
        }
        if ($s['active'] && count($s['scope']) === 0) {
            return array('number' => 1, 'key' => 'case_1', 'notes' => $protects ? array() : array('note_no_auto_needed'));
        }
        if (! $protects) {
            $notes[] = 'note_no_auto';
        }
        if ($s['active']) {
            $adminOnly = true;
            $admin = trim((string) $s['admin_path'], '/');
            foreach ($s['scope'] as $pattern) {
                if ($pattern !== $admin && $pattern !== $admin.'/*') {
                    $adminOnly = false;
                }
            }

            return array('number' => $adminOnly ? 3 : 2, 'key' => $adminOnly ? 'case_3_admin' : 'case_2', 'notes' => $notes);
        }
        if (! $protects && (int) $s['denied_count'] === 0) {
            return array('number' => null, 'key' => 'case_idle', 'notes' => array());
        }

        return array('number' => 3, 'key' => 'case_3', 'notes' => $notes);
    }

    /**
     * @param  array  $s
     * @param  bool  $hasAuth
     * @return array list of [key, level (error|warn), params, link]
     */
    private static function warnings(array $s, $hasAuth)
    {
        $warnings = array();
        if ($s['admin_enabled'] && ! $s['active'] && ! $hasAuth) {
            $warnings[] = array('key' => 'warn_admin_open', 'level' => 'error', 'params' => array(), 'link' => self::link('admin'));
        }
        if (! $s['global']) {
            $warnings[] = array('key' => 'warn_not_global', 'level' => 'warn', 'params' => array(), 'link' => null);
        }
        $forwarded = is_string($s['forwarded_for']) ? trim(explode(',', $s['forwarded_for'])[0]) : '';
        if ($forwarded !== '' && IpMatcher::isValid($forwarded) && $forwarded !== $s['my_ip']) {
            $warnings[] = array('key' => 'warn_proxy', 'level' => 'warn', 'params' => array('forwarded' => $forwarded, 'ip' => (string) $s['my_ip']), 'link' => self::link(4));
        } elseif ($s['my_row'] !== 'local' && self::isPrivate($s['my_ip'])) {
            $warnings[] = array('key' => 'warn_private', 'level' => 'warn', 'params' => array('ip' => (string) $s['my_ip']), 'link' => self::link(4));
        }

        return $warnings;
    }

    /**
     * Private or link-local address: what a proxy or a container network usually looks like.
     *
     * @param  string|null  $ip
     * @return bool
     */
    public static function isPrivate($ip)
    {
        return IpMatcher::matchesAny($ip, array('10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', '127.0.0.0/8', 'fc00::/7', 'fe80::/10'));
    }
}
