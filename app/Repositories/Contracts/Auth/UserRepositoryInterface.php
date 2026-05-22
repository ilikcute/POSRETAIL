<?php

namespace App\Repositories\Contracts\Auth;

use App\Models\Auth\User;
use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email);

    /**
     * Paginated user list with roles eager-loaded.
     *
     * @param  array<string, mixed>  $filters  Supports: search, role, is_active
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator;

    /**
     * Find a user with roles and permissions loaded.
     */
    public function findWithRoles(int $id);

    /**
     * Sync roles for a user.
     *
     * @param  array<string>  $roles
     */
    public function syncRoles(int $userId, array $roles): void;

    /**
     * Toggle the is_active flag for a user.
     */
    public function toggleActive(int $userId): bool;

    /**
     * Get all active users (for dropdowns).
     *
     * @return Collection<int, User>
     */
    public function allActive(): Collection;
}
