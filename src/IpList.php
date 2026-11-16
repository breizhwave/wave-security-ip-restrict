<?php

namespace Wave\SecurityIpRestrict;

/**
 * Allowed IP addresses for the whole application (pure PHP 5.6+, no framework dependency):
 * - the .env entries (config "ips"), which switch the restriction on;
 * - the entries added from the admin page or the console by someone who already has access.
 *
 * Any entry can be removed, except a protected .env entry (config "protected": label prefixes)
 * and the one that covers the address of the person removing it. An added entry is deleted;
 * a .env entry (read only) is hidden and can be restored.
 *
 * Denied addresses (deny list) always win over allowed ones, and work even when the restriction
 * is off: the .env entries (config "deny", read only) and the entries added from the admin page,
 * the console or the automatic blocking (Attempts), optionally until a date (expires_at).
 *
 * Storage (config "file"), JSON:
 *   {version, ips: [{ip, label, added_at, added_by}], hidden: [{ip, hidden_at, hidden_by}],
 *    denied: [{ip, label, added_at, added_by, expires_at|null}]}
 * The French keys of the original module (libelle, ajoute_le, ajoute_par, retirees, retiree_le,
 * retiree_par) are still read, and rewritten in English on the next change.
 */
class IpList
{
    const VERSION = 3;

    const REMOVED = 'removed';

    const PROTECTED_ENTRY = 'protected';

    const IN_USE = 'in_use';

    const UNKNOWN = 'unknown';

    const DENIED = 'denied';

    const ALREADY = 'already';

    const ENV_ENTRY = 'env';

    /** @var array */
    private $config;

    /**
     * @param  array  $config  ips (string), deny (string), protected (string), local (array), file (string)
     */
    public function __construct(array $config)
    {
        $this->config = $config + [
            'ips' => '',
            'deny' => '',
            'protected' => '',
            'local' => array('127.0.0.1', '::1'),
            'file' => sys_get_temp_dir().'/ip-restrict.json',
        ];
    }

    /**
     * Restriction on: the .env gives at least one address.
     *
     * @return bool
     */
    public function isActive()
    {
        return count($this->fromEnv()) > 0;
    }

    /**
     * @param  string|null  $ip
     * @return bool
     */
    public function isAllowed($ip)
    {
        if (! is_string($ip) || $ip === '') {
            return false;
        }
        if (in_array($ip, (array) $this->config['local'], true)) {
            return true;
        }
        $entries = array();
        foreach ($this->all() as $entry) {
            if (! $entry['hidden']) {
                $entries[] = $entry['ip'];
            }
        }

        return IpMatcher::matchesAny($ip, $entries);
    }

    /**
     * Every entry: .env first (hidden: masked from the admin page), then the added ones.
     *
     * @return array list of [ip, label, source (env|added), hidden, protected, added_at?, added_by?, hidden_at?]
     */
    public function all()
    {
        $data = $this->read();
        $hidden = array();
        foreach ($data['hidden'] as $h) {
            $hidden[$h['ip']] = $h;
        }

        $entries = array();
        foreach ($this->fromEnv() as $entry) {
            $entry['hidden'] = isset($hidden[$entry['ip']]);
            if ($entry['hidden'] && isset($hidden[$entry['ip']]['hidden_at'])) {
                $entry['hidden_at'] = $hidden[$entry['ip']]['hidden_at'];
            }
            $entries[] = $entry;
        }
        foreach ($data['ips'] as $entry) {
            $entries[] = $entry + array('label' => '', 'source' => 'added', 'hidden' => false, 'protected' => false);
        }

        return $entries;
    }

    /**
     * Entries of the "ips" setting: "ip=label,ip/cidr=label".
     *
     * @return array list of [ip, label, source, protected]
     */
    public function fromEnv()
    {
        $entries = array();
        foreach (self::splitList($this->config['ips']) as $item) {
            $parts = array_map('trim', explode('=', $item, 2));
            $ip = $parts[0];
            $label = isset($parts[1]) ? $parts[1] : '';
            if (IpMatcher::isValid($ip)) {
                $entries[] = array('ip' => $ip, 'label' => $label, 'source' => 'env', 'protected' => $this->isProtectedLabel($label));
            }
        }

        return $entries;
    }

    /**
     * A .env label is protected when it starts with one of the "protected" names (case insensitive):
     * "Erwan" also protects "Erwan (IPv6)".
     *
     * @param  string  $label
     * @return bool
     */
    public function isProtectedLabel($label)
    {
        foreach (self::splitList($this->config['protected']) as $name) {
            if (preg_match('/^'.preg_quote($name, '/').'\b/iu', (string) $label)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the entry cover this address? (the one in use cannot be removed)
     *
     * @param  string  $entry
     * @param  string|null  $ip
     * @return bool
     */
    public static function covers($entry, $ip)
    {
        return IpMatcher::matches($ip, $entry);
    }

    /**
     * @param  string  $ip
     * @return bool
     */
    public static function isValid($ip)
    {
        return IpMatcher::isValid($ip);
    }

    /**
     * Adds an entry; false when it is already listed.
     *
     * @param  string  $ip
     * @param  string  $label
     * @param  string  $by  who added it (address, user name…)
     * @return bool
     */
    public function add($ip, $label, $by)
    {
        foreach ($this->all() as $entry) {
            if ($entry['ip'] === $ip) {
                return false;
            }
        }
        $this->mutate(function ($data) use ($ip, $label, $by) {
            $data['ips'][] = array('ip' => $ip, 'label' => (string) $label, 'added_at' => date('c'), 'added_by' => (string) $by);

            return $data;
        });

        return true;
    }

    /**
     * Removes an entry, except a protected one or the one that covers $myIp: an added entry is
     * deleted, a .env entry is hidden.
     *
     * @param  string  $ip
     * @param  string|null  $myIp  address of the person removing it (null from the console)
     * @param  string  $by
     * @return string self::REMOVED, PROTECTED_ENTRY, IN_USE or UNKNOWN
     */
    public function remove($ip, $myIp, $by)
    {
        $found = null;
        foreach ($this->all() as $entry) {
            if ($entry['ip'] === $ip && ! $entry['hidden']) {
                $found = $entry;
                break;
            }
        }
        if ($found === null) {
            return self::UNKNOWN;
        }
        if ($found['protected']) {
            return self::PROTECTED_ENTRY;
        }
        if (self::covers($ip, $myIp)) {
            return self::IN_USE;
        }

        $this->mutate(function ($data) use ($found, $ip, $by) {
            if ($found['source'] === 'env') {
                $data['hidden'][] = array('ip' => $ip, 'hidden_at' => date('c'), 'hidden_by' => (string) $by);
            } else {
                $kept = array();
                foreach ($data['ips'] as $entry) {
                    if ($entry['ip'] !== $ip) {
                        $kept[] = $entry;
                    }
                }
                $data['ips'] = $kept;
            }

            return $data;
        });

        return self::REMOVED;
    }

    /**
     * Restores a hidden .env entry; false when it was not hidden.
     *
     * @param  string  $ip
     * @return bool
     */
    public function restore($ip)
    {
        $restored = false;
        $this->mutate(function ($data) use ($ip, &$restored) {
            $kept = array();
            foreach ($data['hidden'] as $entry) {
                if ($entry['ip'] === $ip) {
                    $restored = true;
                } else {
                    $kept[] = $entry;
                }
            }
            $data['hidden'] = $kept;

            return $data;
        });

        return $restored;
    }

    /**
     * Local server address (config "local"): always allowed, never denied.
     *
     * @param  string|null  $ip
     * @return bool
     */
    public function isLocal($ip)
    {
        return is_string($ip) && in_array($ip, (array) $this->config['local'], true);
    }

    /**
     * Is this address denied (deny list, expired entries ignored)? Local addresses never are.
     *
     * @param  string|null  $ip
     * @return bool
     */
    public function isDenied($ip)
    {
        return $this->denyEntryFor($ip) !== null;
    }

    /**
     * The deny entry that covers this address, or null.
     *
     * @param  string|null  $ip
     * @return array|null
     */
    public function denyEntryFor($ip)
    {
        if (! is_string($ip) || $ip === '' || $this->isLocal($ip)) {
            return null;
        }
        foreach ($this->denied() as $entry) {
            if (IpMatcher::matches($ip, $entry['ip'])) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * Deny list: .env first, then the added entries that have not expired.
     *
     * @return array list of [ip, label, source (env|added), added_at?, added_by?, expires_at?]
     */
    public function denied()
    {
        $entries = array();
        foreach (self::splitList($this->config['deny']) as $item) {
            $parts = array_map('trim', explode('=', $item, 2));
            if (IpMatcher::isValid($parts[0])) {
                $entries[] = array('ip' => $parts[0], 'label' => isset($parts[1]) ? $parts[1] : '', 'source' => 'env', 'expires_at' => null);
            }
        }
        $data = $this->read();
        foreach ($data['denied'] as $entry) {
            if (! self::isExpired($entry)) {
                $entries[] = $entry + array('label' => '', 'source' => 'added', 'expires_at' => null);
            }
        }

        return $entries;
    }

    /**
     * Denies an address or range, for $minutes (null or 0: until it is unblocked).
     * Refused when it covers $myIp (you would lock yourself out) or a protected allowed entry.
     *
     * @param  string  $ip
     * @param  string  $label
     * @param  string  $by  who denied it (address, user name, "auto"…)
     * @param  string|null  $myIp  address of the person denying it (null from the console)
     * @param  int|null  $minutes
     * @return string self::DENIED, ALREADY, IN_USE or PROTECTED_ENTRY
     */
    public function deny($ip, $label, $by, $myIp = null, $minutes = null)
    {
        foreach ($this->denied() as $entry) {
            if ($entry['ip'] === $ip) {
                return self::ALREADY;
            }
        }
        if ($myIp !== null && IpMatcher::matches($myIp, $ip)) {
            return self::IN_USE;
        }
        foreach ($this->fromEnv() as $entry) {
            if ($entry['protected'] && IpMatcher::overlaps($entry['ip'], $ip)) {
                return self::PROTECTED_ENTRY;
            }
        }

        $expires = $minutes ? date('c', time() + 60 * (int) $minutes) : null;
        $this->mutate(function ($data) use ($ip, $label, $by, $expires) {
            $kept = array();
            foreach ($data['denied'] as $entry) {
                if ($entry['ip'] !== $ip) {
                    $kept[] = $entry;
                }
            }
            $kept[] = array('ip' => $ip, 'label' => (string) $label, 'added_at' => date('c'), 'added_by' => (string) $by, 'expires_at' => $expires);
            $data['denied'] = $kept;

            return $data;
        });

        return self::DENIED;
    }

    /**
     * Unblocks an added deny entry. A .env entry can only be removed from the .env.
     *
     * @param  string  $ip
     * @return string self::REMOVED, ENV_ENTRY or UNKNOWN
     */
    public function undeny($ip)
    {
        $found = null;
        foreach ($this->denied() as $entry) {
            if ($entry['ip'] === $ip) {
                $found = $entry;
                break;
            }
        }
        if ($found === null) {
            return self::UNKNOWN;
        }
        if ($found['source'] === 'env') {
            return self::ENV_ENTRY;
        }
        $this->mutate(function ($data) use ($ip) {
            $kept = array();
            foreach ($data['denied'] as $entry) {
                if ($entry['ip'] !== $ip) {
                    $kept[] = $entry;
                }
            }
            $data['denied'] = $kept;

            return $data;
        });

        return self::REMOVED;
    }

    /**
     * @param  array  $entry
     * @return bool
     */
    private static function isExpired(array $entry)
    {
        return ! empty($entry['expires_at']) && strtotime($entry['expires_at']) <= time();
    }

    /**
     * @return array
     */
    private function read()
    {
        $file = $this->config['file'];
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return self::normalize(is_array($data) ? $data : array());
    }

    /**
     * Read, change and write under an exclusive lock.
     *
     * @param  callable  $change  function (array $data): array
     * @return void
     */
    private function mutate($change)
    {
        $file = $this->config['file'];
        $dir = dirname($file);
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('ip-restrict: cannot create directory '.$dir);
        }
        $handle = fopen($file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException('ip-restrict: cannot open '.$file);
        }
        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $data = json_decode((string) $raw, true);
            $data = self::normalize(is_array($data) ? $data : array());
            $active = array();
            foreach ($data['denied'] as $entry) {
                if (! self::isExpired($entry)) {
                    $active[] = $entry;
                }
            }
            $data['denied'] = $active;
            $data = call_user_func($change, $data);
            $data['version'] = self::VERSION;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } catch (\Exception $e) {
            fclose($handle);
            throw $e;
        }
        fclose($handle);
    }

    /**
     * Default keys, and the French keys of the original module (version 1).
     *
     * @param  array  $data
     * @return array
     */
    private static function normalize(array $data)
    {
        $ips = isset($data['ips']) && is_array($data['ips']) ? $data['ips'] : array();
        $hidden = isset($data['hidden']) && is_array($data['hidden']) ? $data['hidden']
            : (isset($data['retirees']) && is_array($data['retirees']) ? $data['retirees'] : array());

        $denied = isset($data['denied']) && is_array($data['denied']) ? $data['denied'] : array();

        return array('version' => self::VERSION, 'ips' => self::renameKeys($ips), 'hidden' => self::renameKeys($hidden), 'denied' => self::renameKeys($denied));
    }

    /**
     * Keeps the rows that have an "ip", with the French keys renamed.
     *
     * @param  array  $rows
     * @return array
     */
    private static function renameKeys(array $rows)
    {
        $map = array('libelle' => 'label', 'ajoute_le' => 'added_at', 'ajoute_par' => 'added_by', 'retiree_le' => 'hidden_at', 'retiree_par' => 'hidden_by');
        $result = array();
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['ip'])) {
                continue;
            }
            foreach ($map as $old => $new) {
                if (array_key_exists($old, $row)) {
                    if (! array_key_exists($new, $row)) {
                        $row[$new] = $row[$old];
                    }
                    unset($row[$old]);
                }
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @param  string|array  $value  comma separated list, or array
     * @return array
     */
    private static function splitList($value)
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map('trim', $items), 'strlen'));
    }
}
