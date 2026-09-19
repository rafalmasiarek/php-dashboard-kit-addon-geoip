<?php

declare(strict_types=1);

namespace rafalmasiarek\DashboardKitGeoIp;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use rafalmasiarek\DashboardKit\Log\SecretRedactionProcessor;
use rafalmasiarek\DashboardKitGeoIp\Log\KvLineFormatter;
use rafalmasiarek\DashboardKitGeoIp\Driver\MaxMindDriver;
use rafalmasiarek\RealIpResolver;
use Slim\App;

/**
 * Registers GeoIP middleware and log processor for a dashboard-kit application.
 *
 * Driver resolution — checked in this order:
 *   1. GeoIpDriverInterface::class already bound in container (custom driver from app)
 *   2. MaxMindDriver using geoip.db_path from app.config (default)
 *
 * To use a custom driver, bind it before calling register():
 *
 *   $container->set(GeoIpDriverInterface::class, fn() => new IpApiDriver());
 *   GeoIpAddon::register($dashboard->getApp(), $container);
 *
 * Uses the app's own logger.geoip channel when Dashboard::create() already
 * registered one (config['logging']['geoip']) — so this channel gets the same
 * processors (SecretRedactionProcessor, RequestHeadersProcessor) as every
 * other channel. Falls back to a standalone logger writing to
 * {logs_dir}/geoip.log (still protected by SecretRedactionProcessor) when the
 * app didn't configure that channel.
 *
 * The per-resolution audit line (one per driver call, with ip, duration_ms,
 * country, city) is only written when geoip.debug is true — the middleware
 * runs on every request, so logging unconditionally would dominate the channel.
 * When log.processor.request_id is bound in the container (RequestIdAddon registered
 * before this addon), req.id is included in every geoip log line automatically.
 *
 * @package rafalmasiarek\DashboardKitGeoIp
 */
final class GeoIpAddon
{
    /**
     * Registers the GeoIP middleware, Monolog processor, and geoip audit logger.
     *
     * @param App                $app       Slim application instance.
     * @param ContainerInterface $container PHP-DI container from Dashboard::getContainer().
     * @return void
     */
    public static function register(App $app, ContainerInterface $container): void
    {
        if (!\class_exists(\rafalmasiarek\DashboardKit\Dashboard::class)) {
            throw new \LogicException(
                static::class . ' is a dashboard-kit addon and requires rafalmasiarek/dashboard-kit. '
                . 'Run: composer require rafalmasiarek/dashboard-kit'
            );
        }

        if (!$container->has(GeoIpDriverInterface::class)) {
            $config = (array) ($container->get('app.config')['geoip'] ?? []);
            $dbPath = (string) ($config['db_path'] ?? '');

            if ($dbPath === '') {
                return;
            }

            $container->set(
                GeoIpDriverInterface::class,
                static fn() => new MaxMindDriver($dbPath)
            );
        }

        $driver    = $container->get(GeoIpDriverInterface::class);
        $processor = new GeoIpLogProcessor();

        foreach (['app', 'audit', 'error'] as $channel) {
            try {
                $logger = $container->get('logger.' . $channel);
                if ($logger instanceof Logger) {
                    $logger->pushProcessor($processor);
                }
            } catch (\Throwable) {
            }
        }

        // Prefer a logger.geoip already registered by Dashboard::create() (from
        // config['logging']['geoip']) so this channel gets the same processors
        // (SecretRedactionProcessor, RequestHeadersProcessor) as every other
        // channel. Only build a standalone one as a fallback when the app didn't
        // configure that channel — still redaction-protected either way.
        $hasSharedLogger = $container->has('logger.geoip');
        $geoipLogger     = $hasSharedLogger ? $container->get('logger.geoip') : self::buildLogger($container);

        if (!$hasSharedLogger) {
            $container->set('logger.geoip', static fn() => $geoipLogger);
        }

        $geoipConfig = (array) ($container->get('app.config')['geoip'] ?? []);
        $debug       = (bool) ($geoipConfig['debug'] ?? false);

        $resolver = $container->has(RealIpResolver::class) ? $container->get(RealIpResolver::class) : null;
        $app->add(new GeoIpMiddleware($driver, $geoipLogger, $resolver, $debug));
    }

    /**
     * Builds a standalone geoip audit logger, used only when the app hasn't
     * already registered its own logger.geoip channel via Dashboard::create().
     *
     * Pushes SecretRedactionProcessor directly since this logger bypasses the
     * app's own channel-creation loop where that would otherwise be applied.
     * Picks up log.processor.request_id from the container when available
     * so every geoip log line carries req.id for cross-log correlation.
     *
     * @param  ContainerInterface $container
     * @return Logger
     */
    private static function buildLogger(ContainerInterface $container): Logger
    {
        $appConfig = (array) ($container->get('app.config') ?? []);
        $geoipCfg  = (array) ($appConfig['geoip'] ?? []);
        $rootDir   = '';

        try {
            $rootDir = (string) $container->get('app.root_dir');
        } catch (\Throwable) {
        }

        $logsDir = (string) ($appConfig['logs_dir'] ?? ($rootDir !== '' ? $rootDir . '/storage/logs' : sys_get_temp_dir()));
        $days    = (int) ($geoipCfg['log_days'] ?? 30);

        $formatter = null;
        try {
            $candidate = $container->get('geoip.log.formatter');
            if ($candidate instanceof FormatterInterface) {
                $formatter = $candidate;
            }
        } catch (\Throwable) {
        }

        $handler = new RotatingFileHandler($logsDir . '/geoip.log', $days, Level::Info);
        $handler->setFormatter($formatter ?? new KvLineFormatter());

        $logger = new Logger('geoip');
        $logger->pushHandler($handler);
        $logger->pushProcessor(new SecretRedactionProcessor());

        try {
            $reqIdProcessor = $container->get('log.processor.request_id');
            $logger->pushProcessor($reqIdProcessor);
        } catch (\Throwable) {
        }

        return $logger;
    }
}
