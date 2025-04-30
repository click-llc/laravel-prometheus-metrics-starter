<?php

declare(strict_types=1);

namespace Click\LaravelPrometheus\Http\Routes;

use Click\LaravelPrometheus\Http\Controllers\PrometheusDataController;
use Illuminate\Support\Facades\Route;

Route::get(config('prometheus.route_path'), [PrometheusDataController::class, 'show']);