<?php

namespace Tests\Concerns;

use App\Models\Applicantdata;
use App\Models\Applicantfile;
use App\Models\Company;
use App\Models\CompanyDomain;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesTenants
{
    protected function makeCompany(string $code, string $domain, array $attrs = []): Company
    {
        $company = Company::create(array_merge([
            'code'          => $code,
            'name'          => strtoupper($code) . ' Corp',
            'primary_color' => '#112233',
            'is_active'     => true,
        ], $attrs));

        CompanyDomain::create(['company_id' => $company->id, 'domain' => $domain]);

        return $company;
    }

    protected function makeApplicant(Company $company, string $email = 'budi@mail.test', bool $verified = true, string $password = 'Password1'): Applicantdata
    {
        $applicant = Applicantdata::create([
            'company_id'   => $company->id,
            'applicant_id' => 'AP' . strtoupper(Str::random(12)),
            'email'        => $email,
            'password'     => Hash::make($password),
        ]);

        if ($verified) {
            $applicant->forceFill(['email_verified_at' => now()])->save();
        }

        return $applicant;
    }

    protected function makeFile(Applicantdata $applicant, string $type = 'ktp'): Applicantfile
    {
        return Applicantfile::create([
            'id_karyawan'  => $applicant->applicant_id,
            'type'         => $type,
            'originalName' => "{$type}.jpg",
            'filename'     => "Applicants/{$applicant->company->code}/{$applicant->applicant_id}/{$type}-" . Str::lower(Str::random(20)) . '.webp',
        ]);
    }
}
