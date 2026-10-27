<?php

namespace App\Support;

class Text
{
    /** "budi  santoso" -> "Budi Santoso" */
    public static function title(?string $value): ?string
    {
        $value = self::clean($value);

        return $value === null ? null : mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    public static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        return $value === '' ? null : $value;
    }

    /** Nama file asli yang aman ditampilkan: tanpa ekstensi, tanpa karakter aneh, maks 100 karakter. */
    public static function safeFilename(string $original): string
    {
        $name = pathinfo($original, PATHINFO_FILENAME);
        $name = preg_replace('/[^\p{L}\p{N} ._-]/u', '', $name) ?? '';
        $name = mb_substr(trim($name), 0, 100);

        return $name === '' ? 'file' : $name;
    }
}
