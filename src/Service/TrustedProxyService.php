<?php

declare(strict_types=1);

/*
 * This file is part of the Laravel trusted proxies package.
 *
 * (ɔ) Frugan <dev@frugan.it>
 *
 * This source file is subject to the GNU GPLv3 license that is bundled
 * with this source code in the file COPYING.
 */

namespace LaravelFoundry\TrustedProxies\Service;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Monicahq\Cloudflare\Facades\CloudflareProxies;

/**
 * Service for configuring trusted proxies.
 * Supports multiple providers: Cloudflare, AWS CloudFront, Fastly, Docker, custom ranges.
 */
class TrustedProxyService
{
    /**
     * AWS CloudFront IP ranges (common ranges)
     * For the complete list, fetch from: https://ip-ranges.amazonaws.com/ip-ranges.json
     */
    protected const AWS_CLOUDFRONT_IPS = [
        '13.32.0.0/15',
        '13.35.0.0/16',
        '13.224.0.0/14',
        '52.84.0.0/15',
        '54.230.0.0/16',
        '54.239.128.0/18',
        '99.84.0.0/16',
        '130.176.0.0/16',
        '204.246.164.0/22',
        '205.251.192.0/19',
    ];

    /**
     * Fastly IP ranges
     * Source: https://api.fastly.com/public-ip-list
     */
    protected const FASTLY_IPS = [
        '23.235.32.0/20',
        '43.249.72.0/22',
        '103.244.50.0/24',
        '103.245.222.0/23',
        '103.245.224.0/24',
        '104.156.80.0/20',
        '151.101.0.0/16',
        '157.52.64.0/18',
        '167.82.0.0/17',
        '167.82.128.0/20',
        '167.82.160.0/20',
        '167.82.224.0/20',
        '172.111.64.0/18',
        '185.31.16.0/22',
        '199.27.72.0/21',
        '199.232.0.0/16',
    ];

    /**
     * Docker default network ranges
     */
    protected const DOCKER_IPS = [
        '172.16.0.0/12',  // Default bridge network
        '10.0.0.0/8',     // Custom networks and Swarm ingress
        '192.168.0.0/16', // Host network
    ];

    /**
     * @var Config
     */
    protected $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Configure trusted proxies on the request instance.
     */
    public function configure(): void
    {
        $providers = $this->config->get('trustedproxies.providers', []);
        $customRanges = $this->config->get('trustedproxies.custom_ranges', []);
        $headers = $this->getTrustedHeaders();

        $trustedProxies = $this->buildTrustedProxies($providers, $customRanges);

        if (empty($trustedProxies)) {
            return;
        }

        \request()->setTrustedProxies($trustedProxies, $headers);
    }

    /**
     * Build the array of trusted proxy IPs based on enabled providers.
     */
    protected function buildTrustedProxies(array $providers, array $customRanges): array
    {
        $trustedProxies = [];

        foreach ($providers as $provider) {
            $trustedProxies = \array_merge($trustedProxies, $this->getProviderIps($provider));
        }

        // Add custom ranges
        $trustedProxies = \array_merge($trustedProxies, $customRanges);

        return \array_unique($trustedProxies);
    }

    /**
     * Get IP ranges for a specific provider.
     * Cloudflare IPs are fetched dynamically (with cache) via monicahq/laravel-cloudflare.
     */
    protected function getProviderIps(string $provider): array
    {
        return match (\strtolower($provider)) {
            'cloudflare' => CloudflareProxies::load(),
            'aws_cloudfront', 'cloudfront' => self::AWS_CLOUDFRONT_IPS,
            'fastly' => self::FASTLY_IPS,
            'docker' => self::DOCKER_IPS,
            default => [],
        };
    }

    /**
     * Get trusted headers configuration.
     */
    protected function getTrustedHeaders(): int
    {
        $headers = Request::HEADER_X_FORWARDED_FOR
                 | Request::HEADER_X_FORWARDED_HOST
                 | Request::HEADER_X_FORWARDED_PORT
                 | Request::HEADER_X_FORWARDED_PROTO;

        // Add custom headers if configured
        $customHeaders = $this->config->get('trustedproxies.headers', []);

        foreach ($customHeaders as $header) {
            $headers |= match ($header) {
                'X-Forwarded-Prefix' => Request::HEADER_X_FORWARDED_PREFIX,
                'X-Forwarded-AWS-ELB' => Request::HEADER_X_FORWARDED_AWS_ELB,
                default => 0,
            };
        }

        return $headers;
    }

    /**
     * Check if a specific provider is enabled.
     */
    public function isProviderEnabled(string $provider): bool
    {
        return \in_array($provider, $this->config->get('trustedproxies.providers', []));
    }

    /**
     * Get all trusted proxy IPs currently configured.
     */
    public function getTrustedProxies(): array
    {
        $providers = $this->config->get('trustedproxies.providers', []);
        $customRanges = $this->config->get('trustedproxies.custom_ranges', []);

        return $this->buildTrustedProxies($providers, $customRanges);
    }
}
