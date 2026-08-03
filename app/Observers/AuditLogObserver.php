<?php

namespace App\Observers;

use App\Models\Auditlogs;
use App\Models\Staffusers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogObserver
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
        // Auth::id() returns the username (getAuthIdentifierName() = 'username'),
        // so read the numeric primary key explicitly for the FK.
        $user = Auth::user();
        $userId = $user instanceof Staffusers ? $user->userId : null; // nullable; system tasks run without a session

        Auditlogs::create([
            'userId' => $userId,
            'action' => $action,
            'entityTable' => $model->getTable(),
            'entityId' => $model->getKey(),
            'oldValues' => $action === 'updated' ? json_encode($model->getOriginal()) : null,
            'newValues' => json_encode($model->getAttributes()),
            'ipAddress' => Request::ip(),
            'createdAt' => now(),
        ]);
    }
}
