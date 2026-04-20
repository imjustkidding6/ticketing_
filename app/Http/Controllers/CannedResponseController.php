<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCannedResponseRequest;
use App\Http\Requests\UpdateCannedResponseRequest;
use App\Models\CannedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CannedResponseController extends Controller
{
    public function index(Request $request): View
    {
        $categories = CannedResponse::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        $responses = CannedResponse::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn ($q, $cat) => $q->inCategory($cat))
            ->ordered()
            ->paginate(20)
            ->withQueryString();

        return view('canned-responses.index', compact('responses', 'categories'));
    }

    public function create(): View
    {
        $categories = CannedResponse::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('canned-responses.create', compact('categories'));
    }

    public function store(StoreCannedResponseRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['created_by'] = Auth::id();

        CannedResponse::create($validated);

        return redirect()->route('canned-responses.index')
            ->with('success', 'Canned response created successfully.');
    }

    public function edit(CannedResponse $cannedResponse): View
    {
        $categories = CannedResponse::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('canned-responses.edit', compact('cannedResponse', 'categories'));
    }

    public function update(UpdateCannedResponseRequest $request, CannedResponse $cannedResponse): RedirectResponse
    {
        $cannedResponse->update($request->validated());

        return redirect()->route('canned-responses.index')
            ->with('success', 'Canned response updated successfully.');
    }

    public function destroy(CannedResponse $cannedResponse): RedirectResponse
    {
        $cannedResponse->delete();

        return redirect()->route('canned-responses.index')
            ->with('success', 'Canned response deleted successfully.');
    }

    public function list(Request $request): JsonResponse
    {
        $responses = CannedResponse::query()
            ->when($request->category, fn ($q, $cat) => $q->inCategory($cat))
            ->ordered()
            ->get(['id', 'name', 'category', 'content', 'shortcut']);

        return response()->json($responses);
    }
}
