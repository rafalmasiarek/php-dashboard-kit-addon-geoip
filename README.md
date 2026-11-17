# dashboard-kit-addon-geoip


[![Packagist Version](https://img.shields.io/packagist/v/rafalmasiarek/dashboard-kit-addon-geoip)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-geoip)
[![Total Downloads](https://img.shields.io/packagist/dt/rafalmasiarek/dashboard-kit-addon-geoip)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-geoip)
[![PHP](https://img.shields.io/packagist/php-v/rafalmasiarek/dashboard-kit-addon-geoip)](https://packagist.org/packages/rafalmasiarek/dashboard-kit-addon-geoip)
[![License](https://img.shields.io/badge/license-BUSL--1.1-red)](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/LICENSE)







GeoIP middleware and log enrichment addon for [dashboard-kit](https://github.com/rafalmasiarek/php-dashboard-kit).

Resolves the client IP to country, city, and region on every request. Stores results in `$_SERVER` (compatible with nginx `ngx_http_geoip_module`) and enriches all Monolog log channels with geo context fields.







## License

![License: BUSL-1.1](https://img.shields.io/badge/license-BUSL--1.1-red)

Business Source License 1.1.

Source available; commercial use restricted until 2099-01-01.




See [LICENSE](LICENSE) for full details.

For alternative licensing, [contact us](https://masiarek.pl/contact/?af_subject=Commercial+license+%E2%80%94+dashboard-kit-addon-geoip&af_message=Hello%2C+I+am+interested+in+a+commercial+license+for+dashboard-kit-addon-geoip.).



## Authors

- Rafał Masiarek | [website](https://masiarek.pl) | [github](https://github.com/rafalmasiarek)




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


## Index

### Classes

- [`MaxMindDriver`](#maxminddriver) — `rafalmasiarek\DashboardKitGeoIp\Driver`
- [`GeoIpAddon`](#geoipaddon) — `rafalmasiarek\DashboardKitGeoIp`
- [`GeoIpLogProcessor`](#geoiplogprocessor) — `rafalmasiarek\DashboardKitGeoIp`
- [`GeoIpMiddleware`](#geoipmiddleware) — `rafalmasiarek\DashboardKitGeoIp`
- [`GeoIpResult`](#geoipresult) — `rafalmasiarek\DashboardKitGeoIp`
- [`KvLineFormatter`](#kvlineformatter) — `rafalmasiarek\DashboardKitGeoIp\Log`

### Interfaces

- [`GeoIpDriverInterface`](#geoipdriverinterface) — `rafalmasiarek\DashboardKitGeoIp`

## `MaxMindDriver`

*final class* — `rafalmasiarek\DashboardKitGeoIp\Driver\MaxMindDriver` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Driver/MaxMindDriver.php#L22)

GeoIP driver backed by a local MaxMind GeoLite2 / GeoIP2 database file. Requires the geoip2/geoip2 package: composer require geoip2/geoip2 Download a free GeoLite2-City.mmdb from https://dev.maxmind.com/geoip/geolite2-free-geolocation-data and set the path in the geoip.db_path config key.

implements [`GeoIpDriverInterface`](#geoipdriverinterface)

**Depends on:** `Reader`

| Property | Type | Visibility | Description |
|---|---|---|---|
| `$reader` | `Reader` | private | MaxMind database reader instance. |

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#maxminddriver-__construct) | public | — | — |
| [`resolve()`](#maxminddriver-resolve) | public | [`GeoIpResult`](#geoipresult) | Resolves geolocation from the local MaxMind database. Returns an empty GeoIpResult on private/reserved ranges or any reader error. |

<details>
<summary><code>__construct()</code></summary>

#### `MaxMindDriver __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Driver/MaxMindDriver.php#L32)

```php
public function __construct(string $dbPath)
```

| Param | Type | Description |
|---|---|---|
| `$dbPath` | `string` | Absolute path to the .mmdb database file. |

| Throws | When |
|---|---|
| `\InvalidArgumentException` | When the file does not exist. |

</details>

<details>
<summary><code>resolve()</code></summary>

#### `MaxMindDriver resolve()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Driver/MaxMindDriver.php#L52)

```php
public function resolve(string $ip): GeoIpResult
```

Resolves geolocation from the local MaxMind database. Returns an empty GeoIpResult on private/reserved ranges or any reader error.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IPv4 or IPv6 address. |

**Returns:** [`GeoIpResult`](#geoipresult)

</details>

## `GeoIpAddon`

*final class* — `rafalmasiarek\DashboardKitGeoIp\GeoIpAddon` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpAddon.php#L45)

Registers GeoIP middleware and log processor for a dashboard-kit application. Driver resolution — checked in this order: 1. GeoIpDriverInterface::class already bound in container (custom driver from app) 2. MaxMindDriver using geoip.db_path from app.config (default) To use a custom driver, bind it before calling register(): $container->set(GeoIpDriverInterface::class, fn() => new IpApiDriver()); GeoIpAddon::register($dashboard->getApp(), $container); Uses the app's own logger.geoip channel when Dashboard::create() already registered one (config['logging']['geoip']) — so this channel gets the same processors (SecretRedactionProcessor, RequestHeadersProcessor) as every other channel. Falls back to a standalone logger writing to {logs_dir}/geoip.log (still protected by SecretRedactionProcessor) when the app didn't configure that channel. The per-resolution audit line (one per driver call, with ip, duration_ms, country, city) is only written when geoip.debug is true — the middleware runs on every request, so logging unconditionally would dominate the channel. When log.processor.request_id is bound in the container (RequestIdAddon registered before this addon), req.id is included in every geoip log line automatically.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`register()`](#geoipaddon-register) | public static | `void` | Registers the GeoIP middleware, Monolog processor, and geoip audit logger. |
| [`buildLogger()`](#geoipaddon-buildlogger) | private static | `Logger` | Builds a standalone geoip audit logger, used only when the app hasn't already registered its own logger.geoip channel via Dashboard::create(). Pushes SecretRedactionProcessor directly since this logger bypasses the app's own channel-creation loop where that would otherwise be applied. Picks up log.processor.request_id from the container when available so every geoip log line carries req.id for cross-log correlation. |

<details>
<summary><code>register()</code></summary>

#### `GeoIpAddon register()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpAddon.php#L54)

```php
public static function register(App $app, ContainerInterface $container): void
```

Registers the GeoIP middleware, Monolog processor, and geoip audit logger.

| Param | Type | Description |
|---|---|---|
| `$app` | `App` | Slim application instance. |
| `$container` | `ContainerInterface` | PHP-DI container from Dashboard::getContainer(). |

</details>

<details>
<summary><code>buildLogger()</code></summary>

#### `GeoIpAddon buildLogger()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpAddon.php#L121)

```php
private static function buildLogger(ContainerInterface $container): Logger
```

Builds a standalone geoip audit logger, used only when the app hasn't already registered its own logger.geoip channel via Dashboard::create(). Pushes SecretRedactionProcessor directly since this logger bypasses the app's own channel-creation loop where that would otherwise be applied. Picks up log.processor.request_id from the container when available so every geoip log line carries req.id for cross-log correlation.

| Param | Type | Description |
|---|---|---|
| `$container` | `ContainerInterface` | — |

**Returns:** `Logger`

</details>

## `GeoIpDriverInterface`

*interface* — `rafalmasiarek\DashboardKitGeoIp\GeoIpDriverInterface` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpDriverInterface.php#L15)

Contract for IP geolocation drivers. Implementations must be safe to call on every request — resolution results are not cached by the interface; caching is the driver's responsibility.

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`resolve()`](#geoipdriverinterface-resolve) | public | [`GeoIpResult`](#geoipresult) | Resolves geolocation data for the given IP address. Must never throw — return an empty GeoIpResult on any failure (private range, unresolvable IP, driver error). |

<details>
<summary><code>resolve()</code></summary>

#### `GeoIpDriverInterface resolve()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpDriverInterface.php#L26)

```php
public function resolve(string $ip): GeoIpResult
```

Resolves geolocation data for the given IP address. Must never throw — return an empty GeoIpResult on any failure (private range, unresolvable IP, driver error).

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IPv4 or IPv6 address. |

**Returns:** [`GeoIpResult`](#geoipresult)

</details>

## `GeoIpLogProcessor`

*final class* — `rafalmasiarek\DashboardKitGeoIp\GeoIpLogProcessor` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpLogProcessor.php#L23)

Monolog processor that appends GeoIP fields to every log record. Reads from $_SERVER at log-write time (not at boot), so GeoIpMiddleware only needs to have run before any route handler — no boot() or ordering dependency with other processors. Fields injected (when non-empty): req.country      — full country name req.country_code — ISO 3166-1 alpha-2 code req.city         — city name

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__invoke()`](#geoiplogprocessor-__invoke) | public | `LogRecord` | Invoked by Monolog for every log record. Existing context keys take priority — the processor only fills in keys that are not already present. |

<details>
<summary><code>__invoke()</code></summary>

#### `GeoIpLogProcessor __invoke()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpLogProcessor.php#L34)

```php
public function __invoke(LogRecord $record): LogRecord
```

Invoked by Monolog for every log record. Existing context keys take priority — the processor only fills in keys that are not already present.

| Param | Type | Description |
|---|---|---|
| `$record` | `LogRecord` | — |

**Returns:** `LogRecord`

</details>

## `GeoIpMiddleware`

*final class* — `rafalmasiarek\DashboardKitGeoIp\GeoIpMiddleware` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpMiddleware.php#L33)

PSR-15 middleware that populates $_SERVER with GeoIP fields for the current request IP. Checks each $_SERVER key individually before resolving — if all four keys are already present (set by nginx, Cloudflare, or another source), the driver is never called. Any keys that are missing are filled in from the driver result using ??= so existing values from the server layer are always preserved. When a logger is provided each driver call is recorded with ip, duration_ms, country, city, and status ('ok' | 'empty'). Keys written: $_SERVER['GEOIP_COUNTRY']      — full country name (e.g. "Poland") $_SERVER['GEOIP_COUNTRY_CODE'] — ISO 3166-1 alpha-2 code (e.g. "PL") $_SERVER['GEOIP_CITY']         — city name (e.g. "Warsaw") $_SERVER['GEOIP_REGION']       — subdivision name (e.g. "Masovian Voivodeship")

implements `MiddlewareInterface`

**Depends on:** [`GeoIpDriverInterface`](#geoipdriverinterface), `LoggerInterface`, `RealIpResolver`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#geoipmiddleware-__construct) | public | — | — |
| [`process()`](#geoipmiddleware-process) | public | `ResponseInterface` | Resolves and injects GeoIP data into $_SERVER, then passes the request downstream. |
| [`isPrivateIp()`](#geoipmiddleware-isprivateip) | private | `bool` | Returns true for private, loopback, and reserved IP ranges. Uses PHP's FILTER_FLAG_NO_PRIV_RANGE \| FILTER_FLAG_NO_RES_RANGE — if the filter rejects the IP, it is not a public routable address and driver resolution would always return empty results. |

<details>
<summary><code>__construct()</code></summary>

#### `GeoIpMiddleware __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpMiddleware.php#L47)

```php
public function __construct(GeoIpDriverInterface $driver, ?LoggerInterface $logger, ?RealIpResolver $resolver, bool $debug)
```

| Param | Type | Description |
|---|---|---|
| `$driver` | [`GeoIpDriverInterface`](#geoipdriverinterface) | Geolocation driver to call when data is missing. |
| `$logger` | `LoggerInterface\|null` | Optional logger for per-resolution audit lines. |
| `$resolver` | `RealIpResolver\|null` | When provided, the real client IP is resolved via RealIpResolver (handles X-Forwarded-For, Cloudflare, RFC 7239) instead of reading REMOTE_ADDR directly. Injected automatically by GeoIpAddon when dashboard-kit binds RealIpResolver in the container. |
| `$debug` | `bool` | When false (default), the per-resolution audit line is never written — this middleware runs on every request, so logging unconditionally would make it the dominant source of noise on the geoip channel. Set true (geoip.debug in app config) to see it. |

</details>

<details>
<summary><code>process()</code></summary>

#### `GeoIpMiddleware process()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpMiddleware.php#L62)

```php
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
```

Resolves and injects GeoIP data into $_SERVER, then passes the request downstream.

| Param | Type | Description |
|---|---|---|
| `$request` | `ServerRequestInterface` | — |
| `$handler` | `RequestHandlerInterface` | — |

**Returns:** `ResponseInterface`

</details>

<details>
<summary><code>isPrivateIp()</code></summary>

#### `GeoIpMiddleware isPrivateIp()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpMiddleware.php#L111)

```php
private function isPrivateIp(string $ip): bool
```

Returns true for private, loopback, and reserved IP ranges. Uses PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE — if the filter rejects the IP, it is not a public routable address and driver resolution would always return empty results.

| Param | Type | Description |
|---|---|---|
| `$ip` | `string` | IPv4 or IPv6 address. |

**Returns:** `bool`

</details>

## `GeoIpResult`

*final class* — `rafalmasiarek\DashboardKitGeoIp\GeoIpResult` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpResult.php#L15)

Immutable value object holding the resolved geolocation for a single IP address. All fields default to an empty string when the driver cannot determine the value (private ranges, unresolvable IPs, missing database entries).

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`__construct()`](#geoipresult-__construct) | public | — | — |

<details>
<summary><code>__construct()</code></summary>

#### `GeoIpResult __construct()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/GeoIpResult.php#L23)

```php
public function __construct(string $country, string $countryCode, string $city, string $region)
```

| Param | Type | Description |
|---|---|---|
| `$country` | `string` | Full country name (e.g. "Poland"). |
| `$countryCode` | `string` | ISO 3166-1 alpha-2 country code (e.g. "PL"). |
| `$city` | `string` | City name (e.g. "Warsaw"). |
| `$region` | `string` | Most specific subdivision name (e.g. "Masovian Voivodeship"). |

</details>

## `KvLineFormatter`

*class* — `rafalmasiarek\DashboardKitGeoIp\Log\KvLineFormatter` · [source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Log/KvLineFormatter.php#L25)

Formats a Monolog record as a flat key=value line. Default formatter for the geoip audit log channel. Can be replaced by binding a custom FormatterInterface in the container before calling GeoIpAddon::register(): $container->set('geoip.log.formatter', fn() => new JsonFormatter()); GeoIpAddon::register($app, $container); Output example: [2026-07-17 12:34:56] [level=info] [channel=geoip] resolved req.id=550e8400-... req.ip=1.2.3.4 duration_ms=42.3 country=Poland

implements `FormatterInterface`

| Method | Visibility | Returns | Summary |
|---|---|---|---|
| [`format()`](#kvlineformatter-format) | public | `string` | Formats a single log record into a kv line. |
| [`formatBatch()`](#kvlineformatter-formatbatch) | public | `string` | Formats a batch of log records. |
| [`escapeValue()`](#kvlineformatter-escapevalue) | private | `string` | Escapes a value for safe kv output. Strings with spaces or special characters are double-quoted. Arrays and objects are JSON-encoded. |

<details>
<summary><code>format()</code></summary>

#### `KvLineFormatter format()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Log/KvLineFormatter.php#L33)

```php
public function format(LogRecord $record): string
```

Formats a single log record into a kv line.

| Param | Type | Description |
|---|---|---|
| `$record` | `LogRecord` | — |

**Returns:** `string`

</details>

<details>
<summary><code>formatBatch()</code></summary>

#### `KvLineFormatter formatBatch()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Log/KvLineFormatter.php#L63)

```php
public function formatBatch(array $records): string
```

Formats a batch of log records.

| Param | Type | Description |
|---|---|---|
| `$records` | `LogRecord[]` | — |

**Returns:** `string`

</details>

<details>
<summary><code>escapeValue()</code></summary>

#### `KvLineFormatter escapeValue()`

[source](https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip/blob/main/src/Log/KvLineFormatter.php#L77)

```php
private function escapeValue(mixed $value): string
```

Escapes a value for safe kv output. Strings with spaces or special characters are double-quoted. Arrays and objects are JSON-encoded.

| Param | Type | Description |
|---|---|---|
| `$value` | `mixed` | — |

**Returns:** `string`

</details>




<!-- references -->

[repo_link]: https://github.com/rafalmasiarek/php-dashboard-kit-addon-geoip
