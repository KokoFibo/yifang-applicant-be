<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyDomain;
use App\Support\CompanyLogo;
use App\Support\DomainName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyAdd extends Command
{
    protected $signature = 'company:add
        {name : Nama perusahaan}
        {domain* : Satu atau lebih domain, mis. hr.contoh.co.id}
        {--code= : Kode unik (huruf kecil/angka/minus). Default dari nama}
        {--color=#C42A27 : Warna utama (hex, mis. #0F766E). Pilih warna yang cukup gelap agar teks putih terbaca}
        {--logo= : Path file logo di server (png/jpg/webp/svg, maks 2 MB)}
        {--from-name= : Nama pengirim email (default: nama perusahaan)}';

    protected $description = 'Menambah perusahaan baru beserta domainnya (tanpa ubah kode)';

    public function handle(): int
    {
        $name = trim($this->argument('name'));
        $code = Str::slug($this->option('code') ?: $name);
        $color = strtoupper($this->option('color'));

        if (! preg_match('/^#[0-9A-F]{6}$/', $color)) {
            $this->error('Warna harus berformat hex 6 digit, mis. #C42A27.');
            return self::FAILURE;
        }
        if ($code === '' || strlen($code) > 30 || Company::where('code', $code)->exists()) {
            $this->error("Kode \"{$code}\" kosong, terlalu panjang, atau sudah dipakai. Gunakan --code=...");
            return self::FAILURE;
        }

        $domains = [];
        foreach ($this->argument('domain') as $raw) {
            $domain = DomainName::normalize($raw);
            if (! $domain) {
                $this->error("Domain tidak valid: {$raw}");
                return self::FAILURE;
            }
            if (CompanyDomain::where('domain', $domain)->exists()) {
                $this->error("Domain sudah terdaftar: {$domain}");
                return self::FAILURE;
            }
            $domains[] = $domain;
        }

        try {
            $logo = $this->option('logo') ? CompanyLogo::store($this->option('logo'), $code) : 'company-logos/default.png';
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $code, $color, $logo, $domains) {
            $company = Company::create([
                'code' => $code, 'name' => $name, 'primary_color' => $color,
                'logo_path' => $logo, 'mail_from_name' => $this->option('from-name') ?: null,
            ]);
            foreach ($domains as $d) {
                $company->domains()->create(['domain' => $d]);
            }
        });

        $this->info("Perusahaan \"{$name}\" ({$code}) ditambahkan dengan domain: " . implode(', ', $domains));
        $this->line('Langkah berikut: arahkan DNS domain ke IP server. Dengan Caddy, HTTPS terbit otomatis. Dengan Nginx, jalankan certbot untuk domain ini.');

        return self::SUCCESS;
    }
}
