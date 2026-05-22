<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreRoleRequest;
use App\Http\Requests\Auth\UpdateRoleRequest;
use App\Repositories\Contracts\Auth\RoleRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected RoleRepositoryInterface $roleRepository) {}

    /**
     * GET /roles — List all roles with permission count.
     */
    public function index(): JsonResponse
    {
        $this->authorize('view roles');

        $roles = $this->roleRepository->allWithPermissionCount();

        return $this->successResponse($roles, 'Daftar role berhasil diambil');
    }

    /**
     * POST /roles — Create a new role with permissions.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();

            /** @var Role $role */
            $role = $this->roleRepository->create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions($data['permissions']);

            return $this->successResponse(
                $role->load('permissions'),
                'Role berhasil dibuat',
                201
            );
        });
    }

    /**
     * GET /roles/{role} — Show role detail with permissions.
     */
    public function show(int $role): JsonResponse
    {
        $this->authorize('view roles');

        $roleModel = $this->roleRepository->findWithPermissions($role);

        return $this->successResponse($roleModel, 'Detail role berhasil diambil');
    }

    /**
     * PUT /roles/{role} — Update role name and/or permissions.
     */
    public function update(UpdateRoleRequest $request, int $role): JsonResponse
    {
        return DB::transaction(function () use ($request, $role) {
            $data = $request->validated();

            if (! empty($data['name'])) {
                $this->roleRepository->update($role, ['name' => $data['name']]);
            }

            $updatedRole = $this->roleRepository->find($role);

            if (isset($data['permissions'])) {
                $updatedRole->syncPermissions($data['permissions']);
            }

            return $this->successResponse(
                $updatedRole->load('permissions'),
                'Role berhasil diperbarui'
            );
        });
    }

    /**
     * DELETE /roles/{role} — Delete a role (cannot delete super_admin).
     */
    public function destroy(int $role): JsonResponse
    {
        $this->authorize('delete roles');

        $roleModel = $this->roleRepository->find($role);

        if (! $roleModel) {
            return $this->errorResponse('Role tidak ditemukan', 404);
        }

        if ($roleModel->name === RolesEnum::SuperAdmin->value) {
            return $this->errorResponse('Role Super Admin tidak bisa dihapus', 422);
        }

        // Detach all users before deleting
        if ($roleModel->users()->count() > 0) {
            return $this->errorResponse(
                "Role ini masih digunakan oleh {$roleModel->users()->count()} user. Pindahkan user terlebih dahulu.",
                422
            );
        }

        $this->roleRepository->delete($role);

        return $this->successResponse(null, 'Role berhasil dihapus');
    }

    /**
     * GET /roles/permissions/all — Get all available permissions grouped by module.
     */
    public function permissions(): JsonResponse
    {
        $this->authorize('view roles');

        $grouped = $this->roleRepository->getAllPermissionsGrouped();

        return $this->successResponse($grouped, 'Daftar permission berhasil diambil');
    }

    /**
     * POST /roles/{role}/sync-permissions — Replace all permissions of a role.
     */
    public function syncPermissions(Request $request, int $role): JsonResponse
    {
        $this->authorize('edit roles');

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $updatedRole = $this->roleRepository->syncPermissions($role, $validated['permissions']);

        return $this->successResponse($updatedRole, 'Permission role berhasil diperbarui');
    }
}
