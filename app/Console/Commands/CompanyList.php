<?php

namespace App\Console\Commands;

use App\Models\Applicantdata;
use App\Models\Company;
use Illuminate\Console\Command;

class CompanyList extends Command
{
    protected $signature = 'company:list';

    protected $description = 'Daftar perusahaan, domain, dan jumlah pelamar';

    public function handle(): int
    {
        $rows = Company::with('domains')->orderBy('id')->get()->map(fn ($c) => [
            $c->code, $c->name, $c->primary_color, $c->is_active ? 'aktif' : 'nonaktif',
            $c->domains->pluck('domain')->implode("\n"),
            Applicantdata::where('company_id', $c->id)->count(),
        ]);

        $this->table(['Kode', 'Nama', 'Warna', 'Status', 'Domain', 'Pelamar'], $rows);
        $orphans = Applicantdata::whereNull('company_id')->count();
        if ($orphans) {
            $this->warn("{$orphans} pelamar lama belum punya perusahaan. Jalankan: php artisan company:adopt <kode>");
        }

        return self::SUCCESS;
    }
}
