<?php

/*
 * Framework-free tests of the IP logic: run with any PHP from 5.6 to 8.x, no Composer needed.
 *   php tests/run.php
 */

require __DIR__.'/../src/IpMatcher.php';
require __DIR__.'/../src/IpList.php';

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
check('rewritten as v2', $data['version'], IpList::VERSION);
check('rewritten in English', isset($data['ips'][0]['label']) && ! isset($data['ips'][0]['libelle']), true);

@unlink($file);
@rmdir(dirname($file));
@rmdir(dirname(dirname($file)));

echo ($failures === 0 ? 'OK' : 'FAILED')." ($count checks, $failures failures, PHP ".PHP_VERSION.")\n";
exit($failures === 0 ? 0 : 1);
