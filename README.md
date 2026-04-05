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

## Why This Package?

Laravel has built-in support for trusted proxies, but configuring it correctly for real-world infrastructure — especially when combining a CDN with Docker Swarm — requires non-trivial setup across multiple files.

This package solves that with a **single `.env` variable**:

```bash
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

That's it. No middleware changes, no PHP configuration files, no hardcoded IP lists to maintain.

### What it does that Laravel doesn't out of the box

- **Multi-provider orchestration** — declare multiple providers (`cloudflare`, `aws_cloudfront`, `fastly`, `docker`) and the package merges their IP ranges automatically
- **Dynamic Cloudflare IPs** — Cloudflare IP ranges are fetched from the official API and cached via [`monicahq/laravel-cloudflare`](https://github.com/monicahq/laravel-cloudflare), so they never go stale
- **Docker Swarm aware** — includes the Docker internal network ranges (`10.0.0.0/8`, `172.16.0.0/12`) needed when the Swarm ingress acts as an internal proxy
- **Environment-based** — different providers per environment (local, staging, production) without touching PHP code

### What it does not do

It does not replace or wrap Laravel's `TrustProxies` middleware. It configures `Request::setTrustedProxies()` directly, letting Symfony's battle-tested header resolution handle everything.

## Features

- **Multiple CDN Support** — Cloudflare (dynamic), AWS CloudFront, Fastly
- **Docker Swarm Ready** — handles Docker ingress networks and overlay networks
- **Environment-Based Config** — different settings for dev, staging, production
- **Custom Proxy Support** — add your own load balancers or reverse proxies
- **Zero Configuration** — works out of the box with sensible defaults
- **Laravel Native** — uses Laravel's built-in `Request::setTrustedProxies()`

## Requirements

- PHP >= 8.2
- Laravel Illuminate/Support `^10.0 | ^11.0 | ^12.0 | ^13.0`
- Laravel Illuminate/HTTP `^10.0 | ^11.0 | ^12.0 | ^13.0`

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

That's it. The package will automatically configure trusted proxies on every request.

## Configuration

### Environment Variables

```bash
# Comma-separated list of providers
TRUSTED_PROXY_PROVIDERS=cloudflare,docker

# Custom IP ranges (optional)
TRUSTED_PROXY_CUSTOM_RANGES=10.20.0.0/16,192.168.100.5
```

### Available Providers

| Provider | Description | IP Source |
|---|---|---|
| `cloudflare` | Cloudflare CDN | Dynamic via API (cached) |
| `aws_cloudfront` | AWS CloudFront CDN | Static ranges |
| `fastly` | Fastly CDN | Static ranges |
| `docker` | Docker networks (bridge, custom, Swarm ingress) | Static ranges |

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
```

### Environment-Specific Configurations

#### Local Development

```bash
TRUSTED_PROXY_PROVIDERS=docker
```

#### Staging

```bash
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

#### Production with Docker Swarm

```bash
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
```

**Important:** Always keep `docker` enabled in production when using Docker Swarm. The Swarm ingress network acts as an internal proxy, so without trusting Docker IP ranges the real client IP cannot be resolved correctly even when Cloudflare provides it in `CF-Connecting-IP`.

### Custom Load Balancer

```bash
TRUSTED_PROXY_PROVIDERS=cloudflare,docker
TRUSTED_PROXY_CUSTOM_RANGES=10.20.0.0/16
```

### Multiple CDN Providers

```bash
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

## How It Works

### Architecture

```
Internet → CDN (Cloudflare) → Your Server → Docker Swarm Ingress → Container
         ↓ adds CF-Connecting-IP              ↓ adds X-Forwarded-*

Package configures Laravel to:
1. Trust IPs from Cloudflare (dynamic) and Docker networks
2. Let Symfony resolve the real client IP from X-Forwarded-For
3. request()->ip() returns the real client IP
```

### Why Trust Docker in Production?

With Docker Swarm, requests flow through the **ingress network** before reaching your container:

```
Client → Cloudflare → Your VPS → Swarm Ingress (10.0.x.x) → Container
```

Without trusting Docker IPs, you'd see the ingress IP (`10.0.x.x`) instead of the real client IP. Cloudflare sends the real IP in `CF-Connecting-IP`, but you still need to trust the ingress network for Symfony to process the forwarded headers correctly.

### Cloudflare IP Ranges

Cloudflare IP ranges are resolved dynamically at runtime via [`monicahq/laravel-cloudflare`](https://github.com/monicahq/laravel-cloudflare), which fetches them from `https://www.cloudflare.com/ips-v4` and `ips-v6` and stores them in Laravel's cache. You should schedule a periodic cache refresh to keep them up to date:

```bash
php artisan cloudflare:reload
```

Or via Laravel scheduler in `routes/console.php`:

```php
Schedule::command('cloudflare:reload')->daily();
```

## Troubleshooting

### Still seeing proxy IPs?

```php
// In tinker or any PHP file
$service = app(\LaravelFoundry\TrustedProxies\Service\TrustedProxyService::class);

var_dump(config('trustedproxies.providers'));
var_dump($service->getTrustedProxies());
var_dump(request()->ip());
```

### Docker Swarm issues?

```bash
# Check the ingress network
docker network inspect ingress

# Check your container's networks
docker inspect <container-id> | grep -A 20 Networks
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
