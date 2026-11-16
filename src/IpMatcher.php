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

        return self::samePrefix($ipBin, $netBin, $bits);
    }

    /**
     * Do two entries (addresses or CIDR ranges) share at least one address?
     * Two CIDR ranges either nest or are disjoint: compare them over the shorter mask.
     *
     * @param  string  $a
     * @param  string  $b
     * @return bool
     */
    public static function overlaps($a, $b)
    {
        if (! self::isValid($a) || ! self::isValid($b)) {
            return false;
        }
        $pa = explode('/', $a, 2);
        $pb = explode('/', $b, 2);
        $binA = self::toBinary($pa[0]);
        $binB = self::toBinary($pb[0]);
        if ($binA === false || $binB === false || strlen($binA) !== strlen($binB)) {
            return false;
        }
        $max = strlen($binA) * 8;
        $bitsA = isset($pa[1]) ? (int) $pa[1] : $max;
        $bitsB = isset($pb[1]) ? (int) $pb[1] : $max;

        return self::samePrefix($binA, $binB, min($bitsA, $bitsB));
    }

    /**
     * Are the first $bits bits of two packed addresses of the same length equal?
     *
     * @param  string  $a
     * @param  string  $b
     * @param  int  $bits
     * @return bool
     */
    private static function samePrefix($a, $b, $bits)
    {
        $bits = min($bits, strlen($a) * 8);
        $bytes = (int) floor($bits / 8);
        if ($bytes > 0 && substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
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
