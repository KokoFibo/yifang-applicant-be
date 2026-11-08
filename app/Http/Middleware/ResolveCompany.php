<?php

namespace App\Http\Middleware;

use App\Models\CompanyDomain;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;

class ResolveCompany
{
    public function handle(Request $request, Closure $next)
    {
        // Ambil hostname tanpa port
        $host = strtolower($request->getHost());

        // Hilangkan prefix www. jika ada
        $domain = preg_replace('/^www\./', '', $host);

        // Cari perusahaan berdasarkan domain utama
        $company = CompanyDomain::with('company')
            ->where('domain', $domain)
            ->first()?->company;

        if (! $company || ! $company->is_active) {
            return response()->json([
                'message' => 'Domain ini belum terdaftar.',
                'code' => 'company_not_found',
            ], 404);
        }

        // Set konteks perusahaan
        app(CompanyContext::class)->set($company);

        // Nama aplikasi dan pengirim email mengikuti perusahaan
        config([
            'app.name' => $company->name,
            'mail.from.name' => $company->mail_from_name ?: $company->name,
        ]);

        return $next($request);
    }
}
