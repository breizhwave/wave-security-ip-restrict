# wave-security-ip-restrict

Restrict a whole Laravel application to an allow list of IP addresses (IPv4, IPv6, CIDR ranges).

- The list comes from the `.env` (`IP_RESTRICT_IPS`). An empty list means no restriction at all.
- An optional admin page and console commands let someone who already has access add, remove (hide) and restore entries. These changes go to a JSON file that survives deployments.
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
