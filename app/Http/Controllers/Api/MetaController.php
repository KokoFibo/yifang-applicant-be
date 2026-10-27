<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CompanyContext;

class MetaController extends Controller
{
    /** Pilihan dropdown dan daftar dokumen (sesuai perusahaan), agar frontend tidak menduplikasi aturan. */
    public function __invoke(CompanyContext $context)
    {
        $options = [];
        foreach (config('applicant.options') as $key => $list) {
            $options[$key] = collect($list)->map(fn ($v, $k) => array_is_list($list)
                ? ['value' => $v, 'label' => $v]
                : ['value' => $k, 'label' => $v])->values();
        }

        $documents = collect($context->documents())->map(fn ($d, $key) => [
            'key' => $key, 'label' => $d['label'], 'required' => $d['required'],
            'max_files' => $d['max_files'], 'hint' => $d['hint'],
        ])->values();

        return response()->json([
            'options'       => $options,
            'documents'     => $documents,
            'max_upload_mb' => round(config('applicant.image.max_upload_kb') / 1024),
            'school_detail_from' => config('applicant.school_detail_from'),
        ]);
    }
}
