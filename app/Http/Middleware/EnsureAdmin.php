<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');

        // Der admin-Guard kennt nur den einen Env-Admin (EnvAdminUserProvider);
        // ein eingeloggter User auf diesem Guard ist damit automatisch Admin.
        if (! $guard->check()) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
