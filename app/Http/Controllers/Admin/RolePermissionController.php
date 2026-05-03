<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RolePermission\StoreRoleRequest;
use App\Http\Requests\Admin\RolePermission\UpdateRoleRequest;
use App\Http\Responses\Response;
use App\Services\Admin\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Throwable;

class RolePermissionController extends Controller
{
    private RolePermissionService $rolePermissionService;

    public function __construct(RolePermissionService $rolePermissionService)
    {
        $this->rolePermissionService = $rolePermissionService;
    }

    /**
     * Get all roles with their permissions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $roles = $this->rolePermissionService->getRolesWithPermissions($request);

            return Response::Success(
                $roles,
                __('role_permission.index')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Index')->log($e);
            return Response::Error(
                [],
                __('role_permission.index_error')
            );
        }
    }

    /**
     * Get all permissions
     */
    public function getPermissions(): JsonResponse
    {
        try {
            $permissions = $this->rolePermissionService->getAllPermissions();

            return Response::Success(
                $permissions,
                __('role_permission.permissions_retrieved')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Get Permissions')->log($e);
            return Response::Error(
                [],
                __('role_permission.permissions_error')
            );
        }
    }

    /**
     * Create a new role
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->rolePermissionService->createRole($request->validated());

            return Response::Success(
                $role,
                __('role_permission.created')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Store')->log($e);
            return Response::Error(
                [],
                __('role_permission.create_error')
            );
        }
    }

    /**
     * Update an existing role
     */
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        try {
            $updatedRole = $this->rolePermissionService->updateRole($role, $request->validated());

            return Response::Success(
                $updatedRole,
                __('role_permission.updated')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Update')->log($e);
            return Response::Error(
                [],
                __('role_permission.update_error')
            );
        }
    }

    /**
     * Delete a role (only if no users have it)
     */
    public function destroy(Role $role): JsonResponse
    {
        try {
            $result = $this->rolePermissionService->deleteRole($role);

            return Response::Success(
                $result,
                __('role_permission.deleted')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Destroy')->log($e);
            return Response::Error(
                [],
                $e->getMessage()
            );
        }
    }

    /**
     * Check if a role can be deleted
     */
    public function canDelete(Role $role): JsonResponse
    {
        try {
            $result = $this->rolePermissionService->canDeleteRole($role);

            return Response::Success(
                $result,
                __('role_permission.delete_check_retrieved')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Can Delete')->log($e);
            return Response::Error(
                [],
                __('role_permission.delete_check_error')
            );
        }
    }

    /**
     * Get roles statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->rolePermissionService->getRolesStatistics();

            return Response::Success(
                $stats,
                __('role_permission.statistics_retrieved')
            );
        } catch (Throwable $e) {
            activity('Error: Admin Role Permission Statistics')->log($e);
            return Response::Error(
                [],
                __('role_permission.statistics_error')
            );
        }
    }
}

