<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;

class AiConversationExportService
{
    /**
     * Export conversation in JSON format.
     *
     * @return array<string, mixed>
     */
    public function exportJson(ChatConversation $conversation): array
    {
        return [
            'conversation_id' => $conversation->id,
            'tenant_id' => $conversation->tenant_id,
            'channel' => $conversation->channel,
            'created_at' => $conversation->created_at->toIso8601String(),
            'messages' => $conversation->messages->map(fn (ChatMessage $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at->toIso8601String(),
                'metadata' => $m->metadata,
            ])->values()->all(),
        ];
    }

    /**
     * Export conversation in CSV format text.
     */
    public function exportCsv(ChatConversation $conversation): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Message ID', 'Role', 'Content', 'Timestamp']);

        foreach ($conversation->messages as $m) {
            fputcsv($handle, [$m->id, $m->role, $m->content, $m->created_at->toIso8601String()]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Export conversation as printable HTML document for PDF generation.
     */
    public function exportHtml(ChatConversation $conversation): string
    {
        $html = "<html><head><title>AI Conversation #{$conversation->id}</title>";
        $html .= '<style>body{font-family:sans-serif;padding:20px;}.msg{margin-bottom:15px;padding:10px;border-radius:5px;}.user{background:#f0f4ff;}.assistant{background:#f9f9f9;border:1px solid #eee;}</style></head><body>';
        $html .= "<h2>AI Conversation Transcript #{$conversation->id}</h2>";
        $html .= '<p><strong>Channel:</strong> '.strtoupper($conversation->channel)." | <strong>Date:</strong> {$conversation->created_at}</p><hr>";

        foreach ($conversation->messages as $m) {
            $class = $m->role === 'user' ? 'user' : 'assistant';
            $role = strtoupper($m->role);
            $content = nl2br(e($m->content));
            $html .= "<div class='msg {$class}'><strong>{$role}:</strong><div>{$content}</div></div>";
        }

        $html .= '</body></html>';

        return $html;
    }
}
