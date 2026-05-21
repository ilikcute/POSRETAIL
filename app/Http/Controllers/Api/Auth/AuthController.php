<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use App\Repositories\Contracts\Auth\UserRepositoryInterface;

class AuthController extends Controller
{
    use ApiResponseTrait;

    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = $this->userRepository->findByEmail($request->email);

        if (!$user || !Hash::check($request->password, $user->password)) {
            return $this->errorResponse('Kredensial tidak valid', 401);
        }

        if (!$user->is_active) {
            return $this->errorResponse('Akun Anda dinonaktifkan', 403);
        }

        // Update last login
        $this->userRepository->update($user->id, ['last_login_at' => now()]);

        // Generate Token via Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->load('roles'), // load roles dari spatie
            'access_token' => $token,
            'token_type' => 'Bearer'
        ], 'Login berhasil');
    }

    public function logout(Request $request): JsonResponse
    {
        // Hapus token saat ini
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout berhasil');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $request->user()->load('roles'), 
            'Data user berhasil diambil'
        );
    }

    public function register(\App\Http\Requests\Auth\RegisterRequest $request): JsonResponse
    {
        $user = $this->userRepository->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        // Secara default user yang mendaftar via API diberi role cashier
        $user->assignRole('cashier');

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => $user->load('roles'),
            'access_token' => $token,
            'token_type' => 'Bearer'
        ], 'Registrasi berhasil', 201);
    }

    public function forgotPassword(\App\Http\Requests\Auth\ForgotPasswordRequest $request): JsonResponse
    {
        $status = \Illuminate\Support\Facades\Password::broker()->sendResetLink(
            $request->only('email')
        );

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return $this->successResponse(null, __($status));
        }

        return $this->errorResponse(__($status), 400);
    }

    public function resetPassword(\App\Http\Requests\Auth\ResetPasswordRequest $request): JsonResponse
    {
        $status = \Illuminate\Support\Facades\Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                // Di sini kita update via repository
                $this->userRepository->update($user->id, [
                    'password' => Hash::make($password)
                ]);
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return $this->successResponse(null, __($status));
        }

        return $this->errorResponse(__($status), 400);
    }
}
