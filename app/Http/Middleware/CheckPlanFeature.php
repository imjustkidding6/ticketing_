<?php

namespace App\Http\Middleware;

use App\Services\PlanService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function __construct(
        protected PlanService $planService,
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        foreach ($features as $feature) {
            if (! $this->planService->currentTenantHasFeature($feature)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'This feature is not available on your current plan.',
                        'feature' => $feature,
                    ], 403);
                }

                abort(403, 'This feature is not available on your current plan. Please upgrade to access this functionality.');
            }
        }

        return $next($request);
    }
}
