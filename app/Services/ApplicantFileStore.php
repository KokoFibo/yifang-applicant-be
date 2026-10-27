<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicantFileStore
{
    private ?Filesystem $disk = null;

    public function disk(): Filesystem
    {
        // Disk dibuat dengan 'throw' => true agar penyebab asli (kunci AWS salah, bucket tidak ada,
        // izin folder, dst.) muncul di log dan tidak berhenti sebagai "false" yang tidak jelas.
        if (! $this->disk) {
            $name = config('applicant.file_disk');
            $conf = config("filesystems.disks.{$name}");
            if (! is_array($conf)) {
                throw new \RuntimeException("Disk penyimpanan \"{$name}\" tidak ada di config/filesystems.php.");
            }
            $this->disk = Storage::build(array_merge($conf, ['throw' => true]));
        }

        return $this->disk;
    }

    /** Simpan biner WebP. Nama file acak agar tidak bisa ditebak. Mengembalikan path. */
    public function put(string $companyCode, string $applicantId, string $type, string $binary): string
    {
        $path = "Applicants/{$companyCode}/{$applicantId}/{$type}-" . Str::lower(Str::random(20)) . '.webp';

        // ACL hanya dikirim untuk mode 'public'. Mode 'private' dan 'public-policy' tidak mengirim ACL,
        // sehingga juga cocok untuk bucket S3 baru yang ACL-nya dinonaktifkan (bawaan AWS).
        $options = config('applicant.file_visibility') === 'public' ? ['visibility' => 'public'] : [];

        $ok = $this->disk()->put($path, $binary, $options);
        if (! $ok) {
            throw new \RuntimeException('Gagal menyimpan file ke penyimpanan.');
        }

        return $path;
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function url(string $path): string
    {
        if (config('applicant.file_visibility') === 'private') {
            return $this->disk()->temporaryUrl($path, now()->addMinutes(10));
        }

        return $this->disk()->url($path);
    }
}
