<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    /**
     * Write an activity log entry for the given subject.
     *
     * @param  array<string, mixed>|null  $changes
     */
    public static function log(
        Model $subject,
        string $action,
        ?string $description = null,
        ?array $changes = null,
    ): ?ActivityLog {
        $tenantId = $subject->tenant_id ?? TenantScope::getCurrentTenantId();

        if (! $tenantId) {
            return null;
        }

        return ActivityLog::create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_label' => self::labelFor($subject),
            'action' => $action,
            'description' => $description,
            'changes' => $changes,
        ]);
    }

    /**
     * Build a human-readable label for a subject model.
     */
    public static function labelFor(Model $subject): string
    {
        foreach (['ticket_number', 'name', 'subject', 'title', 'email'] as $field) {
            $value = $subject->getAttribute($field);
            if (! empty($value)) {
                return (string) $value;
            }
        }

        return class_basename($subject).' #'.$subject->getKey();
    }
}
