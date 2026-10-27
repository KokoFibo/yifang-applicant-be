<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CompanyContext;

class TenantController extends Controller
{
    /** Tampilan perusahaan untuk domain ini: nama, logo, warna. Dibaca frontend saat dibuka. */
    public function __invoke(CompanyContext $context)
    {
        $c = $context->get();

        return response()->json(['company' => [
            'code'          => $c->code,
            'name'          => $c->name,
            'primary_color' => $c->primary_color,
            'logo_url'      => $c->logoUrl(),
        ]]);
    }
}
