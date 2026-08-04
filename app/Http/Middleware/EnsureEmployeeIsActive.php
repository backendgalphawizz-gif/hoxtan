<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $employee = $request->user('employee');

        if ($employee && ! $employee->is_active) {
            auth('employee')->logout();

            return redirect()
                ->route('filament.employee.auth.login')
                ->withErrors(['email' => 'Your employee account has been deactivated.']);
        }

        return $next($request);
    }
}
