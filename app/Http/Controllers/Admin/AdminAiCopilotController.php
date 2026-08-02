<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAiCopilotController extends Controller
{
    public function __construct(
        private readonly AdminAiCopilotService $copilotService
    ) {}

    /**
     * Process AI Admin Copilot chat query.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $response = $this->copilotService->processAdminQuery($validated['message'], $user);

        return response()->json([
            'success' => true,
            'message' => $response['message'],
            'action' => $response['action'],
            'status' => $response['status'],
        ]);
    }
}
