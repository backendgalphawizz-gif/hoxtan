<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin && ! $admin->is_active) {
            auth('admin')->logout();

            return redirect()
                ->route('filament.admin.auth.login')
                ->withErrors(['email' => 'Your admin account has been deactivated.']);
        }

        return $next($request);
    }
}
