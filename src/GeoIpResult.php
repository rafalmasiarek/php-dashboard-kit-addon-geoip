<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp;

/**
 * Immutable value object holding the resolved geolocation for a single IP address.
 *
 * All fields default to an empty string when the driver cannot determine the value
 * (private ranges, unresolvable IPs, missing database entries).
 *
 * @package rafalmasiarek\DashboardKitGeoIp
 */
final class GeoIpResult
{
    /**
     * @param string $country     Full country name (e.g. "Poland").
     * @param string $countryCode ISO 3166-1 alpha-2 country code (e.g. "PL").
     * @param string $city        City name (e.g. "Warsaw").
     * @param string $region      Most specific subdivision name (e.g. "Masovian Voivodeship").
     */
    public function __construct(
        public readonly string $country     = '',
        public readonly string $countryCode = '',
        public readonly string $city        = '',
        public readonly string $region      = '',
    ) {
    }
}
