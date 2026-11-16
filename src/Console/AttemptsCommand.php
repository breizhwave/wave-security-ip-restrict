<?php

namespace Wave\SecurityIpRestrict\Console;

use Illuminate\Console\Command;
use Wave\SecurityIpRestrict\Attempts;

class AttemptsCommand extends Command
{
    protected $signature = 'ip-restrict:attempts {date? : YYYY-MM-DD, latest logged day by default} {--ip= : Detail of one address}';

    protected $description = 'Show the attack attempts of a day, by address (or the detail of one address)';

    public function handle()
    {
        $attempts = $this->laravel->make('Wave\SecurityIpRestrict\Attempts');
        $ips = $this->laravel->make('Wave\SecurityIpRestrict\IpList');
        $days = $attempts->days();
        $date = (string) $this->argument('date');
        if ($date === '') {
            if (count($days) === 0) {
                $this->comment('No attempt logged yet.');

                return 0;
            }
            $date = $days[0]['date'];
        }
        if (! Attempts::isDate($date)) {
            $this->error("$date: YYYY-MM-DD expected.");

            return 1;
        }

        $entries = $attempts->forDay($date);
        $ip = (string) $this->option('ip');
        if ($ip !== '') {
            $rows = array();
            foreach ($entries as $entry) {
                if ($entry['ip'] === $ip) {
                    $rows[] = array(substr($entry['at'], 11, 8), $entry['type'].(empty($entry['blocked']) ? '' : ' (blocked)'), $entry['detail'], trim($entry['method'].' '.$entry['path']), $entry['user_agent']);
                }
            }
            $this->line("$ip on $date:");
            $this->table(array('Time', 'Type', 'Detail', 'Request', 'User agent'), $rows);

            return 0;
        }

        $rows = array();
        foreach (Attempts::summary($entries) as $row) {
            $types = array();
            foreach ($row['types'] as $type => $n) {
                $types[] = "$type x$n";
            }
            $deny = $ips->denyEntryFor($row['ip']);
            $state = $deny ? 'blocked'.(empty($deny['expires_at']) ? '' : ' until '.$deny['expires_at']) : ($row['trusted'] ? 'trusted' : '');
            $rows[] = array($row['ip'], $row['count'], implode(', ', $types), substr($row['first'], 11, 8).' - '.substr($row['last'], 11, 8), $state);
        }
        $this->line("Attempts on $date (days logged: ".count($days).'):');
        $this->table(array('Address', 'Attempts', 'Types', 'First - last', 'State'), $rows);

        return 0;
    }

    /** Laravel 5.0 / 5.1 */
    public function fire()
    {
        return $this->handle();
    }
}
