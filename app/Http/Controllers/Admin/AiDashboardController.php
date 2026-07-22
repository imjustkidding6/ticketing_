<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KbArticle;
use App\Models\LearnedSnippet;
use App\Models\Ticket;
use App\Services\EmbeddingService;
use App\Services\OpenAiService;
use Illuminate\Contracts\View\View;

class AiDashboardController extends Controller
{
    public function index(OpenAiService $openAi, EmbeddingService $embeddingService): View
    {
        $metrics = $embeddingService->getMetrics();

        $kbTotal = KbArticle::withoutGlobalScopes()->where('is_published', true)->count();
        $kbEmbedded = KbArticle::withoutGlobalScopes()->where('is_published', true)->whereNotNull('embedded_at')->count();

        $ticketTotal = Ticket::withoutGlobalScopes()->where('status', 'closed')->count();
        $ticketEmbedded = Ticket::withoutGlobalScopes()->where('status', 'closed')->whereNotNull('solution_embedded_at')->count();

        $snippetTotal = LearnedSnippet::withoutGlobalScopes()->count();
        $snippetEmbedded = LearnedSnippet::withoutGlobalScopes()->whereNotNull('embedding')->count();

        $isConfigured = $openAi->isConfigured();

        return view('admin.ai.index', [
            'isConfigured' => $isConfigured,
            'metrics' => $metrics,
            'kbTotal' => $kbTotal,
            'kbEmbedded' => $kbEmbedded,
            'ticketTotal' => $ticketTotal,
            'ticketEmbedded' => $ticketEmbedded,
            'snippetTotal' => $snippetTotal,
            'snippetEmbedded' => $snippetEmbedded,
        ]);
    }
}
