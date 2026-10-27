<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Applicantfile;
use App\Support\ApplicantRules;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct(private CompanyContext $context)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user('applicant')->profilePayload()]);
    }

    /** Simpan satu bagian formulir (pribadi, kontak, identitas, pendidikan). */
    public function update(Request $request, string $section): JsonResponse
    {
        abort_unless(in_array($section, ApplicantRules::SECTIONS, true), 404);

        $user  = $request->user('applicant');
        $input = ApplicantRules::normalize($request->only(ApplicantRules::FIELDS[$section]));

        $data = Validator::make(
            $input,
            ApplicantRules::rules($section, $input),
            ApplicantRules::messages(),
            ApplicantRules::attributes()
        )->validate();

        $user->fill(Arr::only(ApplicantRules::adjust($data), ApplicantRules::FIELDS[$section]))->save();

        return response()->json(['user' => $user->fresh()->profilePayload()]);
    }

    /** Kirim pendaftaran: semua bagian dan dokumen wajib harus lengkap. */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user('applicant');

        $stored = Arr::only($user->attributesToArray(), ApplicantRules::allFields());
        $validator = Validator::make(
            $stored,
            ApplicantRules::allRules($stored),
            ApplicantRules::messages(),
            ApplicantRules::attributes()
        );
        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'submit' => ['Data diri belum lengkap. Periksa semua langkah: ' . implode(' ', $validator->errors()->all())],
            ]);
        }

        $uploaded = Applicantfile::where('id_karyawan', $user->applicant_id)->pluck('type')->unique()->all();
        $missing  = collect($this->context->documents())
            ->filter(fn ($d, $key) => $d['required'] && ! in_array($key, $uploaded, true))
            ->pluck('label')->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'submit' => ['Dokumen wajib belum lengkap: ' . $missing->implode(', ') . '.'],
            ]);
        }

        $user->forceFill(['status' => 1])->save();

        return response()->json([
            'message' => 'Data Anda sudah berhasil dikirim. Anda masih bisa mengubah data atau mengganti file kapan saja.',
            'user'    => $user->fresh()->profilePayload(),
        ]);
    }
}
