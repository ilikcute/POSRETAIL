<?php

namespace App\Repositories\Eloquent\Auth;

use App\Models\Auth\User;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use App\Repositories\Eloquent\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Paginated user list with roles eager-loaded.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = $this->model->with('roles')->orderBy('name');

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['role'])) {
            $query->role($filters['role']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Find a user with roles and permissions loaded.
     */
    public function findWithRoles(int $id): ?User
    {
        return $this->model->with('roles.permissions')->find($id);
    }

    /**
     * Sync roles for a user.
     *
     * @param  array<string>  $roles
     */
    public function syncRoles(int $userId, array $roles): void
    {
        $user = $this->model->findOrFail($userId);
        $user->syncRoles($roles);
    }

    /**
     * Toggle the is_active flag for a user.
     */
    public function toggleActive(int $userId): bool
    {
        $user = $this->model->findOrFail($userId);
        $user->is_active = ! $user->is_active;
        $user->save();

        return $user->is_active;
    }

    /**
     * Get all active users (for dropdowns).
     *
     * @return Collection<int, User>
     */
    public function allActive(): Collection
    {
        return $this->model->where('is_active', true)->with('roles')->orderBy('name')->get();
    }
}
