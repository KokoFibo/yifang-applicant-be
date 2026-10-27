<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyDomain;
use App\Support\DomainName;
use Illuminate\Console\Command;

class CompanyDomainCommand extends Command
{
    protected $signature = 'company:domain {code : Kode perusahaan} {domain* : Domain} {--remove : Hapus domain (bukan menambah)}';

    protected $description = 'Menambah atau menghapus domain milik sebuah perusahaan';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();
        if (! $company) {
            $this->error('Perusahaan tidak ditemukan.');
            return self::FAILURE;
        }

        foreach ($this->argument('domain') as $raw) {
            $domain = DomainName::normalize($raw);
            if (! $domain) {
                $this->error("Domain tidak valid: {$raw}");
                continue;
            }

            if ($this->option('remove')) {
                $n = CompanyDomain::where('company_id', $company->id)->where('domain', $domain)->delete();
                $this->line($n ? "Dihapus: {$domain}" : "Tidak ditemukan pada perusahaan ini: {$domain}");
                continue;
            }

            $existing = CompanyDomain::where('domain', $domain)->first();
            if ($existing) {
                $this->error("Sudah terdaftar: {$domain}");
                continue;
            }
            $company->domains()->create(['domain' => $domain]);
            $this->info("Ditambahkan: {$domain}");
        }

        return self::SUCCESS;
    }
}
