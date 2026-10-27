<?php

namespace App\Support;

use App\Models\Company;

/** Perusahaan yang sedang dilayani pada request ini (ditentukan dari domain). */
class CompanyContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function get(): Company
    {
        if (! $this->company) {
            throw new \RuntimeException('Perusahaan belum ditentukan untuk request ini.');
        }

        return $this->company;
    }

    /**
     * Daftar dokumen untuk perusahaan ini: standar dari config, lalu diubah oleh
     * companies.settings.documents, mis. {"skck":{"enabled":false},"npwp":{"required":true}}
     */
    public function documents(): array
    {
        $docs      = config('applicant.documents');
        $overrides = $this->get()->settings['documents'] ?? [];

        foreach ($overrides as $key => $o) {
            if (! isset($docs[$key]) || ! is_array($o)) {
                continue;
            }
            if (($o['enabled'] ?? true) === false) {
                unset($docs[$key]);
                continue;
            }
            if (array_key_exists('required', $o)) {
                $docs[$key]['required'] = (bool) $o['required'];
            }
            if (isset($o['max_files'])) {
                $docs[$key]['max_files'] = max(1, (int) $o['max_files']);
            }
        }

        return $docs;
    }

    /** Awal URL (skema + domain + port) tempat pelamar sedang membuka aplikasi. */
    public function baseUrl(): string
    {
        return request()->getSchemeAndHttpHost();
    }
}
