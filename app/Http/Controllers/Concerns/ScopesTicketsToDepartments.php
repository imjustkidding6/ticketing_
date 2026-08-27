<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Department-level narrowing for ticket access.
 *
 * This used to live as a private method on TicketController and was applied to
 * exactly two actions, so a restricted agent who was refused the ticket page
 * could still open its edit form, change its status, or download its
 * attachments. Anything that resolves a {ticket} route parameter must call
 * authorizeTicketAccess().
 */
trait ScopesTicketsToDepartments
{
    /**
     * Narrow a ticket query to what the current user is allowed to see.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected function scopeByUserDepartments($query)
    {
        $user = Auth::user();
        $tenant = $user?->currentTenant();

        if (! $user || ! $tenant) {
            return $query;
        }

        if (in_array($user->roleInTenant($tenant), ['owner', 'admin'], true)) {
            return $query;
        }

        // 'view all tickets' lifts the department restriction for roles that hold
        // it (manager, supervisor, senior agent, viewer). Unlike the other
        // permissions this one decides scope, not access — never a 403.
        if ($this->userCan('view all tickets')) {
            return $query;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        // No departments means no departmental grant. Previously this returned the
        // unrestricted query, so the default state of a newly invited agent was
        // access to every ticket in the workspace. They keep their own work.
        return $query->where(function ($q) use ($departmentIds, $user) {
            if ($departmentIds->isNotEmpty()) {
                $q->whereIn('department_id', $departmentIds);
            }

            $q->orWhere('assigned_to', $user->id)
                ->orWhere('created_by', $user->id);
        });
    }

    /**
     * Abort with 403 unless the current user may act on this ticket.
     */
    protected function authorizeTicketAccess(Ticket $ticket): void
    {
        $allowed = $this->scopeByUserDepartments(
            Ticket::query()->whereKey($ticket->getKey())
        )->exists();

        abort_unless($allowed, 403, 'This ticket belongs to a department you do not have access to.');
    }
}
