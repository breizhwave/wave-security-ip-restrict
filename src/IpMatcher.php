<?php

namespace Wave\SecurityIpRestrict;

/**
 * IPv4 / IPv6 address and CIDR range helpers, pure PHP (5.6+), no framework dependency.
 */
class IpMatcher
{
    /**
     * An IPv4 or IPv6 address, or a CIDR range. A /0 mask is refused: it would open everything.
     *
     * @param  string  $entry
     * @return bool
     */
    public static function isValid($entry)
    {
        if (! is_string($entry) || $entry === '') {
            return false;
        }
        $parts = explode('/', $entry, 2);
        if (filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }
        if (! isset($parts[1])) {
            return true;
        }
        $max = strpos($parts[0], ':') !== false ? 128 : 32;

        return preg_match('/^\d{1,3}$/', $parts[1]) === 1 && (int) $parts[1] >= 1 && (int) $parts[1] <= $max;
    }

    /**
     * Does the entry (address or CIDR range) cover this address?
     *
     * @param  string|null  $ip
     * @param  string  $entry
     * @return bool
     */
    public static function matches($ip, $entry)
    {
        if (! is_string($ip) || $ip === '' || ! self::isValid($entry)) {
            return false;
        }
        $parts = explode('/', $entry, 2);
        $ipBin = self::toBinary($ip);
        $netBin = self::toBinary($parts[0]);
        if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
            return false;
        }

        $bits = isset($parts[1]) ? (int) $parts[1] : strlen($netBin) * 8;
        $bytes = (int) floor($bits / 8);
        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($netBin[$bytes]) & $mask);
    }

    /**
     * @param  string|null  $ip
     * @param  array  $entries
     * @return bool
     */
    public static function matchesAny($ip, array $entries)
    {
        foreach ($entries as $entry) {
            if (self::matches($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Packed address; an IPv4-mapped IPv6 address (::ffff:1.2.3.4) is read as IPv4.
     *
     * @param  string  $ip
     * @return string|false
     */
    private static function toBinary($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return false;
        }
        if (strlen($bin) === 16 && substr($bin, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return substr($bin, 12);
        }

        return $bin;
    }
}
