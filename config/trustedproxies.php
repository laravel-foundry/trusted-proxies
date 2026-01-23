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

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxy Providers
    |--------------------------------------------------------------------------
    |
    | Define which proxy providers to trust. Available providers:
    | - cloudflare: Cloudflare CDN
    | - aws_cloudfront: AWS CloudFront
    | - fastly: Fastly CDN
    | - docker: Docker internal networks (for development and Docker Swarm)
    |
    | You can enable multiple providers at once.
    | Use comma-separated values in .env file:
    | TRUSTED_PROXY_PROVIDERS=cloudflare,docker
    |
    */

    'providers' => \array_filter(\array_map('trim', \explode(',', \env('TRUSTED_PROXY_PROVIDERS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Custom IP Ranges
    |--------------------------------------------------------------------------
    |
    | Add any custom IP ranges or specific IPs to trust.
    | Useful for:
    | - Internal load balancers
    | - Custom reverse proxies
    | - Specific VPN endpoints
    |
    | Format: CIDR notation or single IPs (comma-separated in .env)
    | Example in .env: TRUSTED_PROXY_CUSTOM_RANGES=192.168.1.0/24,10.0.0.1
    |
    */

    'custom_ranges' => \array_filter(\array_map('trim', \explode(',', \env('TRUSTED_PROXY_CUSTOM_RANGES', '')))),

    /*
    |--------------------------------------------------------------------------
    | Additional Headers
    |--------------------------------------------------------------------------
    |
    | Additional proxy headers to trust beyond the standard ones.
    | By default, the following are always trusted:
    | - X-Forwarded-For
    | - X-Forwarded-Host
    | - X-Forwarded-Port
    | - X-Forwarded-Proto
    |
    | Additional available headers:
    | - X-Forwarded-Prefix
    | - X-Forwarded-AWS-ELB
    |
    */

    'headers' => [
        // 'X-Forwarded-Prefix',
        // 'X-Forwarded-AWS-ELB',
    ],
];
