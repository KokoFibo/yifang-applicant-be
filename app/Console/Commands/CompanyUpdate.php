<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\CompanyLogo;
use Illuminate\Console\Command;

class CompanyUpdate extends Command
{
    protected $signature = 'company:update
        {code : Kode perusahaan}
        {--name= : Nama baru}
        {--color= : Warna utama baru (hex)}
        {--logo= : Path file logo baru}
        {--from-name= : Nama pengirim email baru}
        {--active= : 1 = aktif, 0 = nonaktif (domainnya menjadi tidak tersedia)}';

    protected $description = 'Mengubah nama, warna, logo, atau status perusahaan';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();
        if (! $company) {
            $this->error('Perusahaan tidak ditemukan. Lihat daftar: php artisan company:list');
            return self::FAILURE;
        }

        if ($this->option('name')) {
            $company->name = trim($this->option('name'));
        }
        if ($this->option('from-name')) {
            $company->mail_from_name = trim($this->option('from-name'));
        }
        if ($this->option('color')) {
            $color = strtoupper($this->option('color'));
            if (! preg_match('/^#[0-9A-F]{6}$/', $color)) {
                $this->error('Warna harus berformat hex 6 digit, mis. #C42A27.');
                return self::FAILURE;
            }
            $company->primary_color = $color;
        }
        if ($this->option('logo')) {
            try {
                $company->logo_path = CompanyLogo::store($this->option('logo'), $company->code);
            } catch (\Throwable $e) {
                $this->error($e->getMessage());
                return self::FAILURE;
            }
        }
        if ($this->option('active') !== null) {
            $company->is_active = (bool) (int) $this->option('active');
        }

        $company->save();
        $this->info("Perusahaan {$company->code} diperbarui. Muat ulang halaman di browser untuk melihat perubahannya.");

        return self::SUCCESS;
    }
}
