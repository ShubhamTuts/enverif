<?php

namespace App\Models\Concerns;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Applies the active workspace as a fail-closed global Eloquent scope.
 *
 * Administrative code that intentionally crosses tenant boundaries must opt out with
 * withoutGlobalScopes() and supply workspace_id explicitly. Normal tenant queries are
 * never allowed to become unscoped merely because request/job context was forgotten.
 */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $context = app(WorkspaceContext::class);
            $builder->where(
                $builder->getModel()->qualifyColumn('workspace_id'),
                $context->requireId(),
            );
        });

        static::creating(function ($model): void {
            $context = app(WorkspaceContext::class);

            if (!$model->workspace_id) {
                $model->workspace_id = $context->requireId();
                return;
            }

            if ($context->has() && (int) $model->workspace_id !== $context->requireId()) {
                throw new LogicException('Cannot create a tenant record for a different workspace than the active workspace context.');
            }
        });
    }
}
