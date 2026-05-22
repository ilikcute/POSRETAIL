<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\RolesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Requests\Auth\UpdateUserRequest;
use App\Models\Auth\User;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(protected UserRepositoryInterface $userRepository) {}

    /**
     * GET /users — Paginated list of users with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view users');

        $filters = $request->only(['search', 'role', 'is_active']);
        $perPage = min((int) $request->get('per_page', 15), 100);

        $users = $this->userRepository->paginate($perPage, $filters);

        return $this->successResponse($users, 'Daftar user berhasil diambil');
    }

    /**
     * POST /users — Create a new user and assign a role.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $data = $request->validated();
            $role = $data['role'];

            $user = $this->userRepository->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'station_id' => $data['station_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $user->assignRole($role);

            return $this->successResponse(
                $user->load('roles'),
                'User berhasil dibuat',
                201
            );
        });
    }

    /**
     * GET /users/{user} — Show a single user with roles and permissions.
     */
    public function show(int $user): JsonResponse
    {
        $this->authorize('view users');

        $userModel = $this->userRepository->findWithRoles($user);

        if (! $userModel) {
            return $this->errorResponse('User tidak ditemukan', 404);
        }

        return $this->successResponse($userModel, 'Detail user berhasil diambil');
    }

    /**
     * PUT /users/{user} — Update user data and optionally sync role.
     */
    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        return DB::transaction(function () use ($request, $user) {
            $data = $request->validated();

            $updateData = array_filter([
                'name' => $data['name'] ?? null,
                'email' => $data['email'] ?? null,
                'station_id' => $data['station_id'] ?? null,
                'is_active' => $data['is_active'] ?? null,
            ], fn ($v) => ! is_null($v));

            if (! empty($data['password'])) {
                $updateData['password'] = Hash::make($data['password']);
            }

            // If user is editing self and doesn't have 'edit users' permission, prevent modifying restricted fields
            if ($request->user()->id === $user && ! $request->user()->can('edit users')) {
                unset($updateData['station_id']);
                unset($updateData['is_active']);
                unset($data['role']);
            }

            $this->userRepository->update($user, $updateData);

            if (! empty($data['role'])) {
                $this->userRepository->syncRoles($user, [$data['role']]);
            }

            $updatedUser = $this->userRepository->findWithRoles($user);

            return $this->successResponse($updatedUser, 'User berhasil diperbarui');
        });
    }

    /**
     * DELETE /users/{user} — Soft-delete a user (cannot delete self).
     */
    public function destroy(Request $request, int $user): JsonResponse
    {
        $this->authorize('delete users');

        if ($request->user()->id === $user) {
            return $this->errorResponse('Anda tidak bisa menghapus akun sendiri', 422);
        }

        $userModel = $this->userRepository->find($user);

        if (! $userModel) {
            return $this->errorResponse('User tidak ditemukan', 404);
        }

        // Prevent deleting the only super_admin
        if ($userModel->hasRole(RolesEnum::SuperAdmin) &&
            User::role(RolesEnum::SuperAdmin)->count() === 1) {
            return $this->errorResponse('Tidak bisa menghapus satu-satunya Super Admin', 422);
        }

        $this->userRepository->delete($user);

        return $this->successResponse(null, 'User berhasil dihapus');
    }

    /**
     * PATCH /users/{user}/toggle-active — Toggle aktif/nonaktif.
     */
    public function toggleActive(Request $request, int $user): JsonResponse
    {
        $this->authorize('edit users');

        if ($request->user()->id === $user) {
            return $this->errorResponse('Anda tidak bisa menonaktifkan akun sendiri', 422);
        }

        $isActive = $this->userRepository->toggleActive($user);

        return $this->successResponse(
            ['is_active' => $isActive],
            $isActive ? 'User berhasil diaktifkan' : 'User berhasil dinonaktifkan'
        );
    }

    /**
     * POST /users/{user}/sync-roles — Replace all roles of a user.
     */
    public function syncRoles(Request $request, int $user): JsonResponse
    {
        $this->authorize('edit users');

        $validated = $request->validate([
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $this->userRepository->syncRoles($user, $validated['roles']);

        $updatedUser = $this->userRepository->findWithRoles($user);

        return $this->successResponse($updatedUser, 'Role user berhasil diperbarui');
    }
}
