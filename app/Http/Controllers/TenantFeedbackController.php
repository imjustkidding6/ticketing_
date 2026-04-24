<?php

namespace App\Http\Controllers;

use App\Models\TenantFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantFeedbackController extends Controller
{
    public function create(): View
    {
        return view('feedback.create', [
            'types' => TenantFeedback::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'string', 'in:' . implode(',', TenantFeedback::TYPES)],
            'subject' => ['nullable', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:3000'],
        ]);

        TenantFeedback::create([
            'tenant_id' => session('current_tenant_id'),
            'user_id'   => auth()->id(),
            'type'      => $validated['type'],
            'subject'   => $validated['subject'] ?? null,
            'body'      => $validated['body'],
        ]);

        return back()->with('success', 'Thank you for your feedback!');
    }
}
