<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyDomain;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Data awal. NAMA di bawah adalah PLACEHOLDER: ganti dengan
 *   php artisan company:update <kode> --name="Nama Resmi" --color="#hex" --logo=/path/logo.png
 * Seeder aman dijalankan berulang (tidak menimpa nama/warna/logo yang sudah Anda ubah).
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        // Logo bawaan (logo PT Yifang C.M.E) dipakai sementara oleh semua perusahaan
        Storage::disk('public')->put('company-logos/default.png', file_get_contents(__DIR__ . '/assets/logo.png'));

        $companies = [
            ['code' => 'payroll', 'name' => 'Yifang Payroll', 'domain' => 'payrolll-applicant.yifang.co.id'], // sesuai input: "payrolll" (3 huruf l). Perbaiki jika salah ketik.
            ['code' => 'salary',  'name' => 'Yifang Salary',  'domain' => 'salary-applicant.yifang.co.id'],
            ['code' => 'sti',     'name' => 'Yifang STI',     'domain' => 'sti-applicant.yifang.co.id'],
            ['code' => 'bai',     'name' => 'Yifang BAI',     'domain' => 'bai-applicant.yifang.co.id'],
        ];

        foreach ($companies as $i => $c) {
            $company = Company::firstOrCreate(
                ['code' => $c['code']],
                ['name' => $c['name'], 'primary_color' => '#C42A27', 'logo_path' => 'company-logos/default.png']
            );

            $domains = [$c['domain']];
            if (! app()->environment('production')) {
                // Untuk uji di komputer sendiri: payroll.localhost:5173, sti.localhost:5173, dst.
                // (browser modern otomatis mengarahkan *.localhost ke komputer Anda)
                $domains[] = "{$c['code']}.localhost";
                if ($i === 0) {
                    $domains[] = 'localhost';
                }
            }
            foreach ($domains as $domain) {
                CompanyDomain::firstOrCreate(['domain' => $domain], ['company_id' => $company->id]);
            }
        }
    }
}
