<?php

declare(strict_types=1);

namespace Click\LaravelPrometheus\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PrometheusService
{
    /**
     * @return Response
     */
    public function provideGeneralData(): Response
    {
        $prefix = config('prometheus.prefix');
        $metrics = [];

        $this->processRequestsTotal($prefix, $metrics);
        $this->processRequestsByStatusGroup($prefix, $metrics);
        $this->processDurations($prefix, $metrics);

        return (new Response(implode("\n", $metrics), 200))
            ->header('Content-Type', 'text/plain');
    }

    /**
     * Получает все ключи для общего количества запросов из реестра и формирует метрики.
     *
     * Ключи имеют формат:
     *  bnpl_loan_manager_prometheus_metrics:requests_total:{method}:{endpoint}:{status}
     */
    private function processRequestsTotal(string $prefix, array &$metrics): void
    {
        $registryKey = "$prefix:requests_total_keys";
        $keys = Cache::get($registryKey, []);

        foreach ($keys as $key) {
            // Ожидается, что ключ разделён двоеточиями, например:
            // atto_face_pay_prometheus_metrics:requests_total:GET:api/bnpl/general/v1/payment/status:200
            $parts = explode(':', $key);
            if (count($parts) >= 5) {
                $method = $parts[2];
                $endpoint = $parts[3];
                $status = $parts[4];
                Log::warning("$key");
                $value = Cache::get($key, 0);
                $metrics[] = "app_http_requests_total{method=\"$method\", endpoint=\"$endpoint\", status=\"$status\"} $value";
            } else {
                Log::warning("Invalid requests_total key format: $key");
            }
        }
    }

    /**
     * Получает все ключи для групп запросов по статусу из реестра и формирует метрики.
     *
     * Формат ключа:
     *  atto_face_pay_prometheus_metrics:requests_by_status_group:{method}:{endpoint}:{group}
     */
    private function processRequestsByStatusGroup(string $prefix, array &$metrics): void
    {
        $registryKey = "$prefix:requests_by_status_group_keys";
        $keys = Cache::get($registryKey, []);

        foreach ($keys as $key) {
            $parts = explode(':', $key);
            if (count($parts) >= 5) {
                $method = $parts[2];
                $endpoint = $parts[3];
                $group = $parts[4];
                $value = Cache::get($key, 0);
                Log::warning("$key");
                $metrics[] = "app_http_requests_by_status_group_total{method=\"$method\", endpoint=\"$endpoint\", status_group=\"$group\"} $value";
            } else {
                Log::warning("Invalid requests_by_status_group key format: $key");
            }
        }
    }

    /**
     * Получает все ключи для длительностей запросов из реестра и формирует гистограмму.
     *
     * Формат ключа:
     *  atto_face_pay_prometheus_metrics:durations:{method}:{endpoint}
     *
     * В этом ключе хранится ассоциативный массив, где поле — уникальное значение (например, timestamp),
     * а значение — duration (строка, содержащая число).
     */
    private function processDurations(string $prefix, array &$metrics): void
    {
        $registryKey = "$prefix:durations_keys";
        $keys = Cache::get($registryKey, []);
        if (empty($keys)) {
            Log::warning("No duration keys found in registry");
            return;
        }

        $buckets = config('prometheus.buckets', []);

        foreach ($keys as $key) {
            $parts = explode(':', $key);
            if (count($parts) < 4) {
                Log::warning("Invalid durations key format: $key");
                continue;
            }
            $method = $parts[2];
            $endpoint = $parts[3];

            // Извлекаем ассоциативный массив (хеш) и берем только значения
            $hashData = Cache::get($key, []);
            $durations = array_values($hashData);

            $histogramCounts = array_fill_keys(array_map('strval', $buckets), 0);
            $sum = 0;
            $count = 0;
            foreach ($durations as $raw) {
                if (!is_numeric($raw)) {
                    Log::warning('Non-numeric duration found', ['value' => $raw]);
                    continue;
                }
                $duration = (float)$raw;
                $sum += $duration;
                $count++;
                foreach ($buckets as $bucket) {
                    if ($duration <= $bucket) {
                        $histogramCounts[(string)$bucket]++;
                        break;
                    }
                }
            }
            foreach ($histogramCounts as $le => $bucketCount) {
                $metrics[] = "app_http_request_duration_seconds_bucket{method=\"$method\", endpoint=\"$endpoint\", le=\"$le\"} $bucketCount";
            }
            $metrics[] = "app_http_request_duration_seconds_sum{method=\"$method\", endpoint=\"$endpoint\"} $sum";
            $metrics[] = "app_http_request_duration_seconds_count{method=\"$method\", endpoint=\"$endpoint\"} $count";
        }
    }
}