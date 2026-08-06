<?php

namespace App\Models\Concerns;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies the active workspace as a global Eloquent scope.
 *
 * Route binding intentionally delegates to Laravel's model implementation. This is
 * important for UUID models: Laravel's HasUuids/HasUniqueStringIds concern performs
 * UUID validation in resolveRouteBindingQuery(), while this global scope supplies the
 * workspace boundary. Defining resolveRouteBindingQuery() in this trait would collide
 * with HasUuids and can fatal at class composition time.
 */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $context = app(WorkspaceContext::class);
            if ($context->has()) {
                $builder->where(
                    $builder->getModel()->qualifyColumn('workspace_id'),
                    $context->id(),
                );
            }
        });

        static::creating(function ($model): void {
            $context = app(WorkspaceContext::class);
            if (!$model->workspace_id && $context->has()) {
                $model->workspace_id = $context->id();
            }
        });
    }
}
