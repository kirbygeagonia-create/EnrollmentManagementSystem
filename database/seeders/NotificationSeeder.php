<?php

namespace Database\Seeders;

use App\Models\Notifications;
use App\Models\Staffusers;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Seed demo staff notifications so the bell shows data.
     * Idempotent: only inserts when the staff user has no notifications.
     */
    public function run(): void
    {
        $staff = Staffusers::where('role', 'admin')->get();

        if ($staff->isEmpty() || Notifications::where('notifiable_type', Staffusers::class)->exists()) {
            return;
        }

        foreach ($staff as $user) {
            Notifications::create([
                'type' => 'system',
                'notifiable_type' => Staffusers::class,
                'notifiable_id' => $user->userId,
                'data' => [
                    'message' => 'Welcome to the SEAIT Enrollment Management System. You can manage enrollment workflows from the sidebar.',
                    'signedBy' => 'System',
                ],
            ]);

            Notifications::create([
                'type' => 'workflow_step_signed',
                'notifiable_type' => Staffusers::class,
                'notifiable_id' => $user->userId,
                'data' => [
                    'message' => 'A student has completed the Department Evaluation step and is now waiting for Assessment.',
                    'enrollmentId' => null,
                    'stepLabel' => 'Department Evaluation',
                    'signedBy' => 'Workflow Service',
                ],
            ]);
        }
    }
}
