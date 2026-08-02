<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\TenantUrlHelper;
use App\Support\TenantTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * Get recent notifications for the authenticated user.
     */
    public function recent(): JsonResponse
    {
        $tenant = Tenant::find(session('current_tenant_id'));

        $notifications = $this->scopedNotifications()
            ->latest()
            ->take(20)
            ->get()
            ->map(function ($n) use ($tenant) {
                $data = $n->data ?? [];
                $type = class_basename($n->type);

                // System announcements use a different shape — render them directly.
                if (($data['action'] ?? null) === 'system_announcement') {
                    $publishedAt = $data['published_at'] ?? $n->created_at;

                    return [
                        'id' => $n->id,
                        'type' => $type,
                        'action' => 'system_announcement',
                        'severity' => $data['severity'] ?? 'info',
                        'title' => $data['title'] ?? 'System announcement',
                        'subject' => $data['body'] ?? '',
                        'ticket_number' => null,
                        'url' => null,
                        'read_at' => $n->read_at,
                        'created_ago' => $n->created_at->setTimezone(TenantTime::timezone())->diffForHumans(),
                        'published_at_local' => TenantTime::format($publishedAt, 'M d, Y g:i A'),
                    ];
                }

                $verb = match ($data['action'] ?? null) {
                    'assigned' => 'was assigned to you',
                    'created' => 'was created',
                    'status_changed' => 'changed status to '.($data['new_status'] ?? '-'),
                    'sla_breach_warning' => 'is breaching SLA',
                    'escalated' => 'was escalated',
                    default => str_replace('_', ' ', Str::snake($type)),
                };

                $number = $data['ticket_number'] ?? null;
                $subject = $data['subject'] ?? '';
                $title = $number ? "Ticket {$number} {$verb}" : $verb;

                $url = null;
                if ($tenant && ! empty($data['ticket_id'])) {
                    $url = app(TenantUrlHelper::class)
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
                    'created_ago' => $n->created_at->setTimezone(TenantTime::timezone())->diffForHumans(),
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
        $this->scopedNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Get the unread notification count.
     */
    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => $this->scopedNotifications()
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * Build the base notification query, filtered to the user's current departments.
     *
     * Owners and admins see all notifications. Everyone else only sees notifications
     * tagged with a department they currently belong to (and untagged ones, like
     * system announcements). Notifications carry the originating ticket's
     * department_id in their data payload — see TicketAssignedNotification etc.
     *
     * @return Builder<DatabaseNotification>
     */
    private function scopedNotifications(): Builder
    {
        $user = Auth::user();
        $query = DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id);

        $tenant = $user->currentTenant();
        if (! $tenant) {
            return $query;
        }

        $role = $user->roleInTenant($tenant);
        if (in_array($role, ['owner', 'admin'])) {
            return $query;
        }

        $departmentIds = $user->departments()->pluck('departments.id')->all();

        return $query->where(function (Builder $q) use ($departmentIds) {
            // Notifications with no department tag (system announcements, license expiry, etc.)
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.department_id')) IS NULL");

            if (! empty($departmentIds)) {
                $q->orWhereIn(
                    \DB::raw("JSON_UNQUOTE(JSON_EXTRACT(data, '$.department_id'))"),
                    array_map('strval', $departmentIds)
                );
            }
        });
    }
}
