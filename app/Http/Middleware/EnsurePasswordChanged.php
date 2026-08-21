<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->force_password_change) {
            // The change-password screen itself has to stay reachable. Livewire
            // updates post to their own endpoint, which this middleware is not
            // applied to, so the form on that screen still submits.
            if ($request->routeIs('password.change')) {
                return $next($request);
            }

            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
