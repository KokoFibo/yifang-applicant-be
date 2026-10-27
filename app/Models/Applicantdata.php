<?php

namespace App\Models;

use App\Notifications\ResetApplicantPassword;
use App\Notifications\VerifyApplicantEmail;
use App\Support\ApplicantRules;
use App\Support\CompanyContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Applicantdata extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    protected $table = 'applicantdatas';

    protected $fillable = [
        'company_id', 'applicant_id', 'email', 'password',
        'nama', 'hp', 'telp', 'tempat_lahir', 'tgl_lahir', 'gender', 'status_pernikahan',
        'golongan_darah', 'agama', 'etnis', 'ptkp',
        'nama_contact_darurat', 'contact_darurat_1', 'hubungan_1',
        'nama_contact_darurat_2', 'contact_darurat_2', 'hubungan_2',
        'jenis_identitas', 'no_identitas', 'alamat_identitas', 'alamat_tinggal_sekarang',
        'pendidikan', 'jurusan', 'nama_kampus',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'tgl_lahir'         => 'date:Y-m-d',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function files()
    {
        return $this->hasMany(Applicantfile::class, 'id_karyawan', 'applicant_id');
    }

    /** Data profil (tanpa password) untuk dikirim ke frontend. */
    public function profilePayload(): array
    {
        $attributes = $this->attributesToArray();

        return array_merge(
            ['email' => $this->email, 'status' => (int) $this->status, 'email_verified' => $this->hasVerifiedEmail()],
            array_intersect_key($attributes, array_flip(ApplicantRules::allFields()))
        );
    }

    // ---------- Email verifikasi (link mengarah ke frontend) ----------

    public static function signVerification(int|string $id, string $hash, int $expires): string
    {
        return hash_hmac('sha256', "{$id}|{$hash}|{$expires}", (string) config('app.key'));
    }

    public function frontendVerificationUrl(): string
    {
        $expires = now()->addMinutes((int) config('applicant.verification_ttl_minutes'))->timestamp;
        $hash    = sha1($this->getEmailForVerification());

        return app(CompanyContext::class)->baseUrl() . '/verify-email?' . http_build_query([
            'id'        => $this->getKey(),
            'hash'      => $hash,
            'expires'   => $expires,
            'signature' => self::signVerification($this->getKey(), $hash, $expires),
        ]);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyApplicantEmail());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetApplicantPassword($token));
    }
}
