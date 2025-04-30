<?php

declare(strict_types=1);

namespace Click\LaravelPrometheus\Http\Middlewares;

use Closure;
use InvalidArgumentException;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $duration = microtime(true) - $startTime;

        $route = $request->route();
        $endpoint = $route ? $route->uri() : 'unknown';
        $method = $request->getMethod();
        $statusCode = $response->getStatusCode();
        $statusGroup = $this->getStatusGroup($statusCode);

        try {
            $this->updateMetrics($method, $endpoint, $statusCode, $statusGroup, $duration);
        } catch (Throwable $e) {
            logger()->error('Failed to update Prometheus metrics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
        return $response;
    }

    private function getStatusGroup(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => '2xx',
            $statusCode >= 300 && $statusCode < 400 => '3xx',
            $statusCode >= 400 && $statusCode < 500 => '4xx',
            $statusCode >= 500 => '5xx',
            default => 'unknown',
        };
    }

    /**
     * @throws InvalidArgumentException
     */
    private function updateMetrics(string $method, string $endpoint, int $statusCode, string $statusGroup, float $duration): void
    {
        if (empty($method) || empty($endpoint)) {
            throw new InvalidArgumentException('Method or endpoint is empty');
        }
        $prefix = config('prometheus.prefix');
        $ttl = config('prometheus.cache_ttl'); // время жизни в секундах

        // Формируем ключи для инкрементации метрик
        $totalKey = "$prefix:requests_total:$method:$endpoint:$statusCode";
        $groupKey = "$prefix:requests_by_status_group:$method:$endpoint:$statusGroup";
        $durationKey = "$prefix:durations:$method:$endpoint";

        // Для счетчиков: если ключа нет, ставим его с 0 и TTL
        if (!Cache::has($totalKey)) {
            Cache::put($totalKey, 0, $ttl);
        }
        if (!Cache::has($groupKey)) {
            Cache::put($groupKey, 0, $ttl);
        }
        Cache::increment($totalKey);
        Cache::increment($groupKey);

        // Обновляем "хеш" для длительностей: получаем текущий массив, добавляем новое значение и сохраняем обратно.
        $durations = Cache::get($durationKey, []);
        $field = (string) microtime(true);  // используем timestamp для уникальности поля
        $durations[$field] = number_format($duration, 6, '.', '');
        // Ограничим размер массива, оставляя последние 1000 записей
        if (count($durations) > 1000) {
            $durations = array_slice($durations, -1000, 1000, true);
        }
        Cache::put($durationKey, $durations, $ttl);

        // Регистрируем ключи в реестрах, чтобы потом можно было их получить, также устанавливаем TTL
        $this->registerKey("$prefix:requests_total_keys", $totalKey, $ttl);
        $this->registerKey("$prefix:requests_by_status_group_keys", $groupKey, $ttl);
        $this->registerKey("$prefix:durations_keys", $durationKey, $ttl);
    }

    /**
     * Регистрирует ключ в заданном реестре, если его там ещё нет, и обновляет TTL реестра.
     */
    private function registerKey(string $registryKey, string $key, int $ttl): void
    {
        $keys = Cache::get($registryKey, []);
        if (!in_array($key, $keys)) {
            $keys[] = $key;
        }
        Cache::put($registryKey, $keys, $ttl);
    }
}
