<?php

namespace App\Http\Controllers;

use App\Http\Requests\Concerns\ScopesRulesToTenant;
use App\Models\Department;
use App\Models\TicketCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    use ScopesRulesToTenant;

    /**
     * Display a listing of categories.
     */
    public function index(): View
    {
        $this->checkPermission('view categories');

        $categories = TicketCategory::query()
            ->with('department')
            ->ordered()
            ->paginate(15);

        return view('categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create(): View
    {
        $this->checkPermission('create categories');

        $departments = Department::query()->active()->ordered()->get();

        return view('categories.create', compact('departments'));
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->checkPermission('create categories');

        $validated = $request->validate([
            'department_id' => ['required', $this->tenantExists('departments')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:7'],
            'is_active' => ['boolean'],
            // Decides whether tickets in this category are asked for the technical half of
            // the declared detail set (App\Support\TicketTechnicalFields). Rides on
            // `create categories` / `update categories`, already enforced above.
            'is_technical' => ['boolean'],
            // And the impact half (who is affected, is there a workaround). Defaults TRUE
            // in the schema, so an absent key on an older client leaves the stored value
            // alone on update and keeps the group asked on create — the form posts a hidden
            // `0` companion, which is what makes unticking it reach here at all.
            'asks_impact' => ['boolean'],
        ]);

        TicketCategory::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(TicketCategory $category): View
    {
        $this->checkPermission('update categories');

        $departments = Department::query()->active()->ordered()->get();

        return view('categories.edit', compact('category', 'departments'));
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, TicketCategory $category): RedirectResponse
    {
        $this->checkPermission('update categories');

        $validated = $request->validate([
            'department_id' => ['required', $this->tenantExists('departments')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:7'],
            'is_active' => ['boolean'],
            // Decides whether tickets in this category are asked for the technical half of
            // the declared detail set (App\Support\TicketTechnicalFields). Rides on
            // `create categories` / `update categories`, already enforced above.
            'is_technical' => ['boolean'],
            // And the impact half (who is affected, is there a workaround). Defaults TRUE
            // in the schema, so an absent key on an older client leaves the stored value
            // alone on update and keeps the group asked on create — the form posts a hidden
            // `0` companion, which is what makes unticking it reach here at all.
            'asks_impact' => ['boolean'],
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category.
     */
    public function destroy(TicketCategory $category): RedirectResponse
    {
        $this->checkPermission('delete categories');

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
