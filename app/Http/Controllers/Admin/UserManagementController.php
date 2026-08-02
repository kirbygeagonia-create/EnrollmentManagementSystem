<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Staffusers;
use App\Models\Roles;
use App\Models\Permissions;
use App\Models\Offices;
use App\Models\Academicunits;
use App\Models\Settings;
use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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
            ->when($request->officeId, fn($q, $id) => $q->where('officeId', $id))
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->search, fn($q, $search) => $q->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")->orWhere('employeeNo', $search))
            ->latest();

        $users = $query->paginate(20)->withQueryString();
        $offices = Offices::all(['officeId', 'officeName']);
        $units = Academicunits::all(['unitId', 'unitName']);
        $roles = Roles::with('permissions')->get();

        return Inertia::render('Admin/UserManagement/Index', [
            'users' => $users,
            'offices' => $offices,
            'units' => $units,
            'roles' => $roles,
            'filters' => $request->only(['officeId', 'status', 'search']),
            'staffRoles' => StaffRole::cases(),
            'staffStatuses' => StaffStatus::cases(),
        ]);
    }

    /**
     * Create new staff user.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Staffusers::class);

        $validated = $request->validate([
            'officeId' => 'required|exists:offices,officeId',
            'unitId' => 'nullable|exists:academicunits,unitId',
            'employeeNo' => 'required|string|max:50|unique:staffusers,employeeNo',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:staffusers,username',
            'email' => 'required|email|max:255|unique:staffusers,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:staff,officeHead,dean,programHead,admin',
            'contactNo' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $user = Staffusers::create([
            'officeId' => $validated['officeId'],
            'unitId' => $validated['unitId'],
            'employeeNo' => $validated['employeeNo'],
            'firstName' => $validated['firstName'],
            'middleName' => $validated['middleName'],
            'lastName' => $validated['lastName'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'passwordHash' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'contactNo' => $validated['contactNo'],
            'status' => $validated['status'],
        ]);

        // Assign roles if provided
        if ($request->filled('roleIds')) {
            $user->syncRoles($request->roleIds);
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
            'officeId' => 'required|exists:offices,officeId',
            'unitId' => 'nullable|exists:academicunits,unitId',
            'employeeNo' => 'required|string|max:50|unique:staffusers,employeeNo,' . $user->userId . ',userId',
            'firstName' => 'required|string|max:100',
            'middleName' => 'nullable|string|max:100',
            'lastName' => 'required|string|max:100',
            'username' => 'required|string|max:50|unique:staffusers,username,' . $user->userId . ',userId',
            'email' => 'required|email|max:255|unique:staffusers,email,' . $user->userId . ',userId',
            'role' => 'required|in:staff,officeHead,dean,programHead,admin',
            'contactNo' => 'nullable|string|max:20',
            'status' => 'required|in:active,inactive',
        ]);

        $user->update($validated);

        // Sync roles
        if ($request->filled('roleIds')) {
            $user->syncRoles($request->roleIds);
        }

        return back()->with('success', 'Staff user updated.');
    }

    /**
     * Delete staff user.
     */
    public function destroy(Staffusers $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ($user->userId === Auth::id()) {
            return back()->withErrors(['user' => 'Cannot delete yourself.']);
        }

        $user->delete();

        return back()->with('success', 'Staff user deleted.');
    }

    /**
     * Assign roles to user.
     */
    public function assignRoles(Request $request, Staffusers $user): RedirectResponse
    {
        $this->authorize('assignRoles', $user);

        $request->validate([
            'roleIds' => 'required|array',
            'roleIds.*' => 'exists:roles,roleId',
        ]);

        $user->syncRoles($request->roleIds);

        return back()->with('success', 'Roles assigned.');
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(Staffusers $user): RedirectResponse
    {
        $this->authorize('toggleStatus', $user);

        if ($user->userId === Auth::id()) {
            return back()->withErrors(['user' => 'Cannot change your own status.']);
        }

        $user->update([
            'status' => $user->status === StaffStatus::Active ? StaffStatus::Inactive : StaffStatus::Active,
        ]);

        return back()->with('success', 'User status updated.');
    }

    // ============ ROLES ============
    public function roles(Request $request): Response
    {
        $this->authorize('manageRoles', Roles::class);

        $roles = Roles::with('permissions')->latest()->paginate(20);
        $permissions = Permissions::all(['permissionId', 'permissionName', 'module']);

        return Inertia::render('Admin/UserManagement/Roles', [
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);

        $role = Roles::create($request->validate([
            'roleName' => 'required|string|max:100|unique:roles,roleName',
            'description' => 'nullable|string',
        ]));

        if ($request->filled('permissionIds')) {
            $role->syncPermissions($request->permissionIds);
        }

        return back()->with('success', 'Role created.');
    }

    public function updateRole(Request $request, Roles $role): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);

        $role->update($request->validate([
            'roleName' => 'required|string|max:100|unique:roles,roleName,' . $role->roleId . ',roleId',
            'description' => 'nullable|string',
        ]));

        if ($request->filled('permissionIds')) {
            $role->syncPermissions($request->permissionIds);
        }

        return back()->with('success', 'Role updated.');
    }

    public function destroyRole(Roles $role): RedirectResponse
    {
        $this->authorize('manageRoles', Roles::class);
        $role->delete();
        return back()->with('success', 'Role deleted.');
    }

    // ============ PERMISSIONS ============
    public function permissions(Request $request): Response
    {
        $this->authorize('managePermissions', Permissions::class);

        $permissions = Permissions::latest()->paginate(20);

        return Inertia::render('Admin/UserManagement/Permissions', [
            'permissions' => $permissions,
        ]);
    }

    public function storePermission(Request $request): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);

        Permissions::create($request->validate([
            'permissionName' => 'required|string|max:100|unique:permissions,permissionName',
            'module' => 'required|string|max:100',
        ]));

        return back()->with('success', 'Permission created.');
    }

    public function updatePermission(Request $request, Permissions $permission): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);
        $permission->update($request->validate([
            'permissionName' => 'required|string|max:100|unique:permissions,permissionName,' . $permission->permissionId . ',permissionId',
            'module' => 'required|string|max:100',
        ]));
        return back()->with('success', 'Permission updated.');
    }

    public function destroyPermission(Permissions $permission): RedirectResponse
    {
        $this->authorize('managePermissions', Permissions::class);
        $permission->delete();
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

        $query = \App\Models\Auditlogs::with('user')
            ->when($request->action, fn($q, $action) => $q->where('action', $action))
            ->when($request->entityTable, fn($q, $table) => $q->where('entityTable', $table))
            ->when($request->dateFrom, fn($q, $date) => $q->whereDate('createdAt', '>=', $date))
            ->when($request->dateTo, fn($q, $date) => $q->whereDate('createdAt', '<=', $date))
            ->latest();

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Admin/UserManagement/AuditLogs', [
            'logs' => $logs,
            'filters' => $request->only(['action', 'entityTable', 'dateFrom', 'dateTo']),
        ]);
    }
}