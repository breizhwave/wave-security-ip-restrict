<?php

/*
 * Framework-free tests of the IP logic: run with any PHP from 5.6 to 8.x, no Composer needed.
 *   php tests/run.php
 */

require __DIR__.'/../src/IpMatcher.php';
require __DIR__.'/../src/IpList.php';
require __DIR__.'/../src/Attempts.php';

use Wave\SecurityIpRestrict\Attempts;
use Wave\SecurityIpRestrict\IpList;
use Wave\SecurityIpRestrict\IpMatcher;

$failures = 0;
$count = 0;

function check($label, $actual, $expected)
{
    global $failures, $count;
    $count++;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL  $label: expected ".var_export($expected, true).', got '.var_export($actual, true)."\n";
    }
}

// --- IpMatcher::isValid
check('ipv4', IpMatcher::isValid('203.0.113.10'), true);
check('ipv6', IpMatcher::isValid('2001:db8::1'), true);
check('cidr v4', IpMatcher::isValid('203.0.113.0/24'), true);
check('cidr v6', IpMatcher::isValid('2001:db8::/64'), true);
check('/0 refused', IpMatcher::isValid('0.0.0.0/0'), false);
check('/33 refused', IpMatcher::isValid('1.2.3.4/33'), false);
check('/129 refused', IpMatcher::isValid('2001:db8::/129'), false);
check('garbage', IpMatcher::isValid('hello'), false);
check('mask not numeric', IpMatcher::isValid('1.2.3.4/a'), false);
check('empty', IpMatcher::isValid(''), false);
check('null', IpMatcher::isValid(null), false);

// --- IpMatcher::matches
check('exact v4', IpMatcher::matches('203.0.113.10', '203.0.113.10'), true);
check('other v4', IpMatcher::matches('203.0.113.11', '203.0.113.10'), false);
check('in /24', IpMatcher::matches('203.0.113.200', '203.0.113.0/24'), true);
check('out of /24', IpMatcher::matches('203.0.114.1', '203.0.113.0/24'), false);
check('in /20 (partial byte)', IpMatcher::matches('10.0.15.255', '10.0.0.0/20'), true);
check('out of /20', IpMatcher::matches('10.0.16.0', '10.0.0.0/20'), false);
check('in /1', IpMatcher::matches('127.0.0.1', '0.0.0.0/1'), true);
check('out of /1', IpMatcher::matches('128.0.0.1', '0.0.0.0/1'), false);
check('v6 in /64', IpMatcher::matches('2001:db8:1:2:abcd::1', '2001:db8:1:2::/64'), true);
check('v6 out of /64', IpMatcher::matches('2001:db8:1:3::1', '2001:db8:1:2::/64'), false);
check('v6 exact, other notation', IpMatcher::matches('2001:0db8:0000:0000:0000:0000:0000:0001', '2001:db8::1'), true);
check('v4 vs v6 entry', IpMatcher::matches('203.0.113.10', '2001:db8::/32'), false);
check('mapped v4', IpMatcher::matches('::ffff:203.0.113.10', '203.0.113.0/24'), true);
check('null ip', IpMatcher::matches(null, '203.0.113.10'), false);

// --- IpMatcher::overlaps
check('overlap nested', IpMatcher::overlaps('203.0.113.0/24', '203.0.113.10'), true);
check('overlap nested, reversed', IpMatcher::overlaps('203.0.113.10', '203.0.113.0/24'), true);
check('overlap wide', IpMatcher::overlaps('203.0.0.0/16', '203.0.113.0/24'), true);
check('no overlap', IpMatcher::overlaps('203.0.112.0/24', '203.0.113.0/24'), false);
check('overlap v6', IpMatcher::overlaps('2001:db8::/32', '2001:db8:1:2::/64'), true);
check('no overlap v4/v6', IpMatcher::overlaps('203.0.113.0/24', '2001:db8::/32'), false);
check('overlap invalid', IpMatcher::overlaps('nope', '203.0.113.0/24'), false);
check('invalid ip', IpMatcher::matches('nope', '203.0.113.10'), false);

// --- IpList
$file = sys_get_temp_dir().'/ip-restrict-test-'.getmypid().'/sub/ips.json';
@unlink($file);

$off = new IpList(array('ips' => '', 'file' => $file));
check('inactive without .env', $off->isActive(), false);

$list = new IpList(array(
    'ips' => '203.0.113.10=Office, 2001:db8:1:2::/64 = Alice (IPv6), bad=Ignored, 198.51.100.7=Bob',
    'protected' => 'Office,Alice',
    'local' => array('127.0.0.1', '::1'),
    'file' => $file,
));
check('active', $list->isActive(), true);
check('invalid .env entry skipped', count($list->fromEnv()), 3);
check('label trimmed', $list->fromEnv()[1]['label'], 'Alice (IPv6)');
check('protected by prefix', $list->fromEnv()[1]['protected'], true);
check('not protected', $list->fromEnv()[2]['protected'], false);
check('protected needs a word boundary', $list->isProtectedLabel('Officer'), false);
check('local allowed', $list->isAllowed('127.0.0.1'), true);
check('.env allowed', $list->isAllowed('203.0.113.10'), true);
check('range allowed', $list->isAllowed('2001:db8:1:2::99'), true);
check('stranger refused', $list->isAllowed('192.0.2.1'), false);
check('empty refused', $list->isAllowed(''), false);

check('add', $list->add('192.0.2.0/28', 'Carol', '203.0.113.10'), true);
check('file created', is_file($file), true);
check('add twice', $list->add('192.0.2.0/28', 'Carol', '203.0.113.10'), false);
check('added allowed', $list->isAllowed('192.0.2.5'), true);
check('added does not switch on', (new IpList(array('ips' => '', 'file' => $file)))->isActive(), false);

check('remove protected', $list->remove('203.0.113.10', '192.0.2.5', 'x'), IpList::PROTECTED_ENTRY);
check('remove in use', $list->remove('192.0.2.0/28', '192.0.2.5', 'x'), IpList::IN_USE);
check('remove unknown', $list->remove('192.0.2.99', null, 'x'), IpList::UNKNOWN);
check('remove added', $list->remove('192.0.2.0/28', '203.0.113.10', 'x'), IpList::REMOVED);
check('removed refused', $list->isAllowed('192.0.2.5'), false);

check('hide .env', $list->remove('198.51.100.7', '203.0.113.10', 'x'), IpList::REMOVED);
check('hidden refused', $list->isAllowed('198.51.100.7'), false);
$bob = null;
foreach ($list->all() as $entry) {
    if ($entry['ip'] === '198.51.100.7') {
        $bob = $entry;
    }
}
check('hidden flagged', $bob['hidden'], true);
check('hidden twice is unknown', $list->remove('198.51.100.7', null, 'x'), IpList::UNKNOWN);
check('restore', $list->restore('198.51.100.7'), true);
check('restored allowed', $list->isAllowed('198.51.100.7'), true);
check('restore twice', $list->restore('198.51.100.7'), false);

// French keys of the original module are read.
file_put_contents($file, json_encode(array(
    'version' => 1,
    'ips' => array(array('ip' => '192.0.2.50', 'libelle' => 'Florent', 'ajoute_le' => '2026-10-01T10:00:00+02:00', 'ajoute_par' => '1.2.3.4')),
    'retirees' => array(array('ip' => '198.51.100.7', 'retiree_le' => '2026-10-02T10:00:00+02:00', 'retiree_par' => '1.2.3.4')),
)));
$all = $list->all();
check('legacy added read', $all[3]['label'], 'Florent');
check('legacy added_at', $all[3]['added_at'], '2026-10-01T10:00:00+02:00');
check('legacy hidden read', $all[2]['hidden'], true);
check('legacy allowed', $list->isAllowed('192.0.2.50'), true);
$list->restore('198.51.100.7');
$data = json_decode(file_get_contents($file), true);
check('rewritten at the current version', $data['version'], IpList::VERSION);
check('denied key added', isset($data['denied']) && $data['denied'] === array(), true);

// --- Deny list
$deny = new IpList(array(
    'ips' => '',
    'deny' => '198.51.100.0/24=Scraper, bad=Ignored',
    'protected' => '',
    'file' => $file,
));
check('env deny read', count($deny->denied()), 1);
check('env deny works with restriction off', $deny->isDenied('198.51.100.20'), true);
check('not denied', $deny->isDenied('192.0.2.1'), false);
check('local never denied', $deny->isDenied('127.0.0.1'), false);
check('deny', $deny->deny('192.0.2.66', 'Brute force', 'admin'), IpList::DENIED);
check('denied', $deny->isDenied('192.0.2.66'), true);
check('deny twice', $deny->deny('192.0.2.66', 'Again', 'admin'), IpList::ALREADY);
check('deny own address', $deny->deny('192.0.2.0/24', 'Oops', 'admin', '192.0.2.5'), IpList::IN_USE);
check('undeny env', $deny->undeny('198.51.100.0/24'), IpList::ENV_ENTRY);
check('undeny unknown', $deny->undeny('192.0.2.99'), IpList::UNKNOWN);
check('undeny', $deny->undeny('192.0.2.66'), IpList::REMOVED);
check('undenied', $deny->isDenied('192.0.2.66'), false);
check('deny for a while', $deny->deny('192.0.2.67', 'Temp', 'admin', null, 30), IpList::DENIED);
check('temporarily denied', $deny->isDenied('192.0.2.67'), true);
$data = json_decode(file_get_contents($file), true);
$data['denied'][0]['expires_at'] = date('c', time() - 1);
file_put_contents($file, json_encode($data));
check('expired not denied', $deny->isDenied('192.0.2.67'), false);
check('expired can be denied again', $deny->deny('192.0.2.67', 'Again', 'admin'), IpList::DENIED);
$deny->undeny('192.0.2.67');

// Deny beats allow; a protected allowed entry cannot be denied.
$both = new IpList(array(
    'ips' => '203.0.113.10=Office,198.51.100.7=Bob',
    'protected' => 'Office',
    'file' => $file,
));
check('deny protected', $both->deny('203.0.113.0/24', 'Too wide', 'admin'), IpList::PROTECTED_ENTRY);
check('deny allowed', $both->deny('198.51.100.7', 'Left the team', 'admin'), IpList::DENIED);
check('allowed but denied', $both->isAllowed('198.51.100.7') && $both->isDenied('198.51.100.7'), true);
$both->undeny('198.51.100.7');

// --- Attempts
$dir = dirname($file).'/attempts';
$now = strtotime('2026-11-15 10:00:00');
$public = new IpList(array('ips' => '', 'deny' => '', 'file' => $file));
$public->add('203.0.113.50', 'Office', 'test');
$log = new Attempts(array(
    'dir' => $dir,
    'auto_block' => array('threshold' => 3, 'window' => 10, 'ban' => 60, 'types' => array('login_failed', 'probe')),
), $public);
$log->setClock(function () use (&$now) {
    return $now;
});
$lines = array();
$log->setLogger(function ($entry) use (&$lines) {
    $lines[] = $entry;
});

check('local ignored', $log->record('127.0.0.1', 'login_failed'), Attempts::IGNORED);
check('invalid ignored', $log->record('nope', 'login_failed'), Attempts::IGNORED);
check('range ignored', $log->record('192.0.2.0/24', 'login_failed'), Attempts::IGNORED);
check('1st attempt', $log->record('192.0.2.10', 'login_failed', 'bob@example.com', '/login', 'curl', 'POST'), Attempts::LOGGED);
check('not counted type', $log->record('192.0.2.10', 'not_allowed'), Attempts::LOGGED);
$now += 60;
check('2nd attempt', $log->record('192.0.2.10', 'probe', '', "/wp-login.php\n"), Attempts::LOGGED);
check('still allowed', $public->isDenied('192.0.2.10'), false);
$now += 60;
check('3rd attempt blocks', $log->record('192.0.2.10', 'login_failed'), Attempts::BLOCKED);
check('auto denied', $public->isDenied('192.0.2.10'), true);
$entry = $public->denyEntryFor('192.0.2.10');
check('auto label', strpos($entry['label'], 'Auto: 3 attempts') === 0, true);
check('auto by', $entry['added_by'], 'auto');
check('auto expires', strtotime($entry['expires_at']) > time(), true);
check('denied not logged', $log->record('192.0.2.10', 'login_failed'), Attempts::IGNORED);
check('logger called', count($lines), 4);

for ($i = 0; $i < 5; $i++) {
    $last = $log->record('203.0.113.50', 'login_failed');
}
check('trusted never blocked', $last === Attempts::LOGGED && ! $public->isDenied('203.0.113.50'), true);

$now += 3600;
check('window: old attempts forgotten', $log->record('192.0.2.11', 'probe'), Attempts::LOGGED);
$now += 11 * 60;
$log->record('192.0.2.11', 'probe');
check('window: 2 in window', $public->isDenied('192.0.2.11'), false);

check('days', $log->days(), array(array('date' => '2026-11-15', 'bytes' => filesize($dir.'/2026-11-15.jsonl'))));
$day = $log->forDay('2026-11-15');
check('day entries', count($day), 11);
check('newest first', $day[0]['ip'], '192.0.2.11');
check('control chars removed', $day[count($day) - 3]['path'], '/wp-login.php ');
check('detail kept', $day[count($day) - 1]['detail'], 'bob@example.com');
check('blocked flagged', ! empty($day[count($day) - 4]['blocked']), true);
check('trusted flagged', ! empty($day[2]['trusted']), true);
check('bad date', $log->forDay('../../etc/passwd'), array());
check('limit', count($log->forDay('2026-11-15', 2)), 2);
$summary = Attempts::summary($day);
check('summary most active first', $summary[0]['ip'], '203.0.113.50');
check('summary types', $summary[1]['types'], array('login_failed' => 2, 'probe' => 1, 'not_allowed' => 1));
check('summary blocked', $summary[1]['blocked'], true);

// A new day file removes the ones older than keep_days.
file_put_contents($dir.'/2026-01-01.jsonl', '{}'."\n");
$now = strtotime('2026-11-16 08:00:00');
$log->record('192.0.2.12', 'not_allowed');
check('old day pruned', is_file($dir.'/2026-01-01.jsonl'), false);
check('two days', count($log->days()), 2);

// Past max_day_bytes, only blockings are written.
$small = new Attempts(array('dir' => $dir, 'max_day_bytes' => 1, 'auto_block' => array('threshold' => 2, 'types' => array('probe'))), $public);
$small->setClock(function () use (&$now) {
    return $now;
});
$before = count($small->forDay('2026-11-16'));
$small->record('192.0.2.13', 'probe');
check('capped', count($small->forDay('2026-11-16')), $before);
check('capped still blocks', $small->record('192.0.2.13', 'probe'), Attempts::BLOCKED);
check('blocking written', count($small->forDay('2026-11-16')), $before + 1);

$disabled = new Attempts(array('dir' => $dir, 'enabled' => false), $public);
check('disabled', $disabled->record('192.0.2.14', 'probe'), Attempts::IGNORED);

foreach ((array) glob($dir.'/*') as $f) {
    @unlink($f);
}
@rmdir($dir);
check('rewritten in English', isset($data['ips'][0]['label']) && ! isset($data['ips'][0]['libelle']), true);

@unlink($file);
@rmdir(dirname($file));
@rmdir(dirname(dirname($file)));

echo ($failures === 0 ? 'OK' : 'FAILED')." ($count checks, $failures failures, PHP ".PHP_VERSION.")\n";
exit($failures === 0 ? 0 : 1);
