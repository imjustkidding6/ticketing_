<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\AiChatbotService;
use App\Services\AiConversationExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AiChatbotController extends Controller
{
    public function __construct(
        private readonly AiChatbotService $chatbotService
    ) {}

    /**
     * Render the ChatGPT-style AI Assistant Chat page.
     */
    public function index(Request $request): View
    {
        return view('admin.ai.chat');
    }

    /**
     * Get paginated conversations for the current admin user (Eager loaded to prevent N+1).
     */
    public function getConversations(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversations = ChatConversation::where('user_id', $user->id)
            ->with(['messages' => fn ($q) => $q->latest('id')->limit(1)])
            ->latest('updated_at')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $conversations->items(),
            'current_page' => $conversations->currentPage(),
            'last_page' => $conversations->lastPage(),
        ]);
    }

    /**
     * Start a new conversation session.
     */
    public function startConversation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $conversation = $this->chatbotService->startConversation($user, $validated['title'] ?? null);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Get messages for a specific conversation.
     */
    public function getMessages(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeTenantAccess($request, $conversation);

        $messages = $conversation->messages()
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
            'messages' => $messages,
        ]);
    }

    /**
     * Process message submission inside a conversation.
     */
    public function sendMessage(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeTenantAccess($request, $conversation);

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        $result = $this->chatbotService->processMessage($conversation, $user, $validated['message']);

        return response()->json([
            'success' => true,
            'userMessage' => $result['userMessage'],
            'assistantMessage' => $result['assistantMessage'],
            'conversation' => $conversation->fresh(),
        ]);
    }

    /**
     * Rename a conversation title.
     */
    public function renameConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeTenantAccess($request, $conversation);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $conversation->update(['title' => $validated['title']]);

        return response()->json([
            'success' => true,
            'conversation' => $conversation,
        ]);
    }

    /**
     * Delete a conversation.
     */
    public function deleteConversation(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->authorizeTenantAccess($request, $conversation);

        $conversation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Conversation deleted successfully.',
        ]);
    }

    /**
     * Export conversation transcript using existing AiConversationExportService.
     */
    public function exportConversation(
        Request $request,
        ChatConversation $conversation,
        string $format,
        AiConversationExportService $exportService
    ): Response {
        $this->authorizeTenantAccess($request, $conversation);

        $conversation->load('messages');

        if ($format === 'json') {
            return response()->json($exportService->exportJson($conversation));
        }

        if ($format === 'csv') {
            return response($exportService->exportCsv($conversation))
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="chat_export_'.$conversation->id.'.csv"');
        }

        return response($exportService->exportHtml($conversation))
            ->header('Content-Type', 'text/html');
    }

    /**
     * Enforce tenant context authorization on conversation access.
     */
    private function authorizeTenantAccess(Request $request, ChatConversation $conversation): void
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($conversation->user_id !== $user->id && ! $user->isAdmin()) {
            abort(403, 'Unauthorized access to conversation.');
        }
    }
}
