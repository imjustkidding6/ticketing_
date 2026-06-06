<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BugReport;
use App\Models\ChatMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Internal (is_admin) queue of product bugs reported through the AI Assistant.
 * Cross-tenant (withoutGlobalScopes) like Admin\TenantFeedbackController.
 *
 * Phase 1: "Fix" marks the bug escalated and status can be advanced manually so
 * the report→notify loop is testable. Phase 2 replaces fix() with a GitHub issue
 * + Claude Code Action, and the webhook advances the status automatically.
 */
class BugReportController extends Controller
{
    public function index(Request $request): View
    {
        $bugs = BugReport::withoutGlobalScopes()
            ->with(['tenant', 'reporter'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->severity, fn ($q) => $q->where('severity', $request->severity))
            ->when($request->search, fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$request->search}%")
                ->orWhere('description', 'like', "%{$request->search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'new' => BugReport::withoutGlobalScopes()->where('status', BugReport::STATUS_NEW)->count(),
            'escalated' => BugReport::withoutGlobalScopes()->where('status', BugReport::STATUS_ESCALATED)->count(),
            'pr_opened' => BugReport::withoutGlobalScopes()->where('status', BugReport::STATUS_PR_OPENED)->count(),
        ];

        return view('admin.bugs.index', compact('bugs', 'counts'));
    }

    public function show(int $bug): View
    {
        $bug = BugReport::withoutGlobalScopes()->with(['tenant', 'reporter', 'conversation'])->findOrFail($bug);

        // A short excerpt of the conversation the bug was reported in, for context.
        $conversationExcerpt = $bug->chat_conversation_id
            ? ChatMessage::where('chat_conversation_id', $bug->chat_conversation_id)
                ->whereIn('role', [ChatMessage::ROLE_USER, ChatMessage::ROLE_ASSISTANT])
                ->whereNotNull('content')
                ->latest('id')
                ->limit(8)
                ->get()
                ->reverse()
                ->values()
            : collect();

        return view('admin.bugs.show', compact('bug', 'conversationExcerpt'));
    }

    /**
     * Phase 1: hand the bug to the AI Programmer. For now this just marks it
     * escalated; Phase 2 creates the GitHub issue that triggers Claude Code.
     */
    public function fix(int $bug): RedirectResponse
    {
        $bug = BugReport::withoutGlobalScopes()->findOrFail($bug);

        if (! in_array($bug->status, [BugReport::STATUS_NEW, BugReport::STATUS_TRIAGED], true)) {
            return back()->with('error', 'This bug has already been sent to the AI Programmer.');
        }

        $bug->update(['status' => BugReport::STATUS_ESCALATED]);

        return back()->with('success', "Sent {$bug->reference()} to the AI Programmer.");
    }

    /**
     * Manually advance status (Phase 1 stand-in for the GitHub webhook), or reject.
     */
    public function updateStatus(Request $request, int $bug): RedirectResponse
    {
        $bug = BugReport::withoutGlobalScopes()->findOrFail($bug);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                BugReport::STATUS_PR_OPENED,
                BugReport::STATUS_MERGED,
                BugReport::STATUS_CLOSED,
                BugReport::STATUS_REJECTED,
            ])],
        ]);

        $bug->update(['status' => $validated['status']]);

        return back()->with('success', "{$bug->reference()} marked {$validated['status']}.");
    }
}
