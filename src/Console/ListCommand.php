<?php

namespace Wave\SecurityIpRestrict\Console;

use Illuminate\Console\Command;

class ListCommand extends Command
{
    protected $signature = 'ip-restrict:list';

    protected $description = 'List the allowed and the denied IP addresses (.env and added ones)';

    public function handle()
    {
        $ips = $this->laravel->make('Wave\SecurityIpRestrict\IpList');
        $this->line($ips->isActive() ? 'Restriction: <info>active</info>' : 'Restriction: <comment>inactive</comment> (IP_RESTRICT_IPS empty)');
        $scope = \Wave\SecurityIpRestrict\Http\Middleware\RestrictIp::scope();
        $this->line('Scope: '.(count($scope) > 0 ? implode(', ', $scope) : 'whole application'));

        $rows = array();
        foreach ($ips->all() as $entry) {
            $rows[] = array(
                $entry['ip'],
                $entry['label'],
                $entry['source'],
                $entry['hidden'] ? 'removed' : ($entry['protected'] ? 'protected' : ''),
                isset($entry['added_at']) ? $entry['added_at'] : (isset($entry['hidden_at']) ? $entry['hidden_at'] : ''),
            );
        }
        $this->table(array('Address or range', 'Label', 'Source', 'State', 'Date'), $rows);

        $denied = array();
        foreach ($ips->denied() as $entry) {
            $denied[] = array(
                $entry['ip'],
                $entry['label'],
                $entry['source'] === 'added' && isset($entry['added_by']) && $entry['added_by'] === 'auto' ? 'auto' : $entry['source'],
                isset($entry['added_at']) ? $entry['added_at'] : '',
                empty($entry['expires_at']) ? 'until unblocked' : $entry['expires_at'],
            );
        }
        $this->line('Denied:');
        $this->table(array('Address or range', 'Reason', 'Source', 'Date', 'Until'), $denied);

        return 0;
    }

    /** Laravel 5.0 / 5.1 */
    public function fire()
    {
        return $this->handle();
    }
}
