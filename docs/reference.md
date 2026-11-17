## $_SERVER keys

After the middleware runs, the following keys are available globally:

| Key                    | Example value              |
|------------------------|----------------------------|
| `GEOIP_COUNTRY`        | `Poland`                   |
| `GEOIP_COUNTRY_CODE`   | `PL`                       |
| `GEOIP_CITY`           | `Warsaw`                   |
| `GEOIP_REGION`         | `Masovian Voivodeship`     |

Keys already set by nginx or Cloudflare are never overwritten.

## Log enrichment

All Monolog channels (`app`, `audit`, `error`) automatically receive geo context on every log record:

```
req.country      — full country name
req.country_code — ISO 3166-1 alpha-2 code
req.city         — city name
```

### geoip.log

Each driver call produces one audit line in `{logs_dir}/geoip.log`:

```
[2026-07-17 12:34:56] [level=info] [channel=geoip] resolved req.id=550e8400-... req.ip=1.2.3.4 duration_ms=42.3 country=Poland country_code=PL city=Warsaw region=Masovian\ Voivodeship
```

`req.id` appears automatically when [dashboard-kit-request-id](https://github.com/rafalmasiarek/php-dashboard-kit-addon-request-id) is registered before this addon.

### Custom log formatter

Default format is `KvLineFormatter` (key=value lines). Override before `register()`:

```php
use Monolog\Formatter\JsonFormatter;

$container->set('geoip.log.formatter', fn() => new JsonFormatter());
GeoIpAddon::register($dashboard->getApp(), $container);
```

## Configuration reference

```php
'geoip' => [
    'db_path'  => '/path/to/GeoLite2-City.mmdb',  // required for MaxMindDriver; omit to disable plugin
    'log_days' => 30,                               // geoip.log retention in days
],
```

When `db_path` is not set and no custom driver is bound in the container, `GeoIpAddon::register()` does nothing — no middleware is added and no log processor is registered. This allows the addon call to remain in `index.php` without crashing when the database file is not yet available.

## Requirements

- PHP 8.2+
- dashboard-kit
- monolog/monolog ^3
- geoip2/geoip2 ^4.0 _(only for MaxMindDriver)_
