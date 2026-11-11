<?php

namespace Tests\Support;

use App\Services\ApplicantFileStore;

/** Pengganti ApplicantFileStore untuk tes: tidak menyentuh S3, hanya mencatat penghapusan. */
class FakeFileStore extends ApplicantFileStore
{
    /** @var string[] */
    public array $deleted = [];

    public function url(string $path): string
    {
        return 'https://files.test/' . $path;
    }

    public function delete(string $path): void
    {
        $this->deleted[] = $path;
    }
}
