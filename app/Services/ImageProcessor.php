<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Intervention\Image\ImageManager;

class ImageProcessor
{
    /**
     * Baca gambar, koreksi orientasi EXIF, perkecil (tidak pernah diperbesar),
     * lalu simpan sebagai WebP. Metadata asli (termasuk lokasi GPS) hilang
     * karena gambar di-encode ulang.
     */
    public function toWebp(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;

        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];
        if (! $info || ! in_array($info[2], $allowed, true)) {
            throw ValidationException::withMessages(['file' => 'File harus berupa gambar JPG, PNG, atau WebP.']);
        }
        if ($info[0] * $info[1] > (int) config('applicant.image.max_pixels')) {
            throw ValidationException::withMessages(['file' => 'Resolusi gambar terlalu besar. Perkecil gambar lalu coba lagi.']);
        }

        // Catatan: dekode gambar besar butuh memori. Atur memory_limit PHP-FPM minimal 256M (lihat README).

        try {
            $image = ImageManager::gd()
                ->read($path)
                ->scaleDown(
                    width: (int) config('applicant.image.max_width'),
                    height: (int) config('applicant.image.max_height')
                );

            return (string) $image->toWebp((int) config('applicant.image.quality'));
        } catch (\Throwable $e) {
            report($e);
            // Saat APP_DEBUG=true, tampilkan penyebab teknisnya agar mudah ditelusuri (jangan aktifkan di produksi).
            $detail = config('app.debug') ? ' [' . class_basename($e) . ': ' . $e->getMessage() . ']' : '';
            throw ValidationException::withMessages(['file' => 'Gambar tidak dapat diproses. Coba gunakan file lain.' . $detail]);
        }
    }
}
