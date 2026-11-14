# wave-security-ip-restrict

Restrict a whole Laravel application to an allow list of IP addresses (IPv4, IPv6, CIDR ranges).

- The list comes from the `.env` (`IP_RESTRICT_IPS`). An empty list means no restriction at all.
- An optional admin page and console commands let someone who already has access add, remove (hide) and restore entries. These changes go to a JSON file that survives deployments.
  - **Removing an added entry** deletes it from the JSON file.
  - **Removing a `.env` entry** only hides it: the JSON file records the address with when and by whom (`hidden_at`, `hidden_by`), and the address is refused from then on. The `.env` itself is never changed. The application often can't write to it (read-only file, cached config, managed by the deployment), and it stays the reference list that you version and deploy. Hiding also keeps the removal reversible: **restoring** (`ip-restrict:remove <ip> --restore`, or the button on the page) takes the address off the hidden list, with its original `.env` label, without touching the server. To remove a `.env` address for good, delete it from `IP_RESTRICT_IPS`.
- Some `.env` entries can be protected (they are never removable from the page), and nobody can remove the entry that covers the address they are using.
- Refused requests get a 403 page that shows the visitor's address. The page is in English and French and can be published to change it.
- **PHP 5.6 to 8.x, Laravel 5.1 to 13**: the package uses PHP 5.6 syntax only, framework features that exist since Laravel 5.1, and its own CIDR matcher with no Symfony version dependency.

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
| `admin.write_middleware` | `['throttle:20,1']` | Applied to add, remove and restore. |

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
```

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

- **Your own 403 page**: set `forbidden_view` to one of your views. It receives `$ip`.
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
- **Admin page with the restriction off**: when `IP_RESTRICT_IPS` is empty, only `admin.middleware` protects the admin page. Keep it disabled, or put it behind authentication.
- **Static files**: files the web server serves directly from `public/` never reach Laravel, so they stay readable. To close them too, add a rule to the web server configuration, for example in Apache:

  ```apache
  <Directory "/path/to/site/public">
      Require ip 203.0.113.10 198.51.100.7 2001:db8:1:2::/64
  </Directory>
  ```

- An IP address does not identify a person: an office, a public Wi-Fi network or a VPN shares one address between many people. Treat the allow list as an extra barrier, not as authentication.

## Storage format

```json
{
    "version": 2,
    "ips": [{"ip": "192.0.2.0/28", "label": "Carol", "added_at": "2026-11-14T10:00:00+01:00", "added_by": "203.0.113.10"}],
    "hidden": [{"ip": "198.51.100.7", "hidden_at": "2026-11-14T10:05:00+01:00", "hidden_by": "203.0.113.10"}]
}
```

The package still reads files written by the module it was extracted from (version 1, French keys `libelle`, `ajoute_le`, `retirees`…). It rewrites them in this format on the next change.

## Tests

```bash
php tests/run.php     # IP logic, no framework and no Composer needed, any PHP from 5.6
```

## License

MIT

Made with [Breizhvibe](https://wave.bzh/en/breizhvibe), a set of vibe-coding skills and tools for web development.
