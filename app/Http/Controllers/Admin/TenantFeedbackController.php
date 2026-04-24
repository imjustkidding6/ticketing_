<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TenantFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TenantFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $feedbacks = TenantFeedback::withoutGlobalScopes()
            ->with(['tenant', 'user'])
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('body', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'new'      => TenantFeedback::withoutGlobalScopes()->where('status', 'new')->count(),
            'read'     => TenantFeedback::withoutGlobalScopes()->where('status', 'read')->count(),
            'resolved' => TenantFeedback::withoutGlobalScopes()->where('status', 'resolved')->count(),
        ];

        return view('admin.feedback.index', compact('feedbacks', 'counts'));
    }

    public function show(int $feedback): View
    {
        $feedback = TenantFeedback::withoutGlobalScopes()->with(['tenant', 'user'])->findOrFail($feedback);

        if ($feedback->status === 'new') {
            $feedback->update(['status' => 'read']);
        }

        return view('admin.feedback.show', compact('feedback'));
    }

    public function update(Request $request, int $feedback): RedirectResponse
    {
        $feedback = TenantFeedback::withoutGlobalScopes()->findOrFail($feedback);

        $validated = $request->validate([
            'status' => ['required', Rule::in(TenantFeedback::STATUSES)],
        ]);

        $feedback->update($validated);

        return back()->with('success', 'Status updated.');
    }

    public function destroy(int $feedback): RedirectResponse
    {
        TenantFeedback::withoutGlobalScopes()->findOrFail($feedback)->delete();

        return redirect()->route('admin.feedback.index')->with('success', 'Feedback deleted.');
    }
}
