# Laravel Prometheus Metrics Starter

A simple and extensible Laravel package for collecting and exposing metrics in the [Prometheus](https://prometheus.io/) format. Ideal as a starting point for building custom monitoring solutions.

## 🚀 Features

- Ready-to-use `/metrics` endpoint for Prometheus scraping
- Flexible and centralized configuration
- Extensible architecture with support for custom collectors
- Histogram buckets for operation duration tracking
- Cache support to reduce overhead

## 📦 Installation

```bash
composer require your-vendor/laravel-prometheus-metrics-starter
```

## ⚙️ Publishing Configuration
Publish the configuration file using Artisan:

```bash
php artisan vendor:publish --tag=prometheus-config
```

| Option         | Description                                                                 | Default                |
|----------------|-----------------------------------------------------------------------------|------------------------|
| `route_path`   | The path to expose metrics                                                  | `/metrics`             |
| `cache_ttl`    | Cache duration in seconds (uses Laravel Cache)                              | `3600` (1 hour)        |
| `prefix`       | Metric name prefix (can be set via `PREFIX_PROMETHEUS_METRICS` env var)     | `''`                   |
| `buckets`      | Histogram buckets for measuring durations                                   | `[0.01, 0.05, 0.1, 0.3, 0.5, 1]` |

## 🌐 Routes

After installation, the following route becomes available automatically:

```bash
GET /metrics
```

This route exposes metrics in Prometheus text exposition format.

## 🧩 Middleware Integration

You can attach Prometheus metric collection to specific routes via middleware.

### Option 1: Using an alias

Register the alias in your `config/app.php`:

```bash
'aliases' => [
    'prometheus' => \YourVendor\Prometheus\Middleware\PrometheusMiddleware::class,
    // ...
],
```
Then apply the middleware by alias:
```bash
use Illuminate\Support\Facades\Route;

Route::get('/health', fn() => ['ok' => true])
    ->middleware('prometheus');

```

### Option 2: Using the class directly

```bash
use YourVendor\Prometheus\Middleware\PrometheusMiddleware;

Route::get('/health', fn() => ['ok' => true])
->middleware([PrometheusMiddleware::class]);
```

