<?php

use App\Http\Controllers\Api\Admin\ApplicantAdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Middleware\AdminApiToken;
use App\Http\Middleware\EnsureSameCompany;
use App\Http\Middleware\ResolveCompany;
use App\Models\CompanyDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Semua rute ada di sini (grup "web": sesi + cookie + CSRF) dengan prefix /api,
| karena SPA dan API berada di domain yang sama pada tiap perusahaan.
| Tidak memerlukan Sanctum dan tidak ada daftar domain statis di .env.
*/

// Dipakai Caddy (on-demand TLS) untuk menanyakan "boleh menerbitkan sertifikat untuk domain ini?".
// Hanya bisa diakses dari komputer yang sama.
Route::get('/internal/tls-ask', function (Request $request) {
    abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 404);

    $domain = strtolower((string) $request->query('domain'));
    $ok = $domain !== '' && CompanyDomain::where('domain', $domain)->whereHas('company', fn ($q) => $q->where('is_active', true))->exists();

    return response('', $ok ? 200 : 404);
});

Route::prefix('api')->middleware(ResolveCompany::class)->group(function () {
    Route::get('/csrf', fn () => response()->noContent());   // memastikan cookie XSRF-TOKEN terpasang
    Route::get('/tenant', TenantController::class)->middleware('throttle:120,1');
    Route::get('/meta', MetaController::class)->middleware('throttle:60,1');

    // Publik (dibatasi laju)
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1');
    Route::post('/email/verify', [AuthController::class, 'verifyEmail'])->middleware('throttle:20,1');
    Route::post('/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');

    // Perlu login
    Route::middleware(['auth:applicant', EnsureSameCompany::class])->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile/{section}', [ProfileController::class, 'update'])->where('section', 'pribadi|kontak|identitas|pendidikan');
        Route::post('/profile/submit', [ProfileController::class, 'submit'])->middleware('throttle:10,1');

        Route::get('/documents', [DocumentController::class, 'index']);
        Route::post('/documents', [DocumentController::class, 'store'])->middleware('throttle:60,1');
        Route::post('/documents/{id}/replace', [DocumentController::class, 'replace'])->whereNumber('id')->middleware('throttle:60,1');
        Route::delete('/documents/{id}', [DocumentController::class, 'destroy'])->whereNumber('id');
    });
});

/*
| API admin (baca-saja) untuk konsol Astro di Cloudflare.
| Di luar ResolveCompany (mencakup semua perusahaan), dilindungi token Bearer (AdminApiToken).
| Alamatnya /api/admin/... sehingga sudah diteruskan oleh Nginx ke Laravel.
*/
Route::prefix('api/admin')->middleware([AdminApiToken::class, 'throttle:120,1'])->group(function () {
    Route::get('/meta', [ApplicantAdminController::class, 'meta']);
    Route::get('/summary', [ApplicantAdminController::class, 'summary']);
    Route::get('/applicants', [ApplicantAdminController::class, 'index']);
    Route::get('/applicants/export', [ApplicantAdminController::class, 'export']);
    Route::get('/applicants/{id}', [ApplicantAdminController::class, 'show'])->whereNumber('id');
});
