<?php

namespace App\Http\Middleware;

use App\Models\CompanyDomain;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;

/** Menentukan perusahaan dari domain request. Domain yang tidak terdaftar ditolak. */
class ResolveCompany
{
    public function handle(Request $request, Closure $next)
    {
        $host    = strtolower($request->getHost()); // tanpa port
        $company = CompanyDomain::with('company')->where('domain', $host)->first()?->company;

        if (! $company || ! $company->is_active) {
            return response()->json(['message' => 'Domain ini belum terdaftar.', 'code' => 'company_not_found'], 404);
        }

        app(CompanyContext::class)->set($company);

        // Nama di header/footer email standar Laravel dan nama pengirim mengikuti perusahaan.
        config([
            'app.name'       => $company->name,
            'mail.from.name' => $company->mail_from_name ?: $company->name,
        ]);

        return $next($request);
    }
}
