<?php

declare(strict_types=1);

namespace Click\LaravelPrometheus\Providers;

use Carbon\Laravel\ServiceProvider;
use Click\LaravelPrometheus\Services\PrometheusService;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/prometheus.php', 'prometheus');

        $this->app->singleton(PrometheusService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/prometheus.php' => config_path('prometheus.php'),
        ], 'prometheus-config');

        $this->loadRoutesFrom(__DIR__ . '/../../routes/prometheus.php');
    }
}