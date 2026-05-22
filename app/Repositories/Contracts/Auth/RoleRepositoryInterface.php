<?php

namespace App\Repositories\Contracts\Auth;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Role;

interface RoleRepositoryInterface extends BaseRepositoryInterface
{
    /**
     * Get all roles with permission count.
     *
     * @return Collection<int, Role>
     */
    public function allWithPermissionCount(): Collection;

    /**
     * Find a role by name.
     */
    public function findByName(string $name): ?Role;

    /**
     * Find a role by ID with its permissions loaded.
     */
    public function findWithPermissions(int $id): ?Role;

    /**
     * Sync permissions on a role, returns the updated role.
     *
     * @param  array<string>  $permissions
     */
    public function syncPermissions(int $roleId, array $permissions): Role;

    /**
     * Get all available permissions grouped by module prefix.
     *
     * @return array<string, array<string>>
     */
    public function getAllPermissionsGrouped(): array;
}
