<?php

namespace Wave\SecurityIpRestrict;

/**
 * Attack attempt log and automatic blocking (pure PHP 5.6+, no framework dependency).
 *
 * An attempt is a failed login, a login lockout, a request for a path that scanners probe
 * (wp-login.php, .env…), a request refused by the allow list, or anything the application reports
 * itself (record()). Each one is a JSON line in a file per day (config "dir"/YYYY-MM-DD.jsonl).
 *
 * Automatic blocking: when an address reaches "threshold" counted attempts within "window"
 * minutes, it is added to the deny list (IpList::deny) for "ban" minutes (0: until unblocked).
 * Trusted addresses (local or on the allow list) are logged but never counted nor blocked.
 * Requests from an address that is already denied are not logged (they are refused anyway).
 */
class Attempts
{
    const BLOCKED = 'blocked';

    const LOGGED = 'logged';

    const IGNORED = 'ignored';

    /** @var array */
    private $config;

    /** @var IpList */
    private $ips;

    /** @var callable|null receives each logged entry (Laravel log) */
    private $logger;

    /** @var callable|null returns the current timestamp (tests) */
    private $clock;

    /**
     * @param  array  $config  enabled, dir, keep_days, max_day_bytes, auto_block (enabled, types, threshold, window, ban)
     * @param  IpList  $ips
     */
    public function __construct(array $config, IpList $ips)
    {
        $this->config = $config + array(
            'enabled' => true,
            'dir' => sys_get_temp_dir().'/ip-restrict-attempts',
            'keep_days' => 30,
            'max_day_bytes' => 10485760,
        );
        $auto = isset($config['auto_block']) && is_array($config['auto_block']) ? $config['auto_block'] : array();
        $this->config['auto_block'] = $auto + array(
            'enabled' => true,
            'types' => array('login_failed', 'lockout', 'probe', 'custom'),
            'threshold' => 5,
            'window' => 10,
            'ban' => 60,
        );
        $this->ips = $ips;
    }

    /**
     * @param  callable|null  $logger  function (array $entry)
     * @return void
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param  callable|null  $clock  function (): int
     * @return void
     */
    public function setClock($clock)
    {
        $this->clock = $clock;
    }

    /**
     * Records an attempt from a Laravel (or Symfony) request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $type
     * @param  string  $detail
     * @return string self::BLOCKED, LOGGED or IGNORED
     */
    public function recordRequest($request, $type = 'custom', $detail = '')
    {
        return $this->record($request->ip(), $type, $detail, '/'.ltrim((string) $request->path(), '/'), (string) $request->header('User-Agent'), $request->method());
    }

    /**
     * Records an attempt, and blocks the address when it reaches the threshold.
     *
     * @param  string|null  $ip
     * @param  string  $type  login_failed, lockout, probe, not_allowed, custom or your own
     * @param  string  $detail  login tried, reason… (never a password)
     * @param  string  $path
     * @param  string  $userAgent
     * @param  string  $method
     * @return string self::BLOCKED, LOGGED or IGNORED
     */
    public function record($ip, $type, $detail = '', $path = '', $userAgent = '', $method = '')
    {
        try {
            return $this->doRecord($ip, $type, $detail, $path, $userAgent, $method);
        } catch (\Exception $e) {
            // Logging must never break the request: an unwritable folder only loses the attempt.
            return self::IGNORED;
        }
    }

    /**
     * @see record()
     */
    private function doRecord($ip, $type, $detail, $path, $userAgent, $method)
    {
        if (! $this->config['enabled'] || ! is_string($ip) || ! IpMatcher::isValid($ip) || strpos($ip, '/') !== false) {
            return self::IGNORED;
        }
        if ($this->ips->isLocal($ip) || $this->ips->isDenied($ip)) {
            return self::IGNORED;
        }

        $now = $this->now();
        $trusted = $this->ips->isAllowed($ip);
        $auto = $this->config['auto_block'];
        $blocked = false;
        $count = null;
        if (! $trusted && $auto['enabled'] && in_array($type, (array) $auto['types'], true)) {
            $count = $this->count($ip, $now, max(1, (int) $auto['window']) * 60);
            if ($count >= max(1, (int) $auto['threshold'])) {
                $label = 'Auto: '.$count.' attempts in '.(int) $auto['window'].' min (last: '.$type.')';
                $blocked = $this->ips->deny($ip, $label, 'auto', null, (int) $auto['ban'] ?: null) === IpList::DENIED;
                if ($blocked) {
                    $this->resetCount($ip);
                }
            }
        }

        $entry = array(
            'at' => date('c', $now),
            'ip' => $ip,
            'type' => self::cut($type, 40),
            'detail' => self::cut($detail, 255),
            'method' => self::cut($method, 10),
            'path' => self::cut($path, 255),
            'user_agent' => self::cut($userAgent, 255),
        );
        if ($trusted) {
            $entry['trusted'] = true;
        }
        if ($count !== null) {
            $entry['count'] = $count;
        }
        if ($blocked) {
            $entry['blocked'] = true;
        }
        $this->write($entry, $now, $blocked);
        if ($this->logger) {
            call_user_func($this->logger, $entry);
        }

        return $blocked ? self::BLOCKED : self::LOGGED;
    }

    /**
     * Days that have a log file, newest first.
     *
     * @return array list of [date, bytes]
     */
    public function days()
    {
        $days = array();
        foreach ((array) glob($this->config['dir'].'/*.jsonl') as $file) {
            $date = basename($file, '.jsonl');
            if (self::isDate($date)) {
                $days[] = array('date' => $date, 'bytes' => (int) filesize($file));
            }
        }
        usort($days, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $days;
    }

    /**
     * Attempts of one day, newest first.
     *
     * @param  string  $date  YYYY-MM-DD
     * @param  int  $limit  0: all
     * @return array
     */
    public function forDay($date, $limit = 0)
    {
        if (! self::isDate($date)) {
            return array();
        }
        $file = $this->config['dir'].'/'.$date.'.jsonl';
        if (! is_file($file)) {
            return array();
        }
        $entries = array();
        foreach (array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $line) {
            $entry = json_decode($line, true);
            if (is_array($entry) && isset($entry['ip'], $entry['at'])) {
                $entries[] = $entry + array('type' => '', 'detail' => '', 'method' => '', 'path' => '', 'user_agent' => '');
                if ($limit > 0 && count($entries) >= $limit) {
                    break;
                }
            }
        }

        return $entries;
    }

    /**
     * Attempts grouped by address, most active first.
     *
     * @param  array  $entries  from forDay()
     * @return array list of [ip, count, types (type => count), first, last, trusted, blocked]
     */
    public static function summary(array $entries)
    {
        $byIp = array();
        foreach ($entries as $entry) {
            $ip = $entry['ip'];
            if (! isset($byIp[$ip])) {
                $byIp[$ip] = array('ip' => $ip, 'count' => 0, 'types' => array(), 'first' => $entry['at'], 'last' => $entry['at'], 'trusted' => false, 'blocked' => false);
            }
            $row = &$byIp[$ip];
            $row['count']++;
            $type = (string) $entry['type'];
            $row['types'][$type] = isset($row['types'][$type]) ? $row['types'][$type] + 1 : 1;
            if (strcmp($entry['at'], $row['first']) < 0) {
                $row['first'] = $entry['at'];
            }
            if (strcmp($entry['at'], $row['last']) > 0) {
                $row['last'] = $entry['at'];
            }
            $row['trusted'] = $row['trusted'] || ! empty($entry['trusted']);
            $row['blocked'] = $row['blocked'] || ! empty($entry['blocked']);
            unset($row);
        }
        $rows = array_values($byIp);
        usort($rows, function ($a, $b) {
            return $b['count'] - $a['count'] ?: strcmp($b['last'], $a['last']);
        });

        return $rows;
    }

    /**
     * @param  string  $date
     * @return bool
     */
    public static function isDate($date)
    {
        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1;
    }

    /**
     * Adds one to the address' counter and returns the number of attempts within the window.
     * Counters (config "dir"/counters.json): {ip: [timestamps]}, pruned on each write.
     *
     * @param  string  $ip
     * @param  int  $now
     * @param  int  $window  seconds
     * @return int
     */
    private function count($ip, $now, $window)
    {
        $count = 0;
        $this->mutateCounters(function ($counters) use ($ip, $now, $window, &$count) {
            $kept = array();
            foreach ($counters as $key => $stamps) {
                $recent = array();
                foreach ((array) $stamps as $stamp) {
                    if ((int) $stamp > $now - $window) {
                        $recent[] = (int) $stamp;
                    }
                }
                if (count($recent) > 0) {
                    $kept[$key] = $recent;
                }
            }
            $kept[$ip] = isset($kept[$ip]) ? $kept[$ip] : array();
            $kept[$ip][] = $now;
            $count = count($kept[$ip]);

            return $kept;
        });

        return $count;
    }

    /**
     * @param  string  $ip
     * @return void
     */
    private function resetCount($ip)
    {
        $this->mutateCounters(function ($counters) use ($ip) {
            unset($counters[$ip]);

            return $counters;
        });
    }

    /**
     * @param  callable  $change
     * @return void
     */
    private function mutateCounters($change)
    {
        $this->ensureDir();
        $handle = fopen($this->config['dir'].'/counters.json', 'c+');
        if ($handle === false) {
            return;
        }
        flock($handle, LOCK_EX);
        $counters = json_decode((string) stream_get_contents($handle), true);
        $counters = call_user_func($change, is_array($counters) ? $counters : array());
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode((object) $counters));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /**
     * Appends the entry to the day file. Past "max_day_bytes", only blockings are still written,
     * so that a flood cannot fill the disk. A new day file removes the ones older than "keep_days".
     *
     * @param  array  $entry
     * @param  int  $now
     * @param  bool  $important
     * @return void
     */
    private function write(array $entry, $now, $important)
    {
        $this->ensureDir();
        $file = $this->config['dir'].'/'.date('Y-m-d', $now).'.jsonl';
        $exists = is_file($file);
        if ($exists && ! $important && (int) $this->config['max_day_bytes'] > 0 && filesize($file) >= (int) $this->config['max_day_bytes']) {
            return;
        }
        file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND | LOCK_EX);
        if (! $exists) {
            $this->prune($now);
        }
    }

    /**
     * @param  int  $now
     * @return void
     */
    private function prune($now)
    {
        $keep = (int) $this->config['keep_days'];
        if ($keep <= 0) {
            return;
        }
        $limit = date('Y-m-d', $now - 86400 * $keep);
        foreach ($this->days() as $day) {
            if (strcmp($day['date'], $limit) < 0) {
                @unlink($this->config['dir'].'/'.$day['date'].'.jsonl');
            }
        }
    }

    /**
     * @return void
     */
    private function ensureDir()
    {
        $dir = $this->config['dir'];
        if (! is_dir($dir) && ! @mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new \RuntimeException('ip-restrict: cannot create directory '.$dir);
        }
    }

    /**
     * @return int
     */
    private function now()
    {
        return $this->clock ? (int) call_user_func($this->clock) : time();
    }

    /**
     * @param  string  $value
     * @param  int  $max
     * @return string
     */
    private static function cut($value, $max)
    {
        $value = (string) $value;
        if (function_exists('mb_convert_encoding')) {
            $value = mb_substr(mb_convert_encoding($value, 'UTF-8', 'UTF-8'), 0, $max, 'UTF-8');
        } else {
            $value = substr(preg_match('//u', $value) ? $value : preg_replace('/[\x80-\xFF]/', '?', $value), 0, $max);
        }

        return (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);
    }
}
