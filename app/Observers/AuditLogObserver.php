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
     * Audit follow-up §A1: sensitive attribute keys are redacted at WRITE time
     * (not read time) so credentials never reach the audit-log storage in the
     * first place — regardless of who holds audit.view later. Covers password
     * hashes, API tokens, secrets, and remember tokens on any model.
     */
    private const SENSITIVE_KEY_PATTERN = '/password|hash|token|secret/i';

    /**
     * Redact sensitive attribute values before JSON-encoding for storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function redactSensitive(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string) $key)) {
                $attributes[$key] = '[REDACTED]';
            }
        }

        return $attributes;
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
            'oldValues' => $action === 'updated'
                ? json_encode($this->redactSensitive($model->getOriginal()))
                : null,
            'newValues' => json_encode($this->redactSensitive($model->getAttributes())),
            'ipAddress' => Request::ip(),
            'createdAt' => now(),
        ]);
    }
}
