<?php

namespace Tests\Feature;

use App\Models\Applicantdata;
use App\Models\Applicantfile;
use App\Models\Company;
use App\Services\ApplicantFileStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenants;
use Tests\Support\FakeFileStore;
use Tests\TestCase;

/**
 * Janji utama sistem multi-tenant: data satu perusahaan tidak pernah terlihat atau bisa diubah
 * dari domain perusahaan lain. Jika tes di sini gagal, jangan tambah perusahaan baru sebelum diperbaiki.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private Company $alpha;
    private Company $beta;
    private FakeFileStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = $this->makeCompany('alpha', 'alpha.test');
        $this->beta  = $this->makeCompany('beta', 'beta.test', ['primary_color' => '#445566']);

        $this->store = new FakeFileStore();
        $this->app->instance(ApplicantFileStore::class, $this->store);
    }

    // ---------- Penentuan perusahaan dari domain ----------

    public function test_unknown_domain_is_rejected(): void
    {
        $this->getJson('http://tidak-terdaftar.test/api/tenant')
            ->assertStatus(404)
            ->assertJsonPath('code', 'company_not_found');
    }

    public function test_inactive_company_is_rejected(): void
    {
        $this->beta->update(['is_active' => false]);

        $this->getJson('http://beta.test/api/tenant')->assertStatus(404);
        $this->getJson('http://alpha.test/api/tenant')->assertOk();
    }

    public function test_each_domain_shows_only_its_own_branding(): void
    {
        $this->getJson('http://alpha.test/api/tenant')
            ->assertOk()
            ->assertJsonPath('company.code', 'alpha')
            ->assertJsonPath('company.name', 'ALPHA Corp')
            ->assertJsonPath('company.primary_color', '#112233');

        $this->getJson('http://beta.test/api/tenant')
            ->assertOk()
            ->assertJsonPath('company.code', 'beta')
            ->assertJsonPath('company.primary_color', '#445566');
    }

    // ---------- Akun ----------

    public function test_same_email_can_register_in_two_companies_but_not_twice_in_one(): void
    {
        Notification::fake();
        $payload = ['email' => 'siti@mail.test', 'password' => 'Password1', 'password_confirmation' => 'Password1'];

        $this->postJson('http://alpha.test/api/register', $payload)->assertStatus(201);
        $this->postJson('http://beta.test/api/register', $payload)->assertStatus(201);

        $this->assertSame(
            [$this->alpha->id, $this->beta->id],
            Applicantdata::where('email', 'siti@mail.test')->orderBy('company_id')->pluck('company_id')->all()
        );

        $this->postJson('http://alpha.test/api/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_only_works_in_the_company_that_owns_the_account(): void
    {
        $this->makeApplicant($this->beta, 'rina@mail.test');
        $credentials = ['email' => 'rina@mail.test', 'password' => 'Password1'];

        $this->postJson('http://alpha.test/api/login', $credentials)
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson('http://beta.test/api/login', $credentials)
            ->assertOk()
            ->assertJsonPath('user.email', 'rina@mail.test');
    }

    public function test_session_from_one_company_is_rejected_on_another_domain(): void
    {
        $user = $this->makeApplicant($this->alpha);

        $this->actingAs($user, 'applicant')->getJson('http://alpha.test/api/profile')->assertOk();

        $this->actingAs($user, 'applicant')->getJson('http://beta.test/api/profile')->assertStatus(401);
    }

    public function test_email_verification_link_is_scoped_to_its_company(): void
    {
        $user    = $this->makeApplicant($this->beta, 'dewi@mail.test', verified: false);
        $hash    = sha1($user->email);
        $expires = now()->addHour()->timestamp;
        $payload = [
            'id'        => $user->id,
            'hash'      => $hash,
            'expires'   => $expires,
            'signature' => Applicantdata::signVerification($user->id, $hash, $expires),
        ];

        // Link milik pelamar beta, dibuka lewat domain alpha: harus ditolak dan tidak mengubah apa pun.
        $this->postJson('http://alpha.test/api/email/verify', $payload)->assertStatus(422);
        $this->assertNull($user->fresh()->email_verified_at);

        $this->postJson('http://beta.test/api/email/verify', $payload)->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_password_reset_token_cannot_be_used_across_companies(): void
    {
        // Email yang sama di dua perusahaan, tetapi hanya pelamar alpha yang meminta reset.
        $inAlpha = $this->makeApplicant($this->alpha, 'andi@mail.test', password: 'OldPass123');
        $inBeta  = $this->makeApplicant($this->beta, 'andi@mail.test', password: 'OldPass123');

        $token = str_repeat('a', 64);
        DB::table('applicant_password_resets')->insert([
            'applicant_data_id' => $inAlpha->id,
            'token_hash'        => hash('sha256', $token),
            'created_at'        => now(),
        ]);

        $payload = [
            'token' => $token, 'email' => 'andi@mail.test',
            'password' => 'NewPass123', 'password_confirmation' => 'NewPass123',
        ];

        $this->postJson('http://beta.test/api/reset-password', $payload)->assertStatus(422);
        $this->assertTrue(Hash::check('OldPass123', $inBeta->fresh()->password));
        $this->assertTrue(Hash::check('OldPass123', $inAlpha->fresh()->password));

        $this->postJson('http://alpha.test/api/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('NewPass123', $inAlpha->fresh()->password));
        $this->assertTrue(Hash::check('OldPass123', $inBeta->fresh()->password));
    }

    // ---------- Dokumen ----------

    public function test_document_list_contains_only_the_owners_files(): void
    {
        $me        = $this->makeApplicant($this->alpha, 'saya@mail.test');
        $colleague = $this->makeApplicant($this->alpha, 'rekan@mail.test');
        $outsider  = $this->makeApplicant($this->beta, 'luar@mail.test');

        $mine   = $this->makeFile($me);
        $others = [$this->makeFile($colleague), $this->makeFile($outsider)];

        $response = $this->actingAs($me, 'applicant')->getJson('http://alpha.test/api/documents')->assertOk();

        $response->assertJsonCount(1, 'files');
        $response->assertJsonPath('files.0.id', $mine->id);
        foreach ($others as $file) {
            $this->assertStringNotContainsString($file->filename, $response->getContent());
        }
    }

    public function test_cannot_replace_or_delete_a_file_owned_by_someone_else(): void
    {
        $me        = $this->makeApplicant($this->alpha, 'saya@mail.test');
        $colleague = $this->makeApplicant($this->alpha, 'rekan@mail.test');
        $outsider  = $this->makeApplicant($this->beta, 'luar@mail.test');

        foreach ([$this->makeFile($colleague), $this->makeFile($outsider)] as $foreign) {
            $this->actingAs($me, 'applicant')
                ->deleteJson("http://alpha.test/api/documents/{$foreign->id}")
                ->assertStatus(404);

            $this->actingAs($me, 'applicant')
                ->postJson("http://alpha.test/api/documents/{$foreign->id}/replace")
                ->assertStatus(404);

            $this->assertNotNull(Applicantfile::find($foreign->id), 'File milik orang lain tidak boleh terhapus.');
        }

        $this->assertSame([], $this->store->deleted, 'Tidak boleh ada objek yang dihapus dari penyimpanan.');
    }

    public function test_owner_can_delete_their_own_file(): void
    {
        $me   = $this->makeApplicant($this->alpha);
        $file = $this->makeFile($me);

        $this->actingAs($me, 'applicant')
            ->deleteJson("http://alpha.test/api/documents/{$file->id}")
            ->assertOk();

        $this->assertNull(Applicantfile::find($file->id));
        $this->assertSame([$file->filename], $this->store->deleted);
    }

    public function test_documents_disabled_for_a_company_are_hidden_from_its_applicants(): void
    {
        $this->alpha->update(['settings' => ['documents' => ['skck' => ['enabled' => false]]]]);

        $inAlpha = $this->makeApplicant($this->alpha, 'a@mail.test');
        $inBeta  = $this->makeApplicant($this->beta, 'b@mail.test');
        $this->makeFile($inAlpha, 'ktp');
        $this->makeFile($inAlpha, 'skck');
        $this->makeFile($inBeta, 'skck');

        $this->actingAs($inAlpha, 'applicant')->getJson('http://alpha.test/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.type', 'ktp');

        $this->actingAs($inBeta, 'applicant')->getJson('http://beta.test/api/documents')
            ->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonPath('files.0.type', 'skck');
    }
}
