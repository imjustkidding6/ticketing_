<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    /**
     * Perform a health check on the application.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'database' => false,
            'cache' => false,
        ];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['status'] = 'degraded';
        }

        try {
            Cache::store()->put('health_check', true, 10);
            $checks['cache'] = Cache::store()->get('health_check') === true;
        } catch (\Throwable) {
            $checks['status'] = 'degraded';
        }

        $statusCode = $checks['status'] === 'ok' ? 200 : 503;

        return response()->json($checks, $statusCode);
    }
}
