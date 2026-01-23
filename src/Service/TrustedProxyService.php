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

/**
 * Service for configuring trusted proxies
 * Supports multiple providers: Cloudflare, AWS CloudFront, Fastly, Docker, custom ranges
 */
class TrustedProxyService
{
    /**
     * Cloudflare IP ranges
     * Updated: 2025-01
     * Source: https://www.cloudflare.com/ips/
     */
    protected const CLOUDFLARE_IPS = [
        // IPv4
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * AWS CloudFront IP ranges (common ranges)
     * For complete list, fetch from: https://ip-ranges.amazonaws.com/ip-ranges.json
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
        '10.0.0.0/8',     // Custom networks
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
     * Configure trusted proxies on the request instance
     */
    public function configure(): void
    {
        $request = \request();

        $providers = $this->config->get('trustedproxies.providers', []);
        $customRanges = $this->config->get('trustedproxies.custom_ranges', []);
        $headers = $this->getTrustedHeaders();

        $trustedProxies = $this->buildTrustedProxies($providers, $customRanges);

        if (empty($trustedProxies)) {
            return;
        }

        // Set trusted proxies and headers
        $request->setTrustedProxies($trustedProxies, $headers);

        // Handle provider-specific headers
        $this->handleProviderSpecificHeaders($providers);
    }

    /**
     * Build the array of trusted proxy IPs based on enabled providers
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
     * Get IP ranges for a specific provider
     */
    protected function getProviderIps(string $provider): array
    {
        return match (\strtolower($provider)) {
            'cloudflare' => self::CLOUDFLARE_IPS,
            'aws_cloudfront', 'cloudfront' => self::AWS_CLOUDFRONT_IPS,
            'fastly' => self::FASTLY_IPS,
            'docker' => self::DOCKER_IPS,
            default => [],
        };
    }

    /**
     * Get trusted headers configuration
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
     * Handle provider-specific headers (e.g., Cloudflare's CF-Connecting-IP)
     */
    protected function handleProviderSpecificHeaders(array $providers): void
    {
        if (\in_array('cloudflare', $providers) && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            // Cloudflare provides the real client IP in CF-Connecting-IP header
            // We set it as REMOTE_ADDR so Laravel's Request::ip() picks it up
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (\in_array('fastly', $providers) && isset($_SERVER['HTTP_FASTLY_CLIENT_IP'])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_FASTLY_CLIENT_IP'];
        }
    }

    /**
     * Check if a specific provider is enabled
     */
    public function isProviderEnabled(string $provider): bool
    {
        return \in_array($provider, $this->config->get('trustedproxies.providers', []));
    }

    /**
     * Get all trusted proxy IPs currently configured
     */
    public function getTrustedProxies(): array
    {
        $providers = $this->config->get('trustedproxies.providers', []);
        $customRanges = $this->config->get('trustedproxies.custom_ranges', []);

        return $this->buildTrustedProxies($providers, $customRanges);
    }
}
