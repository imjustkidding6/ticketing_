<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\TicketCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    public function departments(): JsonResponse
    {
        $departments = Department::active()->ordered()->get(['id', 'name', 'code', 'color']);

        return response()->json(['data' => $departments]);
    }

    public function categories(Request $request): JsonResponse
    {
        $request->validate([
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $query = TicketCategory::active()->ordered();

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        return response()->json([
            'data' => $query->get(['id', 'name', 'department_id']),
        ]);
    }
}
