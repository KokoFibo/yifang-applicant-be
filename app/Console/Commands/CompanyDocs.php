<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class CompanyDocs extends Command
{
    protected $signature = 'company:docs
        {code : Kode perusahaan}
        {--off=* : Nonaktifkan dokumen (key), mis. --off=skck --off=bri}
        {--on=* : Aktifkan kembali dokumen}
        {--required=* : Jadikan wajib}
        {--optional=* : Jadikan opsional}
        {--max=* : Atur jumlah file maksimal, format key=angka, mis. --max=cv=3}
        {--reset : Kembalikan ke daftar standar}';

    protected $description = 'Mengatur dokumen yang diminta untuk sebuah perusahaan';

    public function handle(): int
    {
        $company = Company::where('code', $this->argument('code'))->first();
        if (! $company) {
            $this->error('Perusahaan tidak ditemukan.');
            return self::FAILURE;
        }

        $valid    = array_keys(config('applicant.documents'));
        $settings = $company->settings ?? [];
        $docs     = $this->option('reset') ? [] : ($settings['documents'] ?? []);

        $apply = function (array $keys, callable $fn) use (&$docs, $valid) {
            foreach ($keys as $key) {
                if (! in_array($key, $valid, true)) {
                    $this->error("Kunci dokumen tidak dikenal: {$key}. Yang tersedia: " . implode(', ', $valid));
                    continue;
                }
                $docs[$key] = $fn($docs[$key] ?? []);
            }
        };

        $apply($this->option('off'),      fn ($d) => array_merge($d, ['enabled' => false]));
        $apply($this->option('on'),       fn ($d) => array_merge($d, ['enabled' => true]));
        $apply($this->option('required'), fn ($d) => array_merge($d, ['required' => true]));
        $apply($this->option('optional'), fn ($d) => array_merge($d, ['required' => false]));
        foreach ($this->option('max') as $pair) {
            [$key, $n] = array_pad(explode('=', $pair, 2), 2, null);
            $apply([$key], fn ($d) => array_merge($d, ['max_files' => max(1, (int) $n)]));
        }

        $settings['documents'] = $docs;
        $company->settings = $settings;
        $company->save();

        $this->info('Pengaturan dokumen tersimpan: ' . (json_encode($docs) ?: '{}'));

        return self::SUCCESS;
    }
}
