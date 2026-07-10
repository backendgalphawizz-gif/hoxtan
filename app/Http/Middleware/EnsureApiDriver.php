<?php

namespace App\Http\Middleware;

use App\Models\Driver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Driver || ! $user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'data' => (object) [],
            ], 401);
        }

        return $next($request);
    }
}
