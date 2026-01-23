![PHP Version](https://img.shields.io/packagist/php-v/laravel-foundry/trusted-proxies)
![Packagist Downloads](https://img.shields.io/packagist/dt/laravel-foundry/trusted-proxies)
![Packagist Stars](https://img.shields.io/packagist/stars/laravel-foundry/trusted-proxies)
![GitHub Actions Workflow Status](https://github.com/laravel-foundry/trusted-proxies/actions/workflows/release.yml/badge.svg)
![Coverage Status](https://img.shields.io/codecov/c/github/laravel-foundry/trusted-proxies)
![Known Vulnerabilities](https://snyk.io/test/github/laravel-foundry/trusted-proxies/badge.svg)
![GitHub Issues](https://img.shields.io/github/issues/laravel-foundry/trusted-proxies)
![GitHub Release](https://img.shields.io/github/v/release/laravel-foundry/trusted-proxies)
![License](https://img.shields.io/github/license/laravel-foundry/trusted-proxies)
<!--
Qlty @see https://github.com/badges/shields/issues/11192
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/laravel-foundry/trusted-proxies/total)
![Code Climate](https://img.shields.io/codeclimate/maintainability/laravel-foundry/trusted-proxies)
![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen)
-->

# Laravel Trusted Proxies

Laravel trusted proxies configuration for applications behind CDNs, load balancers, or Docker networks.

## Features

- **Multiple CDN Support** - Cloudflare, AWS CloudFront, Fastly out of the box
- **Docker Swarm Ready** - Handles Docker ingress networks and overlay networks
- **Environment-Based Config** - Different settings for dev, staging, production
- **Custom Proxy Support** - Add your own load balancers or reverse proxies
- **Zero Configuration** - Works out of the box with sensible defaults
- **Laravel Native** - Uses Laravel's built-in Request::setTrustedProxies()

## Why This Package?

When running Laravel behind proxies (CDN, Docker Swarm, load balancers), Laravel sees the proxy's IP instead of the real client IP. This breaks:

- Rate limiting
- IP-based access control
- Geolocation
- Logging and analytics
- Security features

This package configures Laravel to trust specific proxies and extract the real client IP from headers like `X-Forwarded-For` and `CF-Connecting-IP`.

## Requirements

- PHP >= 8.2
- Laravel Illuminate/Support ^10.0|^11.0|^12.0
- Laravel Illuminate/HTTP ^10.0|^11.0|^12.0

## Installation

### 1. Install the package

```bash
composer require laravel-foundry/trusted-proxies
```

The package auto-registers via Laravel's service provider discovery.

### 2. Configure environment variables

Add to your `.env` file:

```bash
# Development (local Docker)
TRUSTED_PROXY_PROVIDERS=docker

# Staging (Docker + Cloudflare)
TRUSTED_PROXY_PROVIDERS=cloudflare,docker

# Production (Docker Swarm + Cloudflare)
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

That's it! The package will automatically configure trusted proxies on every request.

## Configuration

### Environment Variables

The package uses environment variables for configuration:

```bash
# Comma-separated list of providers
TRUSTED_PROXY_PROVIDERS=cloudflare,docker

# Custom IP ranges (optional)
TRUSTED_PROXY_CUSTOM_RANGES=10.20.0.0/16,192.168.100.5
```

### Available Providers

- `cloudflare` - Cloudflare CDN (includes CF-Connecting-IP header)
- `aws_cloudfront` - AWS CloudFront CDN
- `fastly` - Fastly CDN (includes Fastly-Client-IP header)
- `docker` - Docker networks (bridge, custom, Swarm ingress)

### Publish Configuration (Optional)

For advanced customization, publish the config file:

```bash
php artisan vendor:publish --tag=trustedproxies-config
```

This creates `config/trustedproxies.php` where you can customize settings.

## Usage

### Basic Usage

After installation, Laravel automatically gets the real client IP:

```php
// Get real client IP (not proxy IP)
$clientIp = request()->ip();

// Use in your application
Log::info('Request from IP', ['ip' => $clientIp]);
```

### Environment-Specific Configurations

#### Local Development (.env.development)

```bash
# Only trust Docker internal networks
TRUSTED_PROXY_PROVIDERS=docker
```

#### Staging (.env.staging)

```bash
# Trust Docker + Cloudflare
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

#### Production with Docker Swarm (.env.production)

```bash
# Trust Docker (for Swarm ingress) + Cloudflare
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

**Important:** Always keep `docker` enabled in production when using Docker Swarm, because the Swarm ingress network acts as an internal proxy.

### Custom Load Balancer

If you have an additional load balancer:

```bash
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
TRUSTED_PROXY_CUSTOM_RANGES=10.20.0.0/16
```

### Multiple CDN Providers

```bash
# Using both Cloudflare and Fastly
TRUSTED_PROXY_PROVIDERS=cloudflare,fastly,docker
```

## Common Use Cases

### Rate Limiting

```php
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->ip());
});
```

### IP Whitelisting

```php
namespace App\Http\Middleware;

class IpWhitelist
{
    public function handle($request, $next)
    {
        $allowedIps = ['1.2.3.4', '5.6.7.8'];
        
        if (!in_array(request()->ip(), $allowedIps)) {
            abort(403, 'Access denied');
        }
        
        return $next($request);
    }
}
```

### Logging Real IPs

```php
use Illuminate\Support\Facades\Log;

Log::channel('daily')->info('User action', [
    'ip' => request()->ip(),
    'user_id' => auth()->id(),
    'action' => 'login',
]);
```

### Geolocation

```php
use Illuminate\Support\Facades\Http;

$ip = request()->ip();
$location = Http::get("http://ip-api.com/json/{$ip}")->json();

// Use real IP for accurate geolocation
```

## How It Works

### Architecture

```
Internet → CDN (Cloudflare) → Your Server → Docker Swarm Ingress → Container
         ↓ adds CF-Connecting-IP              ↓ adds X-Forwarded-*
         
Package configures Laravel to:
1. Trust IPs from Cloudflare and Docker networks
2. Read real client IP from CF-Connecting-IP header
3. Fall back to X-Forwarded-For if needed
```

### Technical Details

**Request Flow:**
1. Request arrives at CDN (e.g., Cloudflare)
2. CDN adds headers: `CF-Connecting-IP`, `X-Forwarded-For`
3. Request reaches your server
4. If using Docker Swarm, goes through ingress network
5. Package configures `Request::setTrustedProxies()` with:
   - CDN IP ranges (Cloudflare, AWS, Fastly)
   - Docker network ranges (10.0.0.0/8, 172.16.0.0/12)
   - Custom ranges (if configured)
6. Laravel extracts real IP from trusted headers
7. `request()->ip()` returns real client IP

**Provider-Specific Headers:**
- Cloudflare: Uses `CF-Connecting-IP` (most reliable)
- Fastly: Uses `Fastly-Client-IP`
- Others: Use `X-Forwarded-For` (first non-private IP)

### Why Trust Docker in Production?

With Docker Swarm, requests flow through the **ingress network** before reaching your container:

```
Client → Cloudflare → Your VPS → Swarm Ingress (10.0.x.x) → Container
```

Without trusting Docker IPs, you'd see the ingress IP (10.0.x.x) instead of the real client IP. Cloudflare sends the real IP in `CF-Connecting-IP`, but you still need to trust the ingress network to process it correctly.

## Troubleshooting

### Still seeing proxy IPs?

Check your configuration:

```php
// In tinker or any PHP file
$service = app(\LaravelFoundry\TrustedProxies\Service\TrustedProxyService::class);

// Check enabled providers
$providers = config('trustedproxies.providers');
var_dump($providers);

// Check all trusted IPs
$trustedIps = $service->getTrustedProxies();
var_dump($trustedIps);

// Check current IP
var_dump(request()->ip());
```

### Cloudflare not working?

Verify Cloudflare is passing the header:

```php
// Check if header is present
var_dump($_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'not set');
```

Make sure Cloudflare's orange cloud is enabled (proxying traffic).

### Docker Swarm issues?

Verify the ingress network:

```bash
# Check Docker network
docker network inspect ingress

# Check your container's networks
docker inspect <container-id> | grep -A 20 Networks
```

### Wrong IP being returned?

Enable debug logging:

```php
// In tinker or temporarily in code
Log::debug('IP Detection', [
    'request_ip' => request()->ip(),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'cf_connecting_ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
    'x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
    'trusted_proxies' => $service->getTrustedProxies(),
]);
```

## Testing

```bash
composer test
```

## More info

See [here](docs/README.md).

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for a detailed list of changes for each release.

We follow [Semantic Versioning](https://semver.org/) and use [Conventional Commits](https://www.conventionalcommits.org/) to automatically generate our changelog.

### Release Process

- **Major versions** (1.0.0 → 2.0.0): Breaking changes
- **Minor versions** (1.0.0 → 1.1.0): New features, backward compatible
- **Patch versions** (1.0.0 → 1.0.1): Bug fixes, backward compatible

All releases are automatically created when changes are pushed to the `main` branch, based on commit message conventions.

## Contributing

For your contributions please use:

- [Conventional Commits](https://www.conventionalcommits.org)
- [Pull request workflow](https://docs.github.com/en/get-started/exploring-projects-on-github/contributing-to-a-project)

See [CONTRIBUTING](.github/CONTRIBUTING.md) for detailed guidelines.

## Sponsor

[<img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" width="200" alt="Buy Me A Coffee">](https://buymeacoff.ee/frugan)

## License

(ɔ) Copyleft 2026 [Frugan](https://frugan.it).  
[GNU GPLv3](https://choosealicense.com/licenses/gpl-3.0/), see [LICENSE](LICENSE) file.
