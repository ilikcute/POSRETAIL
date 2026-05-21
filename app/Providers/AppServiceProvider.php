<?php

namespace App\Providers;

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
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(function ($notifiable, $token) {
            return env('FRONTEND_URL', 'http://localhost:3000') . "/reset-password?token={$token}&email={$notifiable->getEmailForPasswordReset()}";
        });
    }
}
