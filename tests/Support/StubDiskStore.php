<?php

namespace Tests\Support;

use App\Services\ApplicantFileStore;
use Illuminate\Contracts\Filesystem\Filesystem;

/** ApplicantFileStore yang memakai disk tiruan (mock) yang diberikan tes. */
class StubDiskStore extends ApplicantFileStore
{
    public function __construct(private Filesystem $stub)
    {
    }

    public function disk(): Filesystem
    {
        return $this->stub;
    }
}
