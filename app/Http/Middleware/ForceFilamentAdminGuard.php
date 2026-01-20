<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceFilamentAdminGuard
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $guard = config('filament.auth.guard', 'admin');
            Auth::shouldUse($guard);
        } catch (\Throwable $e) {
            // swallow
        }

        return $next($request);
    }
}
