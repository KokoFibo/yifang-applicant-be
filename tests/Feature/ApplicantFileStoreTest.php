<?php

namespace Tests\Feature;

use App\Services\ApplicantFileStore;
use Illuminate\Filesystem\FilesystemAdapter;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\StubDiskStore;
use Tests\TestCase;

class ApplicantFileStoreTest extends TestCase
{
    private function disk(): Mockery\MockInterface
    {
        return Mockery::mock(FilesystemAdapter::class);
    }

    public function test_visibility_defaults_to_private_when_env_is_not_set(): void
    {
        // Default harus aman: .env yang lupa diisi tidak boleh membuat KTP/KK/ijazah menjadi publik.
        $this->assertStringContainsString(
            "env('APPLICANT_FILE_VISIBILITY', 'private')",
            file_get_contents(config_path('applicant.php'))
        );
    }

    public function test_private_mode_serves_files_only_through_a_short_lived_signed_url(): void
    {
        config(['applicant.file_visibility' => 'private']);

        $disk = $this->disk();
        $disk->shouldReceive('temporaryUrl')->once()
            ->withArgs(fn ($path, $expiry) => $path === 'Applicants/acme/AP1/ktp-x.webp'
                && $expiry->between(now()->addMinutes(9), now()->addMinutes(11)))
            ->andReturn('https://signed.test/x');
        $disk->shouldNotReceive('url');

        $this->assertSame('https://signed.test/x', (new StubDiskStore($disk))->url('Applicants/acme/AP1/ktp-x.webp'));
    }

    #[DataProvider('publicModes')]
    public function test_public_modes_use_the_direct_url(string $mode): void
    {
        config(['applicant.file_visibility' => $mode]);

        $disk = $this->disk();
        $disk->shouldReceive('url')->once()->with('a/b.webp')->andReturn('https://direct.test/a/b.webp');
        $disk->shouldNotReceive('temporaryUrl');

        $this->assertSame('https://direct.test/a/b.webp', (new StubDiskStore($disk))->url('a/b.webp'));
    }

    public static function publicModes(): array
    {
        return ['public' => ['public'], 'public-policy' => ['public-policy']];
    }

    #[DataProvider('putOptions')]
    public function test_acl_is_only_sent_in_public_mode(string $mode, array $expectedOptions): void
    {
        config(['applicant.file_visibility' => $mode]);

        $disk = $this->disk();
        $disk->shouldReceive('put')->once()
            ->with(
                Mockery::on(fn ($p) => str_starts_with($p, 'Applicants/acme/AP1/ktp-') && str_ends_with($p, '.webp')),
                'BINARY',
                $expectedOptions
            )
            ->andReturn(true);

        $path = (new StubDiskStore($disk))->put('acme', 'AP1', 'ktp', 'BINARY');

        $this->assertStringStartsWith('Applicants/acme/AP1/ktp-', $path);
    }

    public static function putOptions(): array
    {
        return [
            'private'       => ['private', []],
            'public-policy' => ['public-policy', []],
            'public'        => ['public', ['visibility' => 'public']],
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
