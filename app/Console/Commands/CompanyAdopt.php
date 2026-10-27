<?php

namespace App\Console\Commands;

use App\Models\Applicantdata;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompanyAdopt extends Command
{
    protected $signature = 'company:adopt {code : Kode perusahaan pemilik data lama} {--force : Lewati konfirmasi}';

    protected $description = 'Memasukkan pelamar lama (company_id kosong) ke satu perusahaan';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();
        if (! $company) {
            $this->error('Perusahaan tidak ditemukan.');
            return self::FAILURE;
        }

        $total = Applicantdata::whereNull('company_id')->count();
        if ($total === 0) {
            $this->info('Tidak ada pelamar lama tanpa perusahaan.');
            return self::SUCCESS;
        }

        // Email ganda di data lama akan melanggar indeks unik (company_id + email)
        $dups = DB::table('applicantdatas')->whereNull('company_id')
            ->select('email', DB::raw('COUNT(*) as n'))->groupBy('email')->having('n', '>', 1)->pluck('n', 'email');
        $existing = DB::table('applicantdatas')->whereNull('company_id')
            ->whereIn('email', fn ($q) => $q->select('email')->from('applicantdatas')->where('company_id', $company->id))
            ->pluck('email');

        if ($dups->isNotEmpty() || $existing->isNotEmpty()) {
            $this->error('Ada email ganda. Rapikan dulu (hapus/ubah salah satu), lalu jalankan ulang:');
            foreach ($dups as $email => $n) {
                $this->line(" - {$email} muncul {$n}x di data lama");
            }
            foreach ($existing as $email) {
                $this->line(" - {$email} sudah terdaftar di {$company->code}");
            }
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Masukkan {$total} pelamar lama ke \"{$company->name}\"? Cadangkan database lebih dulu.")) {
            return self::FAILURE;
        }

        $n = Applicantdata::whereNull('company_id')->update(['company_id' => $company->id]);
        $this->info("{$n} pelamar dimasukkan ke {$company->code}. Mereka perlu memakai \"Lupa password\" untuk login.");

        return self::SUCCESS;
    }
}
