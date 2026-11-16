<?php

namespace Wave\SecurityIpRestrict\Console;

use Illuminate\Console\Command;
use Wave\SecurityIpRestrict\IpList;

class RemoveCommand extends Command
{
    protected $signature = 'ip-restrict:remove {ip : Address or CIDR range, as listed} {--restore : Restore a removed .env entry instead}'
        .' {--deny : Unblock a denied address instead}';

    protected $description = 'Remove an allowed IP (an .env entry is hidden), restore a hidden .env entry, or unblock a denied IP';

    public function handle()
    {
        $ip = trim((string) $this->argument('ip'));
        $ips = $this->laravel->make('Wave\SecurityIpRestrict\IpList');

        if ($this->option('deny')) {
            switch ($ips->undeny($ip)) {
                case IpList::REMOVED:
                    $this->info("$ip unblocked.");

                    return 0;
                case IpList::ENV_ENTRY:
                    $this->error("$ip is denied by the .env (IP_RESTRICT_DENY): remove it there.");

                    return 1;
                default:
                    $this->error("$ip is not denied.");

                    return 1;
            }
        }

        if ($this->option('restore')) {
            if ($ips->restore($ip)) {
                $this->info("$ip restored.");

                return 0;
            }
            $this->error("$ip was not removed.");

            return 1;
        }

        switch ($ips->remove($ip, null, 'console')) {
            case IpList::REMOVED:
                $this->info("$ip removed.");

                return 0;
            case IpList::PROTECTED_ENTRY:
                $this->error("$ip is protected (IP_RESTRICT_PROTECTED): edit the .env instead.");

                return 1;
            default:
                $this->error("$ip is not listed.");

                return 1;
        }
    }

    /** Laravel 5.0 / 5.1 */
    public function fire()
    {
        return $this->handle();
    }
}
