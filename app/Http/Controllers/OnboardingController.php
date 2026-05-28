<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OnboardingController extends Controller
{
    public function __construct(private OnboardingService $onboardingService) {}

    public function completeStep(Request $request): JsonResponse
    {
        $request->validate(['step' => ['required', 'string']]);

        $tenant = Auth::user()->currentTenant();
        $this->onboardingService->completeStep($tenant, $request->input('step'));

        return response()->json([
            'success' => true,
            'progress' => $this->onboardingService->progress($tenant),
        ]);
    }

    public function dismiss(): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        $this->onboardingService->dismiss($tenant);

        return response()->json(['success' => true]);
    }

    public function reset(): JsonResponse
    {
        $tenant = Auth::user()->currentTenant();
        $this->onboardingService->reset($tenant);

        return response()->json(['success' => true]);
    }
}
