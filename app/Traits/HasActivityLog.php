<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

trait HasActivityLog
{
    public static function bootHasActivityLog(): void
    {
        static::created(function (Model $model) {
            static::logActivity($model, 'created');
        });

        static::updated(function (Model $model) {
            $changes = [];
            foreach ($model->getDirty() as $field => $newValue) {
                if (in_array($field, ['updated_at', 'created_at'], true)) {
                    continue;
                }

                $changes[$field] = [
                    'old' => $model->getOriginal($field),
                    'new' => $newValue,
                ];
            }

            if (!empty($changes)) {
                static::logActivity($model, 'updated', $changes);
            }
        });

        static::deleted(function (Model $model) {
            static::logActivity($model, 'deleted');
        });
    }

    protected static function logActivity(Model $model, string $action, ?array $changes = null): void
    {
        $user = auth()->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'entity_type' => $model->getTable(),
            'entity_id' => $model->getKey(),
            'entity_name' => $model->name ?? $model->title ?? null,
            'changes' => $changes,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
