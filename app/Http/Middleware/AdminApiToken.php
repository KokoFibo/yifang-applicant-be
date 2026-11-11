<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** API admin: hanya menerima "Authorization: Bearer <token>" yang SHA-256-nya cocok dengan ADMIN_API_TOKEN_HASH. */
class AdminApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('admin.api_token_hash');
        $token    = (string) $request->bearerToken();

        if ($expected === '' || $token === '' || ! hash_equals($expected, hash('sha256', $token))) {
            usleep(300000); // memperlambat tebakan
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
