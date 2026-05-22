<?php

namespace App\Repositories\Eloquent\Auth;

use App\Repositories\Contracts\Auth\RoleRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleRepository extends BaseRepository implements RoleRepositoryInterface
{
    public function __construct(Role $model)
    {
        parent::__construct($model);
    }

    /**
     * Get all roles with permission count.
     *
     * @return Collection<int, Role>
     */
    public function allWithPermissionCount(): Collection
    {
        return Role::withCount('permissions')->orderBy('name')->get();
    }

    /**
     * Find a role by name.
     */
    public function findByName(string $name): ?Role
    {
        return Role::where('name', $name)->first();
    }

    /**
     * Find a role by ID with its permissions loaded.
     */
    public function findWithPermissions(int $id): ?Role
    {
        return Role::with('permissions')->findOrFail($id);
    }

    /**
     * Sync permissions on a role, returns the updated role.
     *
     * @param  array<string>  $permissions
     */
    public function syncPermissions(int $roleId, array $permissions): Role
    {
        /** @var Role $role */
        $role = Role::findOrFail($roleId);
        $role->syncPermissions($permissions);

        return $role->load('permissions');
    }

    /**
     * Get all available permissions grouped by module prefix.
     *
     * @return array<string, array<string>>
     */
    public function getAllPermissionsGrouped(): array
    {
        $permissions = Permission::orderBy('name')->pluck('name');

        $grouped = [];
        foreach ($permissions as $permission) {
            // Extract group from last word(s): "view users" → group "users"
            $parts = explode(' ', $permission);
            $action = array_shift($parts); // "view", "create", etc.
            $module = implode(' ', $parts); // "users", "stock opnames", etc.

            $grouped[$module][] = $permission;
        }

        ksort($grouped);

        return $grouped;
    }
}
