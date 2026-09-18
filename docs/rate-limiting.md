# Rate Limiting

The shipping estimate endpoint is unauthenticated and, depending on your shipping calculators, a single request can trigger a call to an external carrier API. The plugin can rate limit it using the [Symfony RateLimiter component](https://symfony.com/doc/current/rate_limiter.html).

Every estimate response is also sent with `Cache-Control: no-store, private`, so a shared cache or CDN in front of your shop never serves one customer's rates to another.

## Installing

The component is an optional dependency:

```bash
composer require symfony/rate-limiter
```

The plugin's default reflects whether you have it:

- **installed** — rate limiting is **on** by default, and you can turn it off
- **not installed** — rate limiting is **off** by default, and the configuration is inert

## Configuration

The defaults allow 30 estimates per client address per minute:

```yaml
babdev_sylius_shipping_estimate:
    rate_limiter:
        enabled: true
        policy: 'sliding_window'
        limit: 30
        interval: '1 minute'
        cache_pool: 'cache.rate_limiter'
        lock_factory: ~
```

| Option         | Description                                                                                                            |
|----------------|------------------------------------------------------------------------------------------------------------------------|
| `enabled`      | Whether to limit at all. Defaults to whether the RateLimiter component is installed.                                   |
| `service`      | The service ID of your own rate limiter factory. When set, every other option is ignored.                              |
| `policy`       | One of `fixed_window`, `sliding_window`, `token_bucket` or `no_limit`.                                                 |
| `limit`        | Estimates allowed per client in one interval.                                                                          |
| `interval`     | The window the limit applies over, such as `1 minute`.                                                                 |
| `cache_pool`   | The cache pool holding the limiter state.                                                                              |
| `lock_factory` | A lock factory making limiter updates atomic. Off by default, as it needs the Lock component installed and configured. |

To turn it off without removing the component:

```yaml
babdev_sylius_shipping_estimate:
    rate_limiter:
        enabled: false
```

### Using Your Own Limiter

If you already define limiters in your application, point the plugin at one and it will be used as-is:

```yaml
framework:
    rate_limiter:
        shipping_estimate:
            policy: 'token_bucket'
            limit: 10
            rate: { interval: '15 seconds' }

babdev_sylius_shipping_estimate:
    rate_limiter:
        service: 'limiter.shipping_estimate'
```

## What The Customer Sees

When a client exhausts its allowance the endpoint answers `429 Too Many Requests` with `Retry-After`, `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers, and this body:

```json
{"error": true, "options": [], "reason": "shipping_estimate_rate_limited"}
```

The bundled JavaScript shows the translated `babdev_sylius_shipping_estimate.ui.shipping_estimates_requested_too_quickly` message.
