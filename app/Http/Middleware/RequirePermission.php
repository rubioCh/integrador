<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->forbidden($request, 'Unauthenticated.');
        }

        if (! method_exists($user, 'hasPermission') || ! $user->hasPermission($permission)) {
            return $this->forbidden($request, 'Insufficient permissions.');
        }

        return $next($request);
    }

    private function forbidden(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse([
                'success' => false,
                'message' => $message,
            ], 403);
        }

        abort(403, $message);
    }
}
