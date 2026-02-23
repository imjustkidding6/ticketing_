<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DistributorController extends Controller
{
    public function index(): View
    {
        $distributors = Distributor::withCount('licenses')
            ->latest()
            ->paginate(15);

        return view('admin.distributors.index', compact('distributors'));
    }

    public function create(): View
    {
        return view('admin.distributors.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        Distributor::create($validated);

        return redirect()->route('admin.distributors.index')
            ->with('success', 'Distributor created successfully.');
    }

    public function show(Distributor $distributor): View
    {
        $distributor->load(['licenses.plan', 'licenses.tenant']);

        return view('admin.distributors.show', compact('distributor'));
    }

    public function edit(Distributor $distributor): View
    {
        return view('admin.distributors.edit', compact('distributor'));
    }

    public function update(Request $request, Distributor $distributor): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $distributor->update($validated);

        return redirect()->route('admin.distributors.index')
            ->with('success', 'Distributor updated successfully.');
    }

    public function destroy(Distributor $distributor): RedirectResponse
    {
        $distributor->delete();

        return redirect()->route('admin.distributors.index')
            ->with('success', 'Distributor deleted successfully.');
    }
}
