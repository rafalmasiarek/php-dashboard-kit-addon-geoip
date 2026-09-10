<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp;

/**
 * Contract for IP geolocation drivers.
 *
 * Implementations must be safe to call on every request — resolution results
 * are not cached by the interface; caching is the driver's responsibility.
 *
 * @package rafalmasiarek\DashboardKitGeoIp
 */
interface GeoIpDriverInterface
{
    /**
     * Resolves geolocation data for the given IP address.
     *
     * Must never throw — return an empty GeoIpResult on any failure
     * (private range, unresolvable IP, driver error).
     *
     * @param  string $ip IPv4 or IPv6 address.
     * @return GeoIpResult
     */
    public function resolve(string $ip): GeoIpResult;
}
