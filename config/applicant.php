<?php

return [

    // Disk penyimpanan file (lihat config/filesystems.php) dan cara file dibuka:
    // 'public'        : objek diberi ACL public-read, dibuka lewat URL langsung. Bucket harus mengizinkan ACL.
    // 'public-policy' : tanpa ACL, dibuka lewat URL langsung. Akses baca publik diatur lewat bucket policy.
    // 'private'       : tanpa ACL, hanya bisa dibuka lewat presigned URL 10 menit (disarankan untuk produksi).
    'file_disk'       => env('APPLICANT_FILE_DISK', 's3'),
    'file_visibility' => env('APPLICANT_FILE_VISIBILITY', 'public'),

    // Masa berlaku link konfirmasi email (menit)
    'verification_ttl_minutes' => 60 * 24,

    // Pengolahan gambar sebelum disimpan ke S3
    'image' => [
        'max_width'     => 1200,
        'max_height'    => 3000,
        'quality'       => 75,          // kualitas WebP
        'max_upload_kb' => 10240,       // 10 MB per file (sebelum dikompres)
        'max_pixels'    => 40000000,    // 40 MP, melindungi memori server
    ],

    // Daftar dokumen STANDAR. Tiap perusahaan bisa mengubahnya (aktif/nonaktif, wajib/opsional)
    // lewat `php artisan company:docs`, tanpa mengubah kode.
    // Key dipakai di database (kolom applicantfiles.type)
    'documents' => [
        'ktp'        => ['label' => 'KTP / Passport',          'required' => true,  'max_files' => 2, 'hint' => 'Foto KTP atau halaman data paspor. Pastikan semua tulisan terbaca.'],
        'kk'         => ['label' => 'Kartu Keluarga',          'required' => true,  'max_files' => 2, 'hint' => 'Satu halaman penuh kartu keluarga.'],
        'ijazah'     => ['label' => 'Ijazah',                  'required' => true,  'max_files' => 3, 'hint' => 'Ijazah pendidikan terakhir.'],
        'nilai'      => ['label' => 'Transkrip Nilai / SKHUN', 'required' => true,  'max_files' => 3, 'hint' => 'Transkrip nilai atau SKHUN pendidikan terakhir.'],
        'cv'         => ['label' => 'CV',                      'required' => true,  'max_files' => 5, 'hint' => 'Foto atau tangkapan layar CV. Satu halaman per file.'],
        'pasfoto'    => ['label' => 'Pas Foto',                'required' => true,  'max_files' => 1, 'hint' => 'Foto formal, wajah jelas, latar polos.'],
        'npwp'       => ['label' => 'NPWP',                    'required' => false, 'max_files' => 1, 'hint' => 'Jika ada.'],
        'sertifikat' => ['label' => 'Sertifikat',              'required' => false, 'max_files' => 5, 'hint' => 'Sertifikat pelatihan atau keahlian, jika ada.'],
        'paklaring'  => ['label' => 'Paklaring',               'required' => false, 'max_files' => 3, 'hint' => 'Surat keterangan kerja, bagi yang pernah bekerja.'],
        'bpjs'       => ['label' => 'BPJS Ketenagakerjaan',    'required' => false, 'max_files' => 2, 'hint' => 'Kartu atau saldo BPJS Ketenagakerjaan yang belum diklaim.'],
        'skck'       => ['label' => 'SKCK',                    'required' => false, 'max_files' => 1, 'hint' => 'Jika sudah memilikinya.'],
        'bri'        => ['label' => 'Buku Tabungan BRI',       'required' => false, 'max_files' => 1, 'hint' => 'Halaman depan buku tabungan rekening BRI.'],
    ],

    // Jurusan dan nama sekolah/kampus hanya diminta mulai dari tingkat pendidikan ini (urutan mengikuti options.pendidikan)
    'school_detail_from' => 'SMA/SMK',

    // Pilihan dropdown (sumber tunggal untuk validasi server dan tampilan frontend)
    'options' => [
        'gender'            => ['Laki-laki', 'Perempuan'],
        'status_pernikahan' => ['Belum Kawin', 'Kawin', 'Cerai Hidup', 'Cerai Mati'],
        'golongan_darah'    => ['O', 'A', 'B', 'AB'],
        'agama'             => ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Budha', 'Konghucu'],
        'etnis'             => ['Batak', 'Jawa', 'Sunda', 'Lampung', 'Palembang', 'Tionghoa', 'Lainnya'],
        'ptkp'              => [
            'TK0' => 'TK/0', 'TK1' => 'TK/1', 'TK2' => 'TK/2', 'TK3' => 'TK/3',
            'K0'  => 'K/0',  'K1'  => 'K/1',  'K2'  => 'K/2',  'K3'  => 'K/3',
        ],
        'pendidikan'        => ['Tidak Bersekolah', 'SD', 'SMP', 'SMA/SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'],
        'jenis_identitas'   => ['KTP', 'Passport'],
    ],
];
