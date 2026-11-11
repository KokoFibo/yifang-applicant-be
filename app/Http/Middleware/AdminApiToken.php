<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * API admin: hanya menerima "Authorization: Bearer <token>" yang SHA-256-nya cocok dengan ADMIN_API_TOKEN_HASH.
 *
 * - Token yang benar langsung lolos dan TIDAK pernah terkena pembatas kegagalan, sehingga pihak luar
 *   tidak bisa mengunci konsol admin hanya dengan mengirim token palsu.
 * - Token yang salah dihitung per IP; setelah MAX_FAILURES kali dalam DECAY_SECONDS dijawab 429.
 * - Tidak ada sleep(): menahan worker PHP-FPM justru memudahkan serangan, bukan menghambat penebak.
 * - Jika ADMIN_API_HOSTS diisi, API ini hanya melayani host tersebut (di domain tenant dijawab 404).
 */
class AdminApiToken
{
    public const MAX_FAILURES  = 10;
    public const DECAY_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $hosts = (array) config('admin.allowed_hosts', []);
        if ($hosts !== [] && ! in_array(strtolower($request->getHost()), $hosts, true)) {
            return response()->json(['message' => 'Not Found'], 404);
        }

        $expected = (string) config('admin.api_token_hash');
        $token    = (string) $request->bearerToken();

        if ($expected !== '' && $token !== '' && hash_equals($expected, hash('sha256', $token))) {
            return $next($request);
        }

        $key = 'admin-api-fail:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_FAILURES)) {
            return response()->json(['message' => 'Too Many Attempts'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        // Catat satu kali saja saat batas tercapai (bukan setiap kegagalan) agar log tidak banjir saat diserang.
        if (RateLimiter::attempts($key) === self::MAX_FAILURES) {
            Log::warning('admin.api.locked', ['ip' => $request->ip()]);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }
}
