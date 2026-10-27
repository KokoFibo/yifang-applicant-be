<?php

namespace App\Support;

use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyLogo
{
    /** Salin file logo lokal ke disk "public". Mengembalikan path relatif untuk kolom logo_path. */
    public static function store(string $localPath, string $code): string
    {
        if (! is_file($localPath)) {
            throw new \InvalidArgumentException("File logo tidak ditemukan: {$localPath}");
        }
        $ext = strtolower(pathinfo($localPath, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            throw new \InvalidArgumentException('Format logo harus png, jpg, webp, atau svg.');
        }
        if (filesize($localPath) > 2 * 1024 * 1024) {
            throw new \InvalidArgumentException('Ukuran logo maksimal 2 MB.');
        }

        $name = $code . '-' . Str::lower(Str::random(6)) . '.' . $ext; // suffix acak agar cache browser ikut diperbarui

        return Storage::disk('public')->putFileAs('company-logos', new File($localPath), $name);
    }
}
