<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('password.required.*')
            && ! $request->routeIs('*.logout')
        ) {
            return redirect()->route('password.required.edit');
        }

        return $next($request);
    }
}
