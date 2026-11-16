# Changelog

## 1.1.0 - unreleased

- Deny list: `IP_RESTRICT_DENY`, entries added from the admin page or the console (`ip-restrict:add --deny`, `ip-restrict:remove --deny`), optionally for a limited time. A denied address is refused even when it is allowed and even when the restriction is off. You cannot deny your own address nor a range overlapping a protected entry.
- Attack attempts log, one JSON file per day: failed logins and lockouts (Laravel auth events), scanner probes (`attempts.probes`), requests refused by the allow list, and custom attempts (`Attempts::recordRequest()`). Optional copy to the Laravel log.
- Automatic blocking: 5 counted attempts within 10 minutes deny the address for 60 minutes (configurable, `IP_RESTRICT_AUTO_BLOCK=false` to only log). Allowed addresses are never blocked.
- Admin page: deny list section and an "Attack attempts" tab (by day, by address, detail, block / unblock). New `ip-restrict:attempts` command.
- The 403 view receives `$reason` (`not_allowed` or `denied`).
- JSON file version 3 (`denied` key); version 2 files are still read.
- README: comparison with akaunting/laravel-firewall and other IP blocking / WAF options.

## 1.0.0 - 2026-11-14

- First release: global middleware, CIDR IPv4 / IPv6 matcher, protected entries, admin page (en / fr), console commands, reading of the original module's JSON file.
