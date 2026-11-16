<?php

namespace Wave\SecurityIpRestrict\Console;

use Illuminate\Console\Command;
use Wave\SecurityIpRestrict\IpList;

/** Lock-out recovery: add an address from the server shell. With --deny: deny it instead. */
class AddCommand extends Command
{
    protected $signature = 'ip-restrict:add {ip : Address or CIDR range} {label=console : Who, where (or why, with --deny)}'
        .' {--deny : Deny the address instead of allowing it} {--minutes= : With --deny, for this long only}';

    protected $description = 'Allow (or, with --deny, deny) an IP address or CIDR range';

    public function handle()
    {
        $ip = trim((string) $this->argument('ip'));
        if (! IpList::isValid($ip)) {
            $this->error("$ip: IPv4 or IPv6 address, or CIDR range (e.g. 2001:db8::/64), expected.");

            return 1;
        }
        if ($this->option('deny')) {
            return $this->deny($ip);
        }
        if (! $this->laravel->make('Wave\SecurityIpRestrict\IpList')->add($ip, (string) $this->argument('label'), 'console')) {
            $this->comment("$ip is already listed.");

            return 0;
        }
        $this->info("$ip added.");

        return 0;
    }

    /**
     * @param  string  $ip
     * @return int
     */
    private function deny($ip)
    {
        $minutes = (int) $this->option('minutes');
        $result = $this->laravel->make('Wave\SecurityIpRestrict\IpList')->deny($ip, (string) $this->argument('label'), 'console', null, $minutes > 0 ? $minutes : null);
        switch ($result) {
            case IpList::DENIED:
                $this->info("$ip denied".($minutes > 0 ? " for $minutes minutes." : '.'));

                return 0;
            case IpList::ALREADY:
                $this->comment("$ip is already denied.");

                return 0;
            default:
                $this->error("$ip overlaps a protected allowed entry (IP_RESTRICT_PROTECTED): not denied.");

                return 1;
        }
    }

    /** Laravel 5.0 / 5.1 */
    public function fire()
    {
        return $this->handle();
    }
}
