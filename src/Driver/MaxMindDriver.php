<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp\Driver;

use GeoIp2\Database\Reader;
use rafalmasiarek\DashboardKitGeoIp\GeoIpDriverInterface;
use rafalmasiarek\DashboardKitGeoIp\GeoIpResult;

/**
 * GeoIP driver backed by a local MaxMind GeoLite2 / GeoIP2 database file.
 *
 * Requires the geoip2/geoip2 package:
 *   composer require geoip2/geoip2
 *
 * Download a free GeoLite2-City.mmdb from https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
 * and set the path in the geoip.db_path config key.
 *
 * @package rafalmasiarek\DashboardKitGeoIp\Driver
 */
final class MaxMindDriver implements GeoIpDriverInterface
{
    /** @var Reader MaxMind database reader instance. */
    private Reader $reader;

    /**
     * @param string $dbPath Absolute path to the .mmdb database file.
     *
     * @throws \InvalidArgumentException When the file does not exist.
     */
    public function __construct(string $dbPath)
    {
        if (!\is_file($dbPath)) {
            throw new \InvalidArgumentException(
                "MaxMind database not found at: {$dbPath}. " .
                'Download GeoLite2-City.mmdb from https://dev.maxmind.com/geoip/geolite2-free-geolocation-data'
            );
        }

        $this->reader = new Reader($dbPath);
    }

    /**
     * Resolves geolocation from the local MaxMind database.
     *
     * Returns an empty GeoIpResult on private/reserved ranges or any reader error.
     *
     * @param  string $ip IPv4 or IPv6 address.
     * @return GeoIpResult
     */
    public function resolve(string $ip): GeoIpResult
    {
        try {
            $record = $this->reader->city($ip);

            return new GeoIpResult(
                country:     (string) ($record->country->name     ?? ''),
                countryCode: (string) ($record->country->isoCode  ?? ''),
                city:        (string) ($record->city->name        ?? ''),
                region:      (string) ($record->mostSpecificSubdivision->name ?? ''),
            );
        } catch (\Throwable) {
            return new GeoIpResult();
        }
    }
}
