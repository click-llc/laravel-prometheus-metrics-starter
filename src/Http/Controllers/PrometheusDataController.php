<?php

declare(strict_types=1);

namespace Click\LaravelPrometheus\Http\Controllers;

use Click\LaravelPrometheus\Services\PrometheusService;
use Illuminate\Http\Response;
use Illuminate\Foundation\Application;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Routing\Controller;

class PrometheusDataController extends Controller
{
    protected PrometheusService $prometheusService;

    public function __construct(PrometheusService $prometheusService)
    {
        $this->prometheusService = $prometheusService;
    }

    public function show(): Application|Response|ResponseFactory
    {
        return $this->prometheusService->provideGeneralData();
    }
}