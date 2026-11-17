## Installation

```bash
composer require rafalmasiarek/dashboard-kit-addon-geoip
```

For the built-in MaxMind driver, also install:

```bash
composer require geoip2/geoip2
```

Download a free [GeoLite2-City.mmdb](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) database from MaxMind.

## Usage

```php
use rafalmasiarek\DashboardKit\Dashboard;
use rafalmasiarek\DashboardKitGeoIp\GeoIpAddon;

$dashboard = Dashboard::create(__DIR__ . '/../', [
    'geoip' => [
        'db_path'  => __DIR__ . '/../storage/GeoLite2-City.mmdb',
        'log_days' => 30,
    ],
]);

GeoIpAddon::register($dashboard->getApp(), $dashboard->getContainer());

$dashboard->run();
```

## Custom driver

Bind a custom driver before calling `register()`. The built-in MaxMind driver is skipped automatically.

```php
use rafalmasiarek\DashboardKitGeoIp\GeoIpAddon;
use rafalmasiarek\DashboardKitGeoIp\GeoIpDriverInterface;
use rafalmasiarek\DashboardKitGeoIp\GeoIpResult;

final class MyDriver implements GeoIpDriverInterface
{
    public function resolve(string $ip): GeoIpResult
    {
        // never throw — return empty GeoIpResult on any failure
        return new GeoIpResult(
            country:     'Poland',
            countryCode: 'PL',
            city:        'Warsaw',
            region:      'Masovian Voivodeship',
        );
    }
}

$container->set(GeoIpDriverInterface::class, fn() => new MyDriver());
GeoIpAddon::register($dashboard->getApp(), $container);
```

### HTTP driver tips

For HTTP-based drivers (ip-api.com, ipinfo.io, custom API):

- Use cURL — supports HTTPS and configurable timeout
- Accept a configurable URL template with `%s` for the IP
- Point the URL at a Cloudflare Worker to get automatic retries, HTTPS, and rate-limit handling

```php
final class IpApiDriver implements GeoIpDriverInterface
{
    public function __construct(
        private readonly string $url       = 'http://ip-api.com/json/%s?fields=country,countryCode,city,regionName',
        private readonly int    $timeoutMs = 3000,
    ) {}

    public function resolve(string $ip): GeoIpResult { ... }
}

// point at CF Worker for retries and HTTPS:
$container->set(GeoIpDriverInterface::class, fn() => new IpApiDriver(
    url: 'https://geoip.example.workers.dev/%s',
));
```
