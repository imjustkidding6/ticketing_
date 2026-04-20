<?php

namespace App\Http\Controllers;

use App\Models\KbCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KbCategoryController extends Controller
{
    public function index(): View
    {
        $categories = KbCategory::query()
            ->withCount('articles')
            ->ordered()
            ->paginate(15);

        return view('knowledge-base.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('knowledge-base.categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        KbCategory::create($validated);

        return redirect()->route('knowledge-base.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(KbCategory $category): View
    {
        return view('knowledge-base.categories.edit', compact('category'));
    }

    public function update(Request $request, KbCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:50'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $category->update($validated);

        return redirect()->route('knowledge-base.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(KbCategory $category): RedirectResponse
    {
        if ($category->articles()->exists()) {
            return redirect()->route('knowledge-base.categories.index')
                ->with('error', 'Cannot delete category with existing articles.');
        }

        $category->delete();

        return redirect()->route('knowledge-base.categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
