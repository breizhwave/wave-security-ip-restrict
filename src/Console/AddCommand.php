<?php

namespace Wave\SecurityIpRestrict\Console;

use Illuminate\Console\Command;
use Wave\SecurityIpRestrict\IpList;

/** Lock-out recovery: add an address from the server shell. */
class AddCommand extends Command
{
    protected $signature = 'ip-restrict:add {ip : Address or CIDR range} {label=console : Who, where}';

    protected $description = 'Allow an IP address or CIDR range';

    public function handle()
    {
        $ip = trim((string) $this->argument('ip'));
        if (! IpList::isValid($ip)) {
            $this->error("$ip: IPv4 or IPv6 address, or CIDR range (e.g. 2001:db8::/64), expected.");

            return 1;
        }
        if (! $this->laravel->make('Wave\SecurityIpRestrict\IpList')->add($ip, (string) $this->argument('label'), 'console')) {
            $this->comment("$ip is already listed.");

            return 0;
        }
        $this->info("$ip added.");

        return 0;
    }

    /** Laravel 5.0 / 5.1 */
    public function fire()
    {
        return $this->handle();
    }
}
