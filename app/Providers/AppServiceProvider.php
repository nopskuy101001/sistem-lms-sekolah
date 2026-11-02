<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            if (! $user) {
                return;
            }

            $view->with([
                'headerNotifications' => $user->notifications()->latest()->limit(6)->get(),
                'unreadNotificationCount' => $user->unreadNotifications()->count(),
            ]);
        });
    }
}
