<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\Log;

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
        // Log failed authentication attempts (helpful for Filament login debugging)
        Event::listen(Failed::class, function (Failed $event) {
            try {
                $email = $event->credentials['email'] ?? null;
                $guard = $event->guard ?? null;
                $userClass = is_object($event->user) ? get_class($event->user) : null;

                Log::channel('filament_auth')->warning('Auth failed', [
                    'email' => $email,
                    'guard' => $guard,
                    'user_class' => $userClass,
                    'request_path' => request()->path(),
                    'ip' => request()->ip(),
                ]);
            } catch (\Throwable $e) {
                // swallow to avoid breaking boot
                Log::error('Failed to log auth failed event: ' . $e->getMessage());
            }
        });
    }
}
