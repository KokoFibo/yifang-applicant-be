<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class ApplicantRules
{
    public const SECTIONS = ['pribadi', 'kontak', 'identitas', 'pendidikan'];

    public const FIELDS = [
        'pribadi'    => ['nama', 'hp', 'telp', 'tempat_lahir', 'tgl_lahir', 'gender', 'status_pernikahan', 'golongan_darah', 'agama', 'etnis', 'ptkp'],
        'kontak'     => ['nama_contact_darurat', 'contact_darurat_1', 'hubungan_1', 'nama_contact_darurat_2', 'contact_darurat_2', 'hubungan_2'],
        'identitas'  => ['jenis_identitas', 'no_identitas', 'alamat_identitas', 'alamat_tinggal_sekarang'],
        'pendidikan' => ['pendidikan', 'jurusan', 'nama_kampus'],
    ];

    public static function allFields(): array
    {
        return array_merge(...array_values(self::FIELDS));
    }

    /** Nilai yang valid untuk sebuah dropdown (dari config/applicant.php) */
    public static function values(string $key): array
    {
        $options = config("applicant.options.$key", []);

        return array_is_list($options) ? $options : array_keys($options);
    }

    public static function rules(string $section, array $input = []): array
    {
        $phone = ['required', 'string', 'regex:/^\+?[0-9 \-]{8,20}$/'];
        $name  = ['required', 'string', 'min:2', 'max:100'];

        $all = [
            'pribadi' => [
                'nama'              => $name,
                'hp'                => $phone,
                'telp'              => $phone,
                'tempat_lahir'      => ['required', 'string', 'max:100'],
                'tgl_lahir'         => ['required', 'date_format:Y-m-d', 'before:-15 years', 'after:1930-01-01'],
                'gender'            => ['required', Rule::in(self::values('gender'))],
                'status_pernikahan' => ['required', Rule::in(self::values('status_pernikahan'))],
                'golongan_darah'    => ['required', Rule::in(self::values('golongan_darah'))],
                'agama'             => ['required', Rule::in(self::values('agama'))],
                'etnis'             => ['required', Rule::in(self::values('etnis'))],
                'ptkp'              => ['required', Rule::in(self::values('ptkp'))],
            ],
            'kontak' => [
                'nama_contact_darurat'   => $name,
                'contact_darurat_1'      => $phone,
                'hubungan_1'             => ['required', 'string', 'max:50'],
                'nama_contact_darurat_2' => $name,
                'contact_darurat_2'      => $phone,
                'hubungan_2'             => ['required', 'string', 'max:50'],
            ],
            'identitas' => [
                'jenis_identitas'         => ['required', Rule::in(self::values('jenis_identitas'))],
                'no_identitas'            => ($input['jenis_identitas'] ?? null) === 'KTP'
                    ? ['required', 'digits:16']
                    : ['required', 'regex:/^[A-Za-z0-9]{6,12}$/'],
                'alamat_identitas'        => ['required', 'string', 'min:5', 'max:255'],
                'alamat_tinggal_sekarang' => ['required', 'string', 'min:5', 'max:255'],
            ],
            'pendidikan' => [
                'pendidikan'  => ['required', Rule::in(self::values('pendidikan'))],
                'jurusan'     => ['nullable', 'string', 'max:100'],
                'nama_kampus' => ['nullable', 'string', 'max:100'],
            ],
        ];

        return $all[$section];
    }

    public static function allRules(array $input = []): array
    {
        return array_merge(...array_map(fn ($s) => self::rules($s, $input), self::SECTIONS));
    }

    /** Jurusan dan sekolah/kampus hanya relevan untuk pendidikan SMA/SMK ke atas. */
    public static function hasSchoolDetail(?string $pendidikan): bool
    {
        $list = self::values('pendidikan');
        $from = array_search(config('applicant.school_detail_from'), $list, true);
        $idx  = array_search($pendidikan, $list, true);

        return $from !== false && $idx !== false && $idx >= $from;
    }

    /** Aturan bisnis yang dipaksakan di server (tidak bergantung pada frontend). */
    public static function adjust(array $data): array
    {
        // Pelamar perempuan: PTKP selalu TK/0
        if (($data['gender'] ?? null) === 'Perempuan') {
            $data['ptkp'] = 'TK0';
        }
        // Pendidikan di bawah SMA/SMK: jurusan dan sekolah/kampus dikosongkan
        if (array_key_exists('pendidikan', $data) && ! self::hasSchoolDetail($data['pendidikan'])) {
            $data['jurusan']     = null;
            $data['nama_kampus'] = null;
        }

        return $data;
    }

    /** Ubah input mentah menjadi data bersih siap simpan. */
    public static function normalize(array $data): array
    {
        $titleCase = ['nama', 'tempat_lahir', 'nama_contact_darurat', 'nama_contact_darurat_2', 'hubungan_1', 'hubungan_2', 'jurusan', 'nama_kampus'];
        foreach ($data as $key => $value) {
            if (! is_string($value)) {
                continue;
            }
            $data[$key] = in_array($key, $titleCase, true) ? Text::title($value) : Text::clean($value);
        }

        return $data;
    }

    public static function messages(): array
    {
        return [
            'required'     => ':attribute wajib diisi.',
            'string'       => ':attribute tidak valid.',
            'min.string'   => ':attribute minimal :min karakter.',
            'max.string'   => ':attribute maksimal :max karakter.',
            'email'        => 'Format email tidak valid.',
            'unique'       => 'Email ini sudah terdaftar. Jika Anda pernah mendaftar sebelumnya, gunakan "Lupa password".',
            'confirmed'    => 'Konfirmasi password tidak sama.',
            'regex'        => 'Format :attribute tidak valid.',
            'digits'       => ':attribute harus :digits digit angka.',
            'in'           => ':attribute tidak valid.',
            'date_format'  => 'Format :attribute tidak valid.',
            'before'       => ':attribute tidak valid. Usia minimal 15 tahun.',
            'after'        => ':attribute tidak valid.',
            'image'        => ':attribute harus berupa gambar (JPG, PNG, atau WebP).',
            'mimes'        => ':attribute harus berupa gambar JPG, PNG, atau WebP.',
            'max.file'     => 'Ukuran :attribute maksimal :max KB.',
            'uploaded'     => 'Upload gagal. Periksa koneksi lalu coba lagi.',
            'password.min' => 'Password minimal :min karakter.',
        ];
    }

    public static function attributes(): array
    {
        return [
            'email' => 'Email', 'password' => 'Password', 'nama' => 'Nama lengkap', 'hp' => 'No. HP',
            'telp' => 'No. telepon', 'tempat_lahir' => 'Tempat lahir', 'tgl_lahir' => 'Tanggal lahir',
            'gender' => 'Jenis kelamin', 'status_pernikahan' => 'Status pernikahan',
            'golongan_darah' => 'Golongan darah', 'agama' => 'Agama', 'etnis' => 'Etnis', 'ptkp' => 'PTKP',
            'nama_contact_darurat' => 'Nama kontak darurat 1', 'contact_darurat_1' => 'No. kontak darurat 1',
            'hubungan_1' => 'Hubungan kontak 1', 'nama_contact_darurat_2' => 'Nama kontak darurat 2',
            'contact_darurat_2' => 'No. kontak darurat 2', 'hubungan_2' => 'Hubungan kontak 2',
            'jenis_identitas' => 'Jenis identitas', 'no_identitas' => 'No. identitas',
            'alamat_identitas' => 'Alamat sesuai identitas', 'alamat_tinggal_sekarang' => 'Alamat tinggal sekarang',
            'pendidikan' => 'Pendidikan terakhir', 'jurusan' => 'Jurusan', 'nama_kampus' => 'Nama sekolah/kampus',
            'file' => 'File', 'type' => 'Jenis dokumen',
        ];
    }
}
