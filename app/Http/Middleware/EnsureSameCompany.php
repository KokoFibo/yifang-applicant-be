<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Sesi pelamar dari perusahaan lain tidak boleh dipakai di domain ini. */
class EnsureSameCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('applicant');

        if ($user && (int) $user->company_id !== app(CompanyContext::class)->get()->id) {
            Auth::guard('applicant')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Sesi tidak valid. Silakan login kembali.'], 401);
        }

        return $next($request);
    }
}
