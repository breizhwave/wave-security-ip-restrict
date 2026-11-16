# wave-security-ip-restrict

Restrict a whole Laravel application to an allow list of IP addresses (IPv4, IPv6, CIDR ranges), deny unwanted addresses, and block brute force and scans automatically.

- The `.env` (`IP_RESTRICT_IPS`) switches the restriction on and gives the base list. An empty `.env` list means no restriction at all. A JSON file adds to that list or hides some of its entries (see [How the allow list is built](#how-the-allow-list-is-built)).
- An optional admin page and console commands let someone who already has access add, remove (hide) and restore entries. These changes go to a JSON file that survives deployments.
  - **Removing an added entry** deletes it from the JSON file.
  - **Removing a `.env` entry** only hides it: the JSON file records the address with when and by whom (`hidden_at`, `hidden_by`), and the address is refused from then on. The `.env` itself is never changed. The application often can't write to it (read-only file, cached config, managed by the deployment), and it stays the reference list that you version and deploy. Hiding also keeps the removal reversible: **restoring** (`ip-restrict:remove <ip> --restore`, or the button on the page) takes the address off the hidden list, with its original `.env` label, without touching the server. To remove a `.env` address for good, delete it from `IP_RESTRICT_IPS`.
- Some `.env` entries can be protected (they are never removable from the page), and nobody can remove the entry that covers the address they are using.
- A **deny list** (`IP_RESTRICT_DENY`, the admin page, the console) refuses addresses even when the restriction is off: it works on a public site too. See [Deny list](#deny-list).
- **Attack attempts** (failed logins, lockouts, scanner probes such as `wp-login.php` or `.env`, refused requests) are logged one file per day, shown by date on the admin page, and an address that repeats them is **blocked automatically** for a while. See [Attack attempts and automatic blocking](#attack-attempts-and-automatic-blocking).
- Refused requests get a 403 page that shows the visitor's address. The page is in English and French and can be published to change it.
- **PHP 5.6 to 8.x, Laravel 5.1 to 13**: the package uses PHP 5.6 syntax only, framework features that exist since Laravel 5.1, and its own CIDR matcher with no Symfony version dependency.
- It is not a WAF: it does not inspect request content (SQL injection, XSS…). See [Compared with other packages](#compared-with-other-packages) for akaunting/laravel-firewall and the alternatives.

## How the allow list is built

Every request is checked against both sources:

| Source | Role |
| --- | --- |
| `.env` (`IP_RESTRICT_IPS`) | **On/off switch** and base list. Edited by hand, deployed with the application. |
| JSON file (`storage/app/ip-restrict.json`) | Changes made from the admin page or the console: **added** entries and **hidden** `.env` entries. Never deployed, survives deployments. |

```
restriction on  =  IP_RESTRICT_IPS has at least one valid entry
allowed         =  (.env entries − hidden ones)  +  added entries  +  local (127.0.0.1, ::1)
```

What follows from it:

- **`.env` empty**: everyone gets in, even when the JSON file has added entries. They take effect once the `.env` list is set again.
- **Every `.env` entry hidden**: the restriction stays on, and only added entries and local addresses get in.
- **JSON file missing or deleted**: back to the plain `.env` list.

## Deny list

A denied address gets the 403 page ("Access blocked"), whatever the allow list says:

```
refused  =  denied (deny list, not expired)            even when the restriction is off
else     =  allow list rules above
local addresses (127.0.0.1, ::1) and `except` paths always pass
```

| Source | Role |
| --- | --- |
| `.env` (`IP_RESTRICT_DENY`) | Same `ip=label` format as `IP_RESTRICT_IPS`. Read only: remove an entry from the `.env`. |
| JSON file (`denied`) | Entries added from the admin page, the console or the automatic blocking, each with an optional end date (`expires_at`). Expired entries are ignored, then dropped on the next change. |

Two safety rails, on the admin page and in `IpList::deny()`:

- you cannot deny a range that covers **the address you are using**;
- you cannot deny a range that overlaps a **protected** allowed entry (`IP_RESTRICT_PROTECTED`).

## Attack attempts and automatic blocking

An attempt is one JSON line in `storage/app/ip-restrict-attempts/YYYY-MM-DD.jsonl`, with time, address, type, detail, method, path and user agent. Types:

| Type | Recorded when | Counted for blocking |
| --- | --- | --- |
| `login_failed` | Laravel fires `Illuminate\Auth\Events\Failed` (Laravel 5.2+). The detail is the `email` field tried (`attempts.login_field`), never the password. | yes |
| `lockout` | Laravel fires `Illuminate\Auth\Events\Lockout` (too many logins, `ThrottlesLogins` / Breeze / Fortify, Laravel 5.3+). | yes |
| `probe` | The path matches `attempts.probes` (`wp-login.php`, `wp-admin/*`, `xmlrpc.php`, `.env`, `.git/*`, `phpmyadmin`…). The request then goes on normally (usually a 404). | yes |
| `not_allowed` | The allow list refused the request. | no |
| `custom` (or yours) | Your code calls `app(\Wave\SecurityIpRestrict\Attempts::class)->recordRequest($request, 'custom', 'Bad API token')`. | `custom` yes |

**Automatic blocking**: an address with `threshold` counted attempts (5) within `window` minutes (10) is added to the deny list for `ban` minutes (60, `0` = until unblocked), with `added_by: "auto"`. The request that crosses the threshold already gets the 403 page.

What is never logged or blocked:

- **local addresses** (`127.0.0.1`, `::1`): never logged;
- **addresses on the allow list**, `.env` or added (even while the restriction is off): logged as *trusted*, never counted, so a colleague who mistypes a password does not lock the office out. Add your office or VPN address to be safe;
- **addresses already denied**: refused before anything else, not logged, so a blocked scanner does not fill the disk.

The log keeps `keep_days` days (30). Past `max_day_bytes` (10 MB) in a day, only blockings are still written. Set `IP_RESTRICT_ATTEMPTS_LOG=true` to copy each attempt to the Laravel log as a warning too (for your alerting).

The admin page has an **Attack attempts** tab: pick a day, see the attempts grouped by address (count, types, first and last time, state), the detail of one address, and **Block** or **Unblock** in one click.

## Install

```bash
composer require breizhwave/wave-security-ip-restrict
```

Laravel 5.5 and later discover the service provider automatically. On Laravel 5.1 to 5.4, add it to `config/app.php`:

```php
'providers' => [
    // ...
    Wave\SecurityIpRestrict\IpRestrictServiceProvider::class,
],
```

## Configure

`.env`:

```dotenv
# Addresses or CIDR ranges, comma separated, optional label after "="
IP_RESTRICT_IPS="203.0.113.10=Office,198.51.100.7=Alice,2001:db8:1:2::/64=Alice (IPv6)"
# .env entries never removable from the admin page (label prefixes)
IP_RESTRICT_PROTECTED="Office,Alice"
# Admin page at /ip-restrict (off by default)
IP_RESTRICT_ADMIN=true

# Denied addresses, always refused (same format)
IP_RESTRICT_DENY="198.51.100.0/24=Scraper"
# Attempts log and automatic blocking (defaults shown)
IP_RESTRICT_ATTEMPTS=true
IP_RESTRICT_AUTO_BLOCK=true
IP_RESTRICT_AUTO_BLOCK_THRESHOLD=5   # attempts…
IP_RESTRICT_AUTO_BLOCK_WINDOW=10     # …within this many minutes
IP_RESTRICT_AUTO_BLOCK_BAN=60        # minutes blocked, 0 = until unblocked
IP_RESTRICT_ATTEMPTS_LOG=false       # also write attempts to the Laravel log
```

To change the other settings, publish the config file (`config/ip-restrict.php`):

```bash
php artisan vendor:publish --tag=ip-restrict-config
php artisan vendor:publish --tag=ip-restrict-views   # 403 and admin pages
php artisan vendor:publish --tag=ip-restrict-lang    # en / fr texts
```

| Key | Default | Purpose |
| --- | --- | --- |
| `ips` | `IP_RESTRICT_IPS` | Allowed entries. An empty list turns the restriction off. |
| `protected` | `IP_RESTRICT_PROTECTED` | Label prefixes of protected `.env` entries. |
| `local` | `127.0.0.1`, `::1` | Always allowed, for `php artisan serve`. |
| `except` | `['up']` | Paths that stay open, as `$request->is()` patterns. |
| `file` | `storage/app/ip-restrict.json` | Added and hidden entries. |
| `global` | `true` | Prepends the middleware to the global HTTP stack. |
| `forbidden_view` | `ip-restrict::forbidden` | The 403 page. |
| `admin.enabled` | `IP_RESTRICT_ADMIN` (false) | Turns the admin routes on. |
| `admin.path` | `ip-restrict` | URL prefix of the admin page. |
| `admin.middleware` | `['web']` | Add your authentication here, e.g. `['web', 'auth']`. |
| `admin.write_middleware` | `['throttle:20,1']` | Applied to add, remove, restore, deny and unblock. |
| `deny` | `IP_RESTRICT_DENY` | Denied entries, always refused. |
| `attempts.enabled` | `IP_RESTRICT_ATTEMPTS` (true) | Turns the attempts log (and so the automatic blocking) on. |
| `attempts.dir` | `storage/app/ip-restrict-attempts` | One file per day, plus `counters.json`. |
| `attempts.keep_days` | `30` | Older day files are deleted. |
| `attempts.max_day_bytes` | 10 MB | Past it, only blockings are written that day. |
| `attempts.laravel_log` | `IP_RESTRICT_ATTEMPTS_LOG` (false) | Copy each attempt to the Laravel log (warning). |
| `attempts.login_failed` | `true` | Listen to Laravel's `Failed` and `Lockout` events. |
| `attempts.login_field` | `email` | Credential logged with a failed login. `null`: none. |
| `attempts.not_allowed` | `true` | Log the requests the allow list refuses. |
| `attempts.probes` | `wp-login.php`, `.env`… | Scanner paths, as `$request->is()` patterns. `[]` turns probes off. |
| `auto_block.enabled` | `IP_RESTRICT_AUTO_BLOCK` (true) | Off: attempts are only logged. |
| `auto_block.types` | `login_failed`, `lockout`, `probe`, `custom` | Types that count. |
| `auto_block.threshold` / `window` / `ban` | `5` / `10` min / `60` min | See above. `ban` `0`: until unblocked. |

### Using the middleware on some routes only

Set `global` to `false` (`IP_RESTRICT_GLOBAL=false`), then use the class as you would any middleware:

```php
Route::group(['middleware' => \Wave\SecurityIpRestrict\Http\Middleware\RestrictIp::class], function () {
    // ...
});
```

### Laravel 5.1

Laravel 5.1 has no `web` middleware group and no `throttle` middleware. Publish the config and set both `admin.middleware` and `admin.write_middleware` to `array()`, or to middleware your application has.

## Console

These commands also let you get back in if you lock yourself out:

```bash
php artisan ip-restrict:list
php artisan ip-restrict:add 192.0.2.10 "Bob, home"
php artisan ip-restrict:remove 192.0.2.10
php artisan ip-restrict:remove 198.51.100.7 --restore   # restore a hidden .env entry

php artisan ip-restrict:add 192.0.2.66 "Brute force" --deny               # deny, until unblocked
php artisan ip-restrict:add 192.0.2.66 "Brute force" --deny --minutes=60  # deny for an hour
php artisan ip-restrict:remove 192.0.2.66 --deny                          # unblock

php artisan ip-restrict:attempts                      # latest day, by address
php artisan ip-restrict:attempts 2026-11-15 --ip=192.0.2.66   # one address, in detail
```

`ip-restrict:list` shows the denied entries too. Blocked yourself by mistake? `ip-restrict:remove <your ip> --deny` from the server shell.

## Managing the list from your own admin

The bundled admin page is plain on purpose. To manage the list inside your own back office (your layout, your authentication, your language), leave `IP_RESTRICT_ADMIN` off and call `Wave\SecurityIpRestrict\IpList` yourself. The container gives one shared instance built from the config, so you can type-hint it in a controller.

| Method | Returns |
| --- | --- |
| `isActive()` | `true` when the `.env` list is not empty. |
| `isAllowed($ip)` | Whether an address gets in (list or `local`). |
| `all()` | Every entry: `ip`, `label`, `source` (`env` or `added`), `hidden`, `protected`, and `added_at`, `added_by`, `hidden_at` when set. |
| `add($ip, $label, $by)` | `false` if the entry is already there. |
| `remove($ip, $myIp, $by)` | `IpList::REMOVED`, `PROTECTED_ENTRY`, `IN_USE` (covers `$myIp`) or `UNKNOWN`. A `.env` entry is only hidden. |
| `restore($ip)` | Shows a hidden `.env` entry again. `false` if it was not hidden. |
| `IpList::isValid($ip)` | Valid IPv4 / IPv6 address or CIDR range. |
| `isDenied($ip)` / `denyEntryFor($ip)` | Whether the deny list refuses an address / the entry that does. |
| `denied()` | Deny entries not expired: `ip`, `label`, `source` (`env` or `added`), `added_at`, `added_by` (`auto` for automatic blocks), `expires_at`. |
| `deny($ip, $label, $by, $myIp = null, $minutes = null)` | `IpList::DENIED`, `ALREADY`, `IN_USE` (covers `$myIp`) or `PROTECTED_ENTRY`. |
| `undeny($ip)` | `IpList::REMOVED`, `ENV_ENTRY` (remove it from the `.env`) or `UNKNOWN`. |

For the attempts, use `Wave\SecurityIpRestrict\Attempts`:

| Method | Returns |
| --- | --- |
| `recordRequest($request, $type, $detail)` | `Attempts::BLOCKED` (this attempt blocked the address), `LOGGED` or `IGNORED` (local, denied, or log off). |
| `record($ip, $type, $detail, $path, $userAgent, $method)` | Same, without a request. |
| `days()` | Logged days, newest first: `date`, `bytes`. |
| `forDay('YYYY-MM-DD', $limit = 0)` | Attempts of a day, newest first: `at`, `ip`, `type`, `detail`, `method`, `path`, `user_agent`, and `trusted`, `count`, `blocked` when set. |
| `Attempts::summary($entries)` | Grouped by address: `ip`, `count`, `types`, `first`, `last`, `trusted`, `blocked`. |
| `IpList::covers($entry, $ip)` | Whether an entry matches an address. Use it to highlight the row of the current visitor. |

### 1. Routes

Put them inside your admin group, behind your own authentication. Throttle the write routes:

```php
use App\Http\Controllers\Admin\IpAccessController;

Route::middleware(['web', 'auth', 'can:admin'])->prefix('admin')->group(function () {
    Route::get('ip-access', [IpAccessController::class, 'index'])->name('ip-access');
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('ip-access', [IpAccessController::class, 'add'])->name('ip-access.add');
        Route::delete('ip-access', [IpAccessController::class, 'remove'])->name('ip-access.remove');
        Route::post('ip-access/restore', [IpAccessController::class, 'restore'])->name('ip-access.restore');
    });
});
```

### 2. Controller

```php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Wave\SecurityIpRestrict\IpList;

class IpAccessController extends Controller
{
    public function index(Request $request, IpList $ips)
    {
        return view('admin.ip-access', [
            'entries' => $ips->all(),
            'active' => $ips->isActive(),
            'myIp' => $request->ip(),
            'myIpAllowed' => $ips->isAllowed($request->ip()),
        ]);
    }

    public function add(Request $request, IpList $ips)
    {
        $data = $request->validate([
            'ip' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                if (! IpList::isValid(trim((string) $value))) {
                    $fail('Expected an IPv4 or IPv6 address, or a CIDR range.');
                }
            }],
            'label' => ['required', 'string', 'max:100'],
        ]);

        $ip = trim($data['ip']);
        $by = $request->user() ? $request->user()->email : $request->ip();   // stored as added_by

        return $ips->add($ip, trim($data['label']), $by)
            ? redirect()->route('ip-access')->with('status', "$ip added.")
            : back()->withErrors("$ip is already in the list.");
    }

    public function remove(Request $request, IpList $ips)
    {
        $ip = (string) $request->input('ip');

        switch ($ips->remove($ip, $request->ip(), (string) $request->ip())) {
            case IpList::REMOVED:
                return redirect()->route('ip-access')->with('status', "$ip removed.");
            case IpList::PROTECTED_ENTRY:
                return back()->withErrors("$ip is protected.");
            case IpList::IN_USE:
                return back()->withErrors("$ip covers your own address: removing it would lock you out.");
            default:
                return back()->withErrors("$ip is not in the list.");
        }
    }

    public function restore(Request $request, IpList $ips)
    {
        $ip = (string) $request->input('ip');

        return $ips->restore($ip)
            ? redirect()->route('ip-access')->with('status', "$ip restored.")
            : back()->withErrors("$ip was not removed.");
    }
}
```

### 3. View

Extend your own layout. One row per entry, with the right action for each case:

```blade
@extends('admin.layout')

@section('content')
    <p>
        Restriction <strong>{{ $active ? 'active' : 'off (IP_RESTRICT_IPS is empty)' }}</strong>.
        Your address: <code>{{ $myIp }}</code> ({{ $myIpAllowed ? 'allowed' : 'not listed' }}).
    </p>

    <table>
        @foreach ($entries as $entry)
            @php($mine = ! $entry['hidden'] && \Wave\SecurityIpRestrict\IpList::covers($entry['ip'], $myIp))
            <tr @class(['hidden-entry' => $entry['hidden'], 'mine' => $mine])>
                <td><code>{{ $entry['ip'] }}</code> @if ($mine) (your address) @endif</td>
                <td>{{ $entry['label'] }}</td>
                <td>{{ $entry['source'] === 'env' ? '.env' : 'added by '.($entry['added_by'] ?? '?') }}</td>
                <td>
                    @if ($entry['hidden'])
                        <form method="POST" action="{{ route('ip-access.restore') }}">
                            @csrf <input type="hidden" name="ip" value="{{ $entry['ip'] }}">
                            <button>Restore</button>
                        </form>
                    @elseif ($entry['protected'])
                        protected
                    @elseif ($mine)
                        in use
                    @else
                        <form method="POST" action="{{ route('ip-access.remove') }}">
                            @csrf @method('DELETE') <input type="hidden" name="ip" value="{{ $entry['ip'] }}">
                            <button>Remove</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
    </table>

    <form method="POST" action="{{ route('ip-access.add') }}">
        @csrf
        <input name="ip" required maxlength="64" placeholder="90.12.34.56 or 2001:db8::/64">
        <input name="label" required maxlength="100" placeholder="Who, where">
        <button>Add</button>
        @unless ($myIpAllowed)
            <button onclick="this.form.ip.value = @js($myIp)">Add my address</button>
        @endunless
    </form>
@endsection
```

### 4. Tips

- **Your own 403 page**: set `forbidden_view` to one of your views. It receives `$ip` and `$reason` (`not_allowed` or `denied`).
- **Your own `.env` names**: the config file is yours once published, e.g. `'ips' => env('ALLOWED_IPS', '')`.
- **IPv6 at home**: a home IPv6 address changes often. Suggest adding its `/64` range (first four groups, then `::/64`).
- **Older Laravel**: `@class`, `@js`, `@php(...)` and `$request->validate()` need recent versions. On Laravel 5.x, use `$this->validate($request, ...)`, plain `class="..."` attributes and `json_encode`.

## Checking that the restriction is active

Browsing from an allowed address only shows that you get in. To check the restriction, send a request from an address that is **not** on the list. You should get the 403 page, and it shows the address the server saw. Ways to do this:

- **Phone on mobile data**: turn Wi-Fi off and open the site. The mobile network gives the phone a different public address.
- **Remote or cloud browser**: a browser running on someone else's machine (a remote browser service, a browser on a cloud VM, a browser testing platform) connects from that machine's address.
- **VPN or Tor**: connect through a VPN, or open the site in Tor Browser.
- **`curl` from a server** you can log into (a VPS, another host, a CI job):

  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" https://example.com/   # expect 403
  curl -s -o /dev/null -w "%{http_code}\n" https://example.com/up # expect 200 (in `except`)
  ```

- **Header spoofing check**: from a refused address, pretend to be an allowed one. This must still return 403. If it returns 200, your trusted proxy settings accept forwarded headers from anyone (see the notes below):

  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" -H "X-Forwarded-For: 203.0.113.10" https://example.com/
  ```

- **Locally, without a second address**: `php artisan ip-restrict:list` shows the active entries. `127.0.0.1` and `::1` are always allowed, so a test on `php artisan serve` proves nothing. Use the package tests (`php tests/run.php`) for the matching logic.

**Does testing weaken the security?** No. A refused request only receives the 403 page, and that page shows the visitor their own address. It never shows the list. The one risk is how you test: don't add a remote browser, VPN or public Wi-Fi address to the list "to see if it works", because many other people share that address. If you do add one for a test, remove it straight after with `php artisan ip-restrict:remove <ip>`.

## Security notes

- **Reverse proxy or CDN**: the package checks `$request->ip()`. Behind a proxy, configure Laravel's trusted proxies first. If you don't, every visitor seems to come from the proxy, and if the proxy is `127.0.0.1` every visitor passes.
- **Automatic blocking behind a proxy**: if trusted proxies are not configured, every request seems to come from the proxy, so the first scanner gets **the proxy blocked, and so every visitor**. Configure trusted proxies before you leave `IP_RESTRICT_AUTO_BLOCK` on, and check the attempts tab shows real visitor addresses. If your proxies trust forwarded headers from anyone, an attacker can also get any address blocked by forging `X-Forwarded-For`.
- **Shared addresses and automatic blocking**: an office, a school or a mobile operator's NAT shares one address. Five failed logins there block everyone behind it for `ban` minutes. Put the addresses you rely on in the allow list (they are never blocked), and keep `ban` short.
- **The log is personal data**: it holds addresses, user agents and the login tried. `keep_days` limits how long it is kept, and it is shown only on the admin page.
- **Admin page with the restriction off**: when `IP_RESTRICT_IPS` is empty, only `admin.middleware` protects the admin page. Keep it disabled, or put it behind authentication.
- **Static files**: files the web server serves directly from `public/` never reach Laravel, so they stay readable. To close them too, add a rule to the web server configuration, for example in Apache:

  ```apache
  <Directory "/path/to/site/public">
      Require ip 203.0.113.10 198.51.100.7 2001:db8:1:2::/64
  </Directory>
  ```

- An IP address does not identify a person: an office, a public Wi-Fi network or a VPN shares one address between many people. Treat the allow list as an extra barrier, not as authentication.

## Compared with other packages

This package is about **who may reach the application** (an allow list for private sites, a deny list and automatic blocking for public ones). It does not inspect request content. If that is what you need, look at a WAF package, or use both.

### akaunting/laravel-firewall

[akaunting/laravel-firewall](https://github.com/akaunting/laravel-firewall) (about 1,000 stars, MIT, v3.0.0 in May 2026) is a Web Application Firewall: it reads request input to detect attacks.

| | wave-security-ip-restrict | akaunting/laravel-firewall |
| --- | --- | --- |
| Main goal | Close a whole site to an allow list, deny addresses, block repeat offenders | Detect and block attacks in requests (WAF) |
| PHP / Laravel | PHP 5.6+, Laravel 5.1 to 13 | PHP 8.2+, Laravel 10 to 13 |
| Storage | `.env` + one JSON file, no database | Database (`firewall_ips`, `firewall_logs` tables, migrations) |
| Allow list | Whole application, on/off from `.env`, IPv4/IPv6/CIDR, labels, added and hidden entries | `FIREWALL_WHITELIST` (CIDR through Symfony `IpUtils`): exempted from the checks, and `firewall.whitelist` middleware to keep routes to these addresses only |
| Deny list | `.env` + JSON, CIDR ranges, optional end date | Blocked addresses in the database, single addresses |
| Content inspection | None | SQL injection, XSS, LFI, RFI, PHP code, session fixation, bad URLs, swear words |
| Client filters | None | User agent, bots, referrer, geolocation (country, continent, region, city through an external service such as ipapi or ipinfo) |
| Brute force | Laravel `Failed` / `Lockout` events, scanner probe paths (`wp-login.php`, `.env`…), your own `recordRequest()` | Failed logins, plus each middleware's detections |
| Automatic blocking | One rule: `threshold` attempts in `window` minutes → banned `ban` minutes, expiry checked on each request | Per middleware (`attempts`, `frequency`, `period`), expiry by the scheduled `firewall:unblockip` command (cron) |
| Logs | One JSONL file per day, size cap, kept `keep_days` days | Database table |
| Notifications | None built in (`IP_RESTRICT_ATTEMPTS_LOG` copies attempts to the Laravel log for your alerting) | Email and Slack on each attack |
| Admin | Built-in page (allow list, deny list, attempts by day, block/unblock) and `ip-restrict:*` commands | No built-in page, Filament panels exist separately (see below) |
| Safety rails | Cannot remove or deny your own address, protected `.env` entries, allow list never auto-blocked | Whitelisted addresses skip the checks |

When to pick which:

- **Private site, back office, staging, intranet**: this package. One `.env` line closes everything, with nothing to migrate.
- **Public site that receives untrusted input**: laravel-firewall adds the content checks this package does not do. Payload detection by patterns has false positives and can be bypassed, so treat it as a layer on top of validation, parameter binding and output escaping.
- **Old Laravel (5.x to 9) or PHP before 8.2**: this package only, laravel-firewall 3 does not install there.
- **Both together** work: this one as the outer gate (allow and deny lists, probes, brute force), laravel-firewall for content checks on the public routes. Do not run two automatic blockings on the same events, or one address gets two bans with two expiries.

### Other packages

Checked in November 2026 (stars and last activity on GitHub):

| Package | What it does | Status |
| --- | --- | --- |
| [pragmarx/firewall](https://github.com/antonioribeiro/firewall) | Allow and deny lists in config or database, CIDR and ranges, countries through GeoIP, attack detection by request rate, artisan commands | About 1,400 stars, no activity since 2023 |
| [shieldon/shieldon](https://github.com/terrylinooo/shieldon) | Framework-agnostic PHP WAF with a Laravel integration: rate limits, CAPTCHA page, filters, admin panel | About 870 stars, last release 2.1.1 in June 2023 |
| [solution-forest/filament-firewall](https://github.com/solutionforest/filament-firewall) | Allow and deny lists (ranges) in the database, managed from a Filament admin panel | v4 for Filament 5 (February 2026), needs Filament |
| [orkhanahmadov/laravel-ip-middleware](https://github.com/orkhanahmadov/laravel-ip-middleware) | Route middleware that allows or refuses a list of addresses | v1.10 (2025), Laravel 8 to 12, PHP 8+ |
| [xultech/laravel-ip-whitelist](https://github.com/Xultech-LTD/laravel-ip-whitelist) | Allow list middleware, CIDR and wildcards, config or database, artisan commands | Small project (2025), Laravel 6 to 12 |
| [spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot) | Stops form spam bots (hidden field, timing). Not IP based, works well next to this package | Maintained |

Outside the application, often a better first line on a public site:

- **CDN or proxy WAF** (Cloudflare, AWS WAF, a hosting provider's firewall): attacks are stopped before they use your PHP workers, with managed rule sets kept up to date.
- **Web server rules** (Apache `Require ip`, nginx `allow` / `deny`): cheapest allow list, and the only one that also covers static files (see [Security notes](#security-notes)).
- **fail2ban** on the server's logs: blocks at the network level, for SSH and other services too.
- **Laravel's own rate limiting** (`throttle` middleware, `RateLimiter`): slows abuse without keeping a list.

## Storage format

```json
{
    "version": 3,
    "ips": [{"ip": "192.0.2.0/28", "label": "Carol", "added_at": "2026-11-14T10:00:00+01:00", "added_by": "203.0.113.10"}],
    "hidden": [{"ip": "198.51.100.7", "hidden_at": "2026-11-14T10:05:00+01:00", "hidden_by": "203.0.113.10"}],
    "denied": [{"ip": "192.0.2.66", "label": "Auto: 5 attempts in 10 min (last: probe)", "added_at": "2026-11-15T10:00:00+01:00", "added_by": "auto", "expires_at": "2026-11-15T11:00:00+01:00"}]
}
```

Version 2 files are read as is (no denied entry) and rewritten as version 3 on the next change.

Attempts (`attempts.dir`), one JSON object per line in `YYYY-MM-DD.jsonl`:

```json
{"at":"2026-11-15T10:00:00+01:00","ip":"192.0.2.66","type":"probe","detail":"","method":"GET","path":"/wp-login.php","user_agent":"Mozilla/5.0","count":5,"blocked":true}
```

`counters.json` in the same folder holds the recent attempt times per address, for the blocking window.

The package still reads files written by the module it was extracted from (version 1, French keys `libelle`, `ajoute_le`, `retirees`…). It rewrites them in this format on the next change.

## Tests

```bash
php tests/run.php     # IP logic, no framework and no Composer needed, any PHP from 5.6
```

## License

MIT

Made with [Breizhvibe](https://wave.bzh/en/breizhvibe), a set of vibe-coding skills and tools for web development.
