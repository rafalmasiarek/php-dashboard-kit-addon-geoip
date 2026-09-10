<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp;

use Monolog\LogRecord;

/**
 * Monolog processor that appends GeoIP fields to every log record.
 *
 * Reads from $_SERVER at log-write time (not at boot), so GeoIpMiddleware
 * only needs to have run before any route handler — no boot() or ordering
 * dependency with other processors.
 *
 * Fields injected (when non-empty):
 *   req.country      — full country name
 *   req.country_code — ISO 3166-1 alpha-2 code
 *   req.city         — city name
 *
 * @package rafalmasiarek\DashboardKitGeoIp
 */
final class GeoIpLogProcessor
{
    /**
     * Invoked by Monolog for every log record.
     *
     * Existing context keys take priority — the processor only fills in keys
     * that are not already present.
     *
     * @param  LogRecord $record
     * @return LogRecord
     */
    public function __invoke(LogRecord $record): LogRecord
    {
        $geo = \array_filter([
            'req.country'      => $_SERVER['GEOIP_COUNTRY']      ?? null,
            'req.country_code' => $_SERVER['GEOIP_COUNTRY_CODE'] ?? null,
            'req.city'         => $_SERVER['GEOIP_CITY']         ?? null,
        ], static fn($v) => $v !== null && $v !== '');

        if (empty($geo)) {
            return $record;
        }

        return $record->with(context: $record->context + $geo);
    }
}
