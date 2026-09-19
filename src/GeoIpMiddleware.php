<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use rafalmasiarek\RealIpResolver;

/**
 * PSR-15 middleware that populates $_SERVER with GeoIP fields for the current request IP.
 *
 * Checks each $_SERVER key individually before resolving — if all four keys are already
 * present (set by nginx, Cloudflare, or another source), the driver is never called.
 * Any keys that are missing are filled in from the driver result using ??= so existing
 * values from the server layer are always preserved.
 *
 * When a logger is provided each driver call is recorded with ip, duration_ms,
 * country, city, and status ('ok' | 'empty').
 *
 * Keys written:
 *   $_SERVER['GEOIP_COUNTRY']      — full country name (e.g. "Poland")
 *   $_SERVER['GEOIP_COUNTRY_CODE'] — ISO 3166-1 alpha-2 code (e.g. "PL")
 *   $_SERVER['GEOIP_CITY']         — city name (e.g. "Warsaw")
 *   $_SERVER['GEOIP_REGION']       — subdivision name (e.g. "Masovian Voivodeship")
 *
 * @package rafalmasiarek\DashboardKitGeoIp
 */
final class GeoIpMiddleware implements MiddlewareInterface
{
    /**
     * @param GeoIpDriverInterface  $driver   Geolocation driver to call when data is missing.
     * @param LoggerInterface|null  $logger   Optional logger for per-resolution audit lines.
     * @param RealIpResolver|null   $resolver When provided, the real client IP is resolved via
     *   RealIpResolver (handles X-Forwarded-For, Cloudflare, RFC 7239) instead of reading
     *   REMOTE_ADDR directly. Injected automatically by GeoIpAddon when dashboard-kit binds
     *   RealIpResolver in the container.
     * @param bool $debug When false (default), the per-resolution audit line is never
     *   written — this middleware runs on every request, so logging unconditionally would
     *   make it the dominant source of noise on the geoip channel. Set true (geoip.debug in
     *   app config) to see it.
     */
    public function __construct(
        private readonly GeoIpDriverInterface $driver,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RealIpResolver $resolver = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * Resolves and injects GeoIP data into $_SERVER, then passes the request downstream.
     *
     * @param  ServerRequestInterface  $request
     * @param  RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->resolver !== null
            ? ($this->resolver->getIp() ?: '')
            : (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');

        if ($ip !== '' && !$this->isPrivateIp($ip)) {
            $needsResolve = !isset($_SERVER['GEOIP_COUNTRY'])
                || !isset($_SERVER['GEOIP_COUNTRY_CODE'])
                || !isset($_SERVER['GEOIP_CITY'])
                || !isset($_SERVER['GEOIP_REGION']);

            if ($needsResolve) {
                $t0     = \microtime(true);
                $result = $this->driver->resolve($ip);
                $ms     = \round((\microtime(true) - $t0) * 1000, 2);

                $_SERVER['GEOIP_COUNTRY']      ??= $result->country;
                $_SERVER['GEOIP_COUNTRY_CODE'] ??= $result->countryCode;
                $_SERVER['GEOIP_CITY']         ??= $result->city;
                $_SERVER['GEOIP_REGION']       ??= $result->region;

                if ($this->debug) {
                    $this->logger?->info('geoip.resolve.ok', [
                        'req.ip'       => $ip,
                        'source'       => 'middleware',
                        'duration_ms'  => $ms,
                        'country'      => $result->country,
                        'country_code' => $result->countryCode,
                        'city'         => $result->city,
                        'region'       => $result->region,
                    ]);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * Returns true for private, loopback, and reserved IP ranges.
     *
     * Uses PHP's FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE — if the
     * filter rejects the IP, it is not a public routable address and driver
     * resolution would always return empty results.
     *
     * @param  string $ip IPv4 or IPv6 address.
     * @return bool
     */
    private function isPrivateIp(string $ip): bool
    {
        return \filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
