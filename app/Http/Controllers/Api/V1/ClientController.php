<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $tenant = $request->attributes->get('api_tenant');

        $client = Client::firstOrCreate(
            [
                'tenant_id' => $tenant->id,
                'email' => $validated['email'],
            ],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'tier' => Client::TIER_BASIC,
                'status' => Client::STATUS_ACTIVE,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
            ],
        ], $client->wasRecentlyCreated ? 201 : 200);
    }
}
