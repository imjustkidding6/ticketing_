<?php

namespace App\Models\Traits;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            if (self::shouldSkipActivityLog($model)) {
                return;
            }

            ActivityLogger::log($model, 'created', class_basename($model).' created.');
        });

        static::updated(function (Model $model) {
            if (self::shouldSkipActivityLog($model)) {
                return;
            }

            $changes = self::buildChanges($model);

            if (empty($changes)) {
                return;
            }

            ActivityLogger::log($model, 'updated', class_basename($model).' updated.', $changes);
        });

        static::deleted(function (Model $model) {
            if (self::shouldSkipActivityLog($model)) {
                return;
            }

            $isForce = method_exists($model, 'isForceDeleting') && $model->isForceDeleting();
            $action = $isForce ? 'force_deleted' : 'deleted';
            ActivityLogger::log($model, $action, class_basename($model).' '.str_replace('_', ' ', $action).'.');
        });

        // `restored` is only dispatched by SoftDeletes; registering is safe otherwise (no-op).
        static::registerModelEvent('restored', function (Model $model) {
            if (self::shouldSkipActivityLog($model)) {
                return;
            }

            ActivityLogger::log($model, 'restored', class_basename($model).' restored.');
        });
    }

    private static function shouldSkipActivityLog(Model $model): bool
    {
        return property_exists($model, 'activityLogDisabled') && $model->activityLogDisabled === true;
    }

    /**
     * Diff the dirty attributes against the original values, ignoring noise.
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function buildChanges(Model $model): array
    {
        $ignored = property_exists($model, 'activityLogIgnore')
            ? (array) $model->activityLogIgnore
            : [];

        $ignored = array_merge(
            ['updated_at', 'created_at', 'remember_token', 'password'],
            $ignored,
        );

        $changes = [];

        foreach ($model->getChanges() as $field => $newValue) {
            if (in_array($field, $ignored, true)) {
                continue;
            }

            $changes[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        return $changes;
    }
}
