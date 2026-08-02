<?php

namespace App\Observers;

use App\Models\Auditlogs;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditObserver
{
    /**
     * Handle the Model "created" event.
     */
    public function created(Model $model): void
    {
        $this->log('created', $model);
    }

    /**
     * Handle the Model "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->log('updated', $model);
    }

    /**
     * Handle the Model "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->log('deleted', $model);
    }

    /**
     * Log the audit entry.
     */
    private function log(string $action, Model $model): void
    {
        $userId = Auth::id() ?? 1; // Default to system user if no auth

        Auditlogs::create([
            'userId' => $userId,
            'action' => $action,
            'entityTable' => $model->getTable(),
            'entityId' => $model->getKey(),
            'oldValues' => $action === 'updated' ? json_encode($model->getOriginal()) : null,
            'newValues' => json_encode($model->getAttributes()),
            'ipAddress' => request()->ip(),
            'createdAt' => now(),
        ]);
    }
}