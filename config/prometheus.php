<?php

declare(strict_types=1);

return [
    'route_path' => '/metrics',
    'cache_ttl' => 3600, // по умолчанию 1 час
    'prefix' => env('PREFIX_PROMETHEUS_METRICS') ?? 'default_prometheus_metrics',
    'buckets' => [0.01, 0.05, 0.1, 0.3, 0.5, 1]
];
