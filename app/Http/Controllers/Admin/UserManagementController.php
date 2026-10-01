<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\Academicunits;
use App\Models\Auditlogs;
use App\Models\Offices;
use App\Models\Permissions;
use App\Models\Roles;
use App\Models\Settings;
use App\Models\Staffusers;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

class UserManagementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display staff user management.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Staffusers::class);

        $query = Staffusers::with(['office', 'unit', 'roles'])
            ->when($request->officeId, fn ($q, $id) => $q->where('officeId', $id))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->search, fn ($q, $search) => $q->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('employeeNo', $search))
            ->orderByDesc('userId');

        $users = $query->paginate(20)->withQueryString();
        $offices = Offices::all(['officeId', 'officeName']);
        $units = Academicunits::all(['unitId', 'unitName']);
        $roles = Roles::with('permissions')->get();
        $permissions = Permissions::all(['id', 'name', 'module']);

        return Inertia::render('Admin/UserManagement/Index', [
            'users' => $users,
            'offices' => $offices,
            'units' => $units,
            'roles' => $roles,
            'filters' => $request->only(['officeId', 'status', 'search']),
            'staffRoles' => collect(StaffRole::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
            'staffStatuses' => collect(StaffStatus::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->value])->values(),
        ]);
    }

    /**
     * Create new staff user.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Staffusers::class);

        $validated = $request->validate([
            'officeId' => 'nullable|exists:offices,officeId',
            'unitId' => 'nullable|exists:academicunits,unitId',
            'employeeNo' => 'required|string|max:50|unique:staffusers,employeeNo',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:staffusers,username',
            'email' => 'required|email|max:255|unique:staffusers,email',
            // Audit (low-prio): enforce a real password policy, not just length.
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => 'required|in:staff,officeHead,dean,programHead,admin,instructor',
            'contactNo' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            // Not a staffusers column: it only reaches syncRoles() below, and an id
            // that no longer resolves throws RoleDoesNotExist in the middle of a save.
            'roleIds' => 'nullable|array',
            'roleIds.*' => 'exists:roles,id',
        ]);

        // Enforce organizational constraints: Deans & Program Heads belong to academic units, not admin offices
        if (in_array($validated['role'], ['dean', 'programHead'])) {
            $validated['officeId'] = null;
            if (empty($validated['unitId'])) {
                return back()->withErrors(['unitId' => 'An Academic College/Unit is required for Deans and Program Heads.']);
            }
        } elseif ($validated['role'] === 'instructor') {
            if (empty($validated['unitId'])) {
                return back()->withErrors(['unitId' => 'An Academic College/Unit is required for Instructors.']);
            }
        } elseif ($validated['role'] === 'officeHead') {
            if (empty($validated['officeId'])) {
                return back()->withErrors(['officeId' => 'An Administrative Office is required for Office Heads.']);
            }
        }

        $user = Staffusers::create([
            'officeId' => $validated['officeId'] ?? null,
            'unitId' => $validated['unitId'] ?? null,
            'employeeNo' => $validated['employeeNo'],
            'firstName' => $validated['firstName'],
            'middleName' => $validated['middleName'] ?? null,
            'lastName' => $validated['lastName'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'passwordHash' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'contactNo' => $validated['contactNo'] ?? null,
            'status' => $validated['status'],
        ]);

        // Assign roles if provided. array_key_exists() rather than filled(): the
        // screen's role matrix posts an empty array when every box is unticked, and
        // clearing an account's roles has to be as reachable as granting them.
        if (array_key_exists('roleIds', $validated)) {
            $user->syncRoles($validated['roleIds']);
        }

        return back()->with('success', 'Staff user created.');
    }

    /**
     * Update staff user.
     */
    public function update(Request $request, Staffusers $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'officeId' => 'nullable|exists:offices,officeId',
            'unitId' => 'nullable|exists:academicunits,unitId',
            'employeeNo' => 'required|string|max:50|unique:staffusers,employeeNo,'.$user->userId.',userId',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:staffusers,username,'.$user->userId.',userId',
            'email' => 'required|email|max:255|unique:staffusers,email,'.$user->userId.',userId',
            'role' => 'required|in:staff,officeHead,dean,programHead,admin,instructor',
            'contactNo' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
            'roleIds' => 'nullable|array',
            'roleIds.*' => 'exists:roles,id',
        ]);

        // Enforce organizational constraints: Deans & Program Heads belong to academic units, not admin offices
        if (in_array($validated['role'], ['dean', 'programHead'])) {
            $validated['officeId'] = null;
            if (empty($validated['unitId'])) {
                return back()->withErrors(['unitId' => 'An Academic College/Unit is required for Deans and Program Heads.']);
            }
        } elseif ($validated['role'] === 'instructor') {
            if (empty($validated['unitId'])) {
                return back()->withErrors(['unitId' => 'An Academic College/Unit is required for Instructors.']);
            }
        } elseif ($validated['role'] === 'officeHead') {
            if (empty($validated['officeId'])) {
                return back()->withErrors(['officeId' => 'An Administrative Office is required for Office Heads.']);
            }
        }

        // roleIds is not a staffusers column; it only feeds syncRoles() below.
        $user->update(collect($validated)->except('roleIds')->all());

        // Sync roles with audit logging — see store() for why the matrix's empty
        // array is honoured instead of ignored.
        if (array_key_exists('roleIds', $validated)) {
            $oldRoles = $user->roles()->pluck('name')->toArray();
            $user->syncRoles($validated['roleIds']);
            $newRoles = $user->fresh()->roles()->pluck('name')->toArray();

            if ($oldRoles != $newRoles) {
                $currentUser = Auth::user();
                $currentUserId = $currentUser instanceof Staffusers ? $currentUser->userId : null;

                Auditlogs::create([
                    'userId' => $currentUserId,
                    'action' => 'assigned',
                    'entityTable' => 'staffusers',
                    'entityId' => $user->userId,
                    'oldValues' => ['roles' => $oldRoles],
                    'newValues' => ['roles' => $newRoles],
                    'ipAddress' => $request->ip(),
                ]);
            }
        }

        return back()->with('success', 'Staff user updated.');
    }

    /**
     * Delete staff user.
     * Prevents database constraint crashes by deactivating staff members who have
     * historical activity records (evaluations, approvals, payments, etc.).
     */
    public function destroy(Staffusers $user): RedirectResponse
    {
        // Answered before the policy so the desk reads an in-page message: the
        // UserManagementPolicy denies deleting yourself, and authorize() would
        // have rendered a bare 403 for a button the screen still shows.
        if ($user->userId === Auth::user()->userId) {
            return back()->withErrors(['user' => 'Cannot delete yourself.']);
        }

        $this->authorize('delete', $user);

        // Check if user has historical activity across foreign key relationships
        $hasHistoricalRecords = $user->admissions()->exists()
            || $user->auditlogs()->exists()
            || $user->clearanceapprovals()->exists()
            || $user->clinicrecords()->exists()
            || $user->documentprintlog()->exists()
            || $user->documents()->exists()
            || $user->evaluatedEnrollments()->exists()
            || $user->processedEnrollments()->exists()
            || $user->enrollmentstatushistory()->exists()
            || $user->payments()->exists()
            || $user->schedules()->exists()
            || $user->studentclearances()->exists()
            || $user->validatedIdrequests()->exists()
            || $user->studentscholarships()->exists()
            || $user->workflowsteps()->exists();

        if ($hasHistoricalRecords) {
            $user->update(['status' => StaffStatus::Inactive]);

            return back()->with('success', 'Staff member has historical activity records and was deactivated instead of permanently deleted to preserve audit trails.');
        }

        try {
            $user->delete();
        } catch (\Throwable) {
            $user->update(['status' => StaffStatus::Inactive]);

            return back()->with('success', 'Staff member has associated institutional records and was deactivated instead of permanently deleted to preserve audit trails.');
        }

        return back()->with('success', 'Staff user deleted.');
    }

    /**
     * Assign roles to user with audit trail logging.
     */
    public function assignRoles(Request $request, Staffusers $user): RedirectResponse
    {
        // See destroy(): the policy refuses this for yourself, and the screen still
        // offers the button on your own row.
        if ($user->userId === Auth::user()->userId) {
            return back()->withErrors(['user' => 'Cannot assign roles to yourself.']);
        }

        $this->authorize('assignRoles', $user);

        $request->validate([
            'roleIds' => 'required|array',
            'roleIds.*' => 'exists:roles,id',
        ]);

        $oldRoles = $user->roles()->pluck('name')->toArray();

        $user->syncRoles($request->roleIds);

        $newRoles = $user->fresh()->roles()->pluck('name')->toArray();

        $currentUser = Auth::user();
        $currentUserId = $currentUser instanceof Staffusers ? $currentUser->userId : null;

        Auditlogs::create([
            'userId' => $currentUserId,
            'action' => 'assigned',
            'entityTable' => 'staffusers',
            'entityId' => $user->userId,
            'oldValues' => ['roles' => $oldRoles],
            'newValues' => ['roles' => $newRoles],
            'ipAddress' => $request->ip(),
        ]);

        return back()->with('success', 'Roles assigned.');
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(Staffusers $user): RedirectResponse
    {
        // Same reason as destroy(): the policy refuses toggling yourself, which
        // would otherwise reach the desk as a 403 page instead of a message.
        if ($user->userId === Auth::user()->userId) {
            return back()->withErrors(['user' => 'Cannot change your own status.']);
        }

        $this->authorize('toggleStatus', $user);

        $user->update([
            'status' => $user->status === StaffStatus::Active ? StaffStatus::Inactive : StaffStatus::Active,
        ]);

        return back()->with('success', 'User status updated.');
    }

    // ============ ROLES ============
    public function roles(Request $request): Response
    {
        $this->authorize('manageRoles', Roles::class);

        $roles = Roles::with('permissions')->orderByDesc('id')->paginate(20);
        $permissions = Permissions::all(['id', 'name', 'module']);

        return Inertia::render('Admin/UserManagement/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name',
            'description' => 'nullable|string',
            // A permission id that no longer resolves throws PermissionDoesNotExist
            // halfway through the save, after the role row is already committed.
            'permissionIds' => 'nullable|array',
            'permissionIds.*' => 'exists:permissions,id',
        ]);

        $role = Roles::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'guard_name' => config('auth.defaults.guard'),
        ]);

        $role->syncPermissions($validated['permissionIds'] ?? []);

        $this->flushPermissionCache();

        return back()->with('success', 'Role created.');
    }

    public function updateRole(Request $request, Roles $role): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:roles,name,'.$role->id.',id',
            'description' => 'nullable|string',
            'permissionIds' => 'nullable|array',
            'permissionIds.*' => 'exists:permissions,id',
        ]);

        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        // The matrix always posts its full selection, so an absent key means the
        // caller is not editing permissions at all and nothing may be revoked.
        if (array_key_exists('permissionIds', $validated)) {
            $role->syncPermissions($validated['permissionIds']);
        }

        $this->flushPermissionCache();

        return back()->with('success', 'Role updated.');
    }

    public function destroyRole(Roles $role): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);

        // staff_roles cascades on role delete, so dropping a seated role quietly
        // strips access from every account that carries it — and dropping the last
        // SysAdmin locks every administrator out with artisan as the only way back.
        $seats = $role->staff_roles()->count();

        if ($seats > 0) {
            return back()->with('error', "Cannot delete: {$seats} staff account(s) still hold the {$role->name} role. Move those accounts to another role first.");
        }

        $role->delete();

        $this->flushPermissionCache();

        return back()->with('success', 'Role deleted.');
    }

    // ============ PERMISSIONS ============
    public function permissions(Request $request): Response
    {
        $this->authorize('managePermissions', Permissions::class);

        $permissions = Permissions::orderByDesc('id')->paginate(20);

        return Inertia::render('Admin/UserManagement/Permissions', [
            'permissions' => $permissions,
        ]);
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);

        Permissions::create($request->validate([
            'name' => 'required|string|max:100|unique:permissions,name',
            'module' => 'required|string|max:100',
        ]) + ['guard_name' => config('auth.defaults.guard')]);

        $this->flushPermissionCache();

        return back()->with('success', 'Permission created.');
    }

    public function updatePermission(Request $request, Permissions $permission): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:permissions,name,'.$permission->id.',id',
            'module' => 'required|string|max:100',
        ]);

        // Renaming is not a label change: the pivots keep pointing at this row by id,
        // but every policy checks its permission by name, so a name that code still
        // asks for stops resolving and hasPermissionTo() throws on those desks.
        if ($validated['name'] !== $permission->name && $permission->role_permissions()->exists()) {
            $grantedTo = $permission->role_permissions()->count();

            return back()->with('error', "Cannot rename: {$grantedTo} role(s) still grant {$permission->name}, and the application checks permissions by name. Add the new permission and retire this one instead.");
        }

        $permission->update($validated);

        $this->flushPermissionCache();

        return back()->with('success', 'Permission updated.');
    }

    public function destroyPermission(Permissions $permission): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);

        // role_permissions cascades, so deleting a granted permission removes it from
        // every role that carries it — and the desk that loses it only finds out when
        // a screen it can no longer open answers with a 403.
        $grantedTo = $permission->role_permissions()->count();

        if ($grantedTo > 0) {
            return back()->with('error', "Cannot delete: {$permission->name} is still granted to {$grantedTo} role(s). Revoke it from those roles first.");
        }

        $permission->delete();

        $this->flushPermissionCache();

        return back()->with('success', 'Permission deleted.');
    }

    // ============ SETTINGS ============
    public function settings(Request $request): Response
    {
        $this->authorize('manageSettings', Settings::class);

        $settings = Settings::all();

        return Inertia::render('Admin/UserManagement/Settings', [
            'settings' => $settings,
        ]);
    }

    public function updateSetting(Request $request, Settings $setting): RedirectResponse
    {
        $this->authorize('manageSettings', Settings::class);

        $setting->update($request->validate([
            'settingValue' => 'required',
        ]));

        return back()->with('success', 'Setting updated.');
    }

    // ============ AUDIT LOGS ============
    public function auditLogs(Request $request): Response
    {
        $this->authorize('viewAuditLogs', Staffusers::class);

        $query = Auditlogs::with('user')
            ->when($request->action, fn ($q, $action) => $q->where('action', $action))
            ->when($request->entityTable, fn ($q, $table) => $q->where('entityTable', $table))
            ->when($request->dateFrom, fn ($q, $date) => $q->whereDate('createdAt', '>=', $date))
            ->when($request->dateTo, fn ($q, $date) => $q->whereDate('createdAt', '<=', $date))
            ->when($request->adminOverride === '1', fn ($q) => $q->where('adminOverride', true))
            ->orderByDesc('createdAt');

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/UserManagement/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'entityTable', 'dateFrom', 'dateTo', 'adminOverride']),
        ]);
    }

    /**
     * spatie keeps a cached copy of the permission set, and it refreshes that copy
     * from its own models only: the trait's give/sync/revoke helpers reset it when
     * the actor is a spatie Role contract implementation, and the models reset it on
     * saved/deleted through RefreshesPermissionCache. App\Models\Roles and
     * App\Models\Permissions are plain Eloquent classes borrowing the trait, so
     * neither path ever fires and a save here would answer against the old set for
     * as long as the cache store keeps it.
     */
    private function flushPermissionCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
