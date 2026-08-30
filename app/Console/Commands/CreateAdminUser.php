<?php

namespace App\Console\Commands;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use App\Models\Offices;
use App\Models\Staffusers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Audit §3.3: bootstrap the first SysAdmin account on a fresh deployment.
 *
 * Fresh installs previously had no documented way in: RbacSeeder creates
 * roles/permissions only, and /admin/users requires an already-authorized
 * admin. Run once after `php artisan migrate --seed`:
 *
 *     php artisan ems:create-admin
 */
class CreateAdminUser extends Command
{
    protected $signature = 'ems:create-admin
        {--name= : Full name (first and last separated by space)}
        {--username= : Login username}
        {--email= : Email address}
        {--password= : Password (omit to be prompted securely)}';

    protected $description = 'Create the initial SysAdmin staff account (first-deployment bootstrap)';

    public function handle(): int
    {
        if (Staffusers::whereHas('roles', fn ($q) => $q->where('name', 'SysAdmin'))->exists()) {
            if (! $this->confirm('A SysAdmin account already exists. Create another?')) {
                return self::SUCCESS;
            }
        }

        $name = $this->option('name') ?: $this->ask('Full name (First Last)');
        $username = $this->option('username') ?: $this->ask('Username');
        $email = $this->option('email') ?: $this->ask('Email address');
        $password = $this->option('password') ?: $this->secret('Password (min 8 chars, mixed case, numbers, symbols)');

        [$firstName, $lastName] = array_pad(preg_split('/\s+/', trim((string) $name), 2), 2, '');

        $officeId = Offices::query()->min('officeId');
        if ($this->getOutput()->isVerbose() && Offices::exists()) {
            $officeId = $this->choice(
                'Office to assign (defaults to the first seeded office)',
                Offices::query()->orderBy('officeId')->pluck('officeName', 'officeId')->all(),
                (string) $officeId
            );
        }

        $validated = Validator::make([
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ], [
            'username' => ['required', 'string', 'max:50', 'unique:staffusers,username'],
            'email' => ['required', 'email', 'max:255', 'unique:staffusers,email'],
            'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols()],
        ])->validate();

        // Audit follow-up §A2: employeeNo is unique (uq_staff_employeeno), so
        // the second-admin path must not reuse the hardcoded bootstrap number.
        $employeeNo = 'EMP-ADMIN-001';
        $sequence = 1;
        while (Staffusers::where('employeeNo', $employeeNo)->exists()) {
            $sequence++;
            $employeeNo = 'EMP-ADMIN-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
        }

        $user = Staffusers::create([
            'employeeNo' => $employeeNo,
            'firstName' => $firstName ?: $username,
            'middleName' => '',
            'lastName' => $lastName ?: $username,
            'username' => $validated['username'],
            'email' => $validated['email'],
            'passwordHash' => Hash::make($validated['password']),
            'officeId' => $officeId,
            'contactNo' => '',
            'role' => StaffRole::Admin->value,
            'status' => StaffStatus::Active->value,
        ]);

        $user->assignRole('SysAdmin');

        $this->info("SysAdmin account created: {$user->username} <{$user->email}>");
        $this->info('Log in at /login and change this password from Profile → Update Password.');

        return self::SUCCESS;
    }
}
