<?php

namespace App\Providers;

use App\Enums\RolesEnum;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Password reset URL configuration
        ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return env('FRONTEND_URL', 'http://localhost:3000')."/reset-password?token={$token}&email=".$notifiable->getEmailForPasswordReset();
        });

        // Super Admin has all permissions automatically
        Gate::before(function ($user, $ability) {
            return $user->hasRole(RolesEnum::SuperAdmin) ? true : null;
        });

        // Define permission gates for user management using Spatie permissions
        Gate::define('view users', fn ($user) => $user->hasPermissionTo('view users'));
        Gate::define('create users', fn ($user) => $user->hasPermissionTo('create users'));
        Gate::define('edit users', fn ($user) => $user->hasPermissionTo('edit users'));
        Gate::define('delete users', fn ($user) => $user->hasPermissionTo('delete users'));
    }
}
