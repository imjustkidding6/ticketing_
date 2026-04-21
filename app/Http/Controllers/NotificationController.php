<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the authenticated user.
     */
    public function recent(): JsonResponse
    {
        $tenant = \App\Models\Tenant::find(session('current_tenant_id'));

        $notifications = Auth::user()->notifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($n) use ($tenant) {
                $data = $n->data ?? [];
                $type = class_basename($n->type);

                $verb = match ($data['action'] ?? null) {
                    'assigned' => 'was assigned to you',
                    'created' => 'was created',
                    'status_changed' => 'changed status to '.($data['new_status'] ?? '-'),
                    'sla_breach_warning' => 'is breaching SLA',
                    'escalated' => 'was escalated',
                    default => str_replace('_', ' ', \Illuminate\Support\Str::snake($type)),
                };

                $number = $data['ticket_number'] ?? null;
                $subject = $data['subject'] ?? '';
                $title = $number ? "Ticket {$number} {$verb}" : $verb;

                $url = null;
                if ($tenant && ! empty($data['ticket_id'])) {
                    $url = app(\App\Services\TenantUrlHelper::class)
                        ->tenantUrl($tenant, '/tickets/'.$data['ticket_id']);
                }

                return [
                    'id' => $n->id,
                    'type' => $type,
                    'action' => $data['action'] ?? null,
                    'title' => $title,
                    'subject' => $subject,
                    'ticket_number' => $number,
                    'url' => $url,
                    'read_at' => $n->read_at,
                    'created_ago' => $n->created_at->diffForHumans(),
                ];
            });

        return response()->json($notifications);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markRead(string $id): JsonResponse
    {
        Auth::user()->notifications()->where('id', $id)->first()?->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }

    /**
     * Get the unread notification count.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications()->count(),
        ]);
    }
}
