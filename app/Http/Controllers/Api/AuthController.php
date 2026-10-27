<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicantdata;
use App\Notifications\ResetApplicantPassword;
use App\Support\ApplicantRules;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private CompanyContext $context)
    {
    }

    private function companyId(): int
    {
        return $this->context->get()->id;
    }

    /** Semua pencarian pelamar dibatasi pada perusahaan dari domain ini. */
    private function applicants()
    {
        return Applicantdata::where('company_id', $this->companyId());
    }

    private function normalizeEmail(Request $request): void
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
    }

    private function newApplicantId(): string
    {
        do {
            $id = 'AP' . strtoupper(Str::random(12));
        } while (Applicantdata::where('applicant_id', $id)->exists());

        return $id;
    }

    private function passwordRule(): PasswordRule
    {
        return PasswordRule::min(8)->letters()->numbers();
    }

    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $data = $request->validate([
            'email'    => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('applicantdatas', 'email')->where('company_id', $this->companyId()),
            ],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ], ApplicantRules::messages(), ApplicantRules::attributes());

        $user = Applicantdata::create([
            'company_id'   => $this->companyId(),
            'applicant_id' => $this->newApplicantId(),
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
        ]);

        $this->sendVerification($user);

        return response()->json([
            'message' => 'Pendaftaran berhasil. Kami mengirim email konfirmasi. Klik tautan di email tersebut sebelum login.',
        ], 201);
    }

    private function sendVerification(Applicantdata $user): void
    {
        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            // Akun tetap dibuat. Pengguna dapat meminta kirim ulang.
            report($e);
        }
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string', 'max:255'],
        ], ApplicantRules::messages(), ApplicantRules::attributes());

        $key = 'applicant-login:' . sha1($this->companyId() . '|' . $request->input('email') . '|' . $request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = $this->applicants()->where('email', $request->input('email'))->first();

        // Password lama (plain text) tidak pernah diterima: hanya hash bcrypt yang valid.
        $valid = $user
            && str_starts_with((string) $user->password, '$2y$')
            && Hash::check($request->input('password'), $user->password);

        if (! $valid) {
            if (! $user) {
                Hash::make($request->input('password')); // samakan waktu respons
            }
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages(['email' => ['Email atau password salah.']]);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email Anda belum dikonfirmasi. Periksa kotak masuk (dan folder spam), atau kirim ulang email konfirmasi.',
                'code'    => 'email_not_verified',
            ], 403);
        }

        RateLimiter::clear($key);
        Auth::guard('applicant')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->profilePayload()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('applicant')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Anda sudah keluar.']);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'        => ['required', 'integer'],
            'hash'      => ['required', 'string', 'size:40'],
            'expires'   => ['required', 'integer'],
            'signature' => ['required', 'string', 'size:64'],
        ]);

        $user = $this->applicants()->find($data['id']);
        $invalid = ! $user
            || (int) $data['expires'] < time()
            || ! hash_equals(sha1($user->getEmailForVerification()), $data['hash'])
            || ! hash_equals(Applicantdata::signVerification($user->getKey(), $data['hash'], (int) $data['expires']), $data['signature']);

        if ($invalid) {
            return response()->json([
                'message' => 'Tautan konfirmasi tidak valid atau sudah kedaluwarsa. Minta email konfirmasi baru.',
            ], 422);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json(['message' => 'Email berhasil dikonfirmasi. Silakan login.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $request->validate(['email' => ['required', 'email']], ApplicantRules::messages(), ApplicantRules::attributes());

        $user = $this->applicants()->where('email', $request->input('email'))->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $this->sendVerification($user);
        }

        // Respons sama apa pun hasilnya, agar email yang terdaftar tidak bisa ditebak.
        return response()->json(['message' => 'Jika email terdaftar dan belum dikonfirmasi, email konfirmasi baru sudah dikirim.'], 202);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $request->validate(['email' => ['required', 'email']], ApplicantRules::messages(), ApplicantRules::attributes());

        $user = $this->applicants()->where('email', $request->input('email'))->first();

        if ($user) {
            $existing = DB::table('applicant_password_resets')->where('applicant_data_id', $user->id)->first();
            $tooSoon  = $existing && Carbon::parse($existing->created_at)->gt(now()->subSeconds(60));

            if (! $tooSoon) {
                $token = Str::random(64);
                DB::table('applicant_password_resets')->updateOrInsert(
                    ['applicant_data_id' => $user->id],
                    ['token_hash' => hash('sha256', $token), 'created_at' => now()]
                );
                try {
                    $user->notify(new ResetApplicantPassword($token));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return response()->json(['message' => 'Jika email terdaftar, tautan untuk mengatur ulang password sudah dikirim.'], 202);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $data = $request->validate([
            'token'    => ['required', 'string', 'size:64'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', $this->passwordRule()],
        ], ApplicantRules::messages(), ApplicantRules::attributes());

        $user = $this->applicants()->where('email', $data['email'])->first();
        $row  = $user ? DB::table('applicant_password_resets')->where('applicant_data_id', $user->id)->first() : null;

        $valid = $row
            && Carbon::parse($row->created_at)->gt(now()->subMinutes(ResetApplicantPassword::EXPIRE_MINUTES))
            && hash_equals($row->token_hash, hash('sha256', $data['token']));

        if (! $valid) {
            throw ValidationException::withMessages(['email' => ['Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru.']]);
        }

        $user->forceFill([
            'password'       => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ]);
        // Pemilik email terbukti dari link reset, jadi email dianggap terverifikasi.
        if (! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }
        $user->save();

        DB::table('applicant_password_resets')->where('applicant_data_id', $user->id)->delete();

        return response()->json(['message' => 'Password berhasil diubah. Silakan login.']);
    }
}
