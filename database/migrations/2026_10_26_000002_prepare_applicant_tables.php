<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menyesuaikan tabel applicantdatas / applicantfiles milik aplikasi lama.
 * HANYA menambah kolom dan melonggarkan NOT NULL -> NULL. Tidak ada data yang dihapus. MySQL.
 */
return new class extends Migration
{
    /** Kolom varchar(255) yang sebelumnya NOT NULL. Saat register hanya email + password yang terisi. */
    private array $varchar255 = [
        'nama', 'hp', 'tempat_lahir', 'gender', 'status_pernikahan', 'golongan_darah',
        'agama', 'etnis', 'nama_contact_darurat', 'nama_contact_darurat_2',
        'contact_darurat_1', 'contact_darurat_2', 'hubungan_1', 'hubungan_2',
        'jenis_identitas', 'no_identitas', 'alamat_identitas', 'alamat_tinggal_sekarang',
    ];

    public function up(): void
    {
        // Database baru/kosong (mis. komputer lokal): buat dulu tabel sesuai struktur aplikasi lama.
        // Di database yang sudah punya tabelnya (aplikasi lama), bagian ini dilewati.
        if (! Schema::hasTable('applicantdatas')) {
            Schema::create('applicantdatas', function (Blueprint $t) {
                $t->id();
                $t->string('applicant_id');
                $t->string('email');
                $t->string('password');
                foreach (['nama', 'hp', 'telp', 'tempat_lahir'] as $c) {
                    $t->string($c)->nullable();
                }
                $t->date('tgl_lahir')->nullable();
                foreach (['gender', 'status_pernikahan', 'golongan_darah', 'agama', 'etnis',
                          'nama_contact_darurat', 'nama_contact_darurat_2', 'contact_darurat_1', 'contact_darurat_2',
                          'hubungan_1', 'hubungan_2', 'jenis_identitas', 'no_identitas',
                          'alamat_identitas', 'alamat_tinggal_sekarang'] as $c) {
                    $t->string($c)->nullable();
                }
                $t->string('ptkp', 100)->nullable();
                $t->integer('status')->default(0);
                $t->timestamps();
                foreach (['pendidikan', 'jurusan', 'nama_kampus'] as $c) {
                    $t->string($c, 100)->nullable();
                }
            });
        }
        if (! Schema::hasTable('applicantfiles')) {
            Schema::create('applicantfiles', function (Blueprint $t) {
                $t->id();
                $t->string('id_karyawan');
                $t->string('originalName');
                $t->string('filename');
                $t->timestamps();
            });
        }

        foreach ($this->varchar255 as $col) {
            DB::statement("ALTER TABLE `applicantdatas` MODIFY `{$col}` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL");
        }
        DB::statement('ALTER TABLE `applicantdatas` MODIFY `tgl_lahir` DATE NULL');
        // 0 = baru register / belum submit, 1 = data sudah disubmit
        DB::statement('ALTER TABLE `applicantdatas` MODIFY `status` INT NOT NULL DEFAULT 0');

        Schema::table('applicantdatas', function (Blueprint $table) {
            if (! Schema::hasColumn('applicantdatas', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('id')->index();
            }
            if (! Schema::hasColumn('applicantdatas', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (! Schema::hasColumn('applicantdatas', 'remember_token')) {
                $table->rememberToken();
            }
        });

        // Email unik PER PERUSAHAAN (email yang sama boleh mendaftar di perusahaan berbeda).
        // Baris lama masih company_id NULL (NULL tidak dianggap sama di indeks unik).
        try {
            Schema::table('applicantdatas', fn (Blueprint $t) => $t->dropUnique('applicantdatas_email_unique'));
        } catch (\Throwable $e) {
            // indeks itu tidak ada: tidak apa-apa
        }
        try {
            Schema::table('applicantdatas', fn (Blueprint $t) => $t->unique(['company_id', 'email'], 'applicantdatas_company_email_unique'));
        } catch (\Throwable $e) {
            // sudah ada
        }

        Schema::table('applicantfiles', function (Blueprint $table) {
            if (! Schema::hasColumn('applicantfiles', 'type')) {
                $table->string('type', 30)->nullable()->after('id_karyawan')->index();
            }
        });

        // Isi kolom type untuk file lama dari nama file, mis. "ktp-01.webp", "kartu_keluarga-02.webp"
        $valid = array_keys(config('applicant.documents', []));
        DB::table('applicantfiles')->whereNull('type')->orderBy('id')->chunkById(200, function ($rows) use ($valid) {
            foreach ($rows as $row) {
                if (preg_match('/^(.+)-\d+\.[A-Za-z0-9]+$/', basename($row->filename), $m)) {
                    $key = strtolower($m[1]) === 'kartu_keluarga' ? 'kk' : strtolower($m[1]);
                    if (in_array($key, $valid, true)) {
                        DB::table('applicantfiles')->where('id', $row->id)->update(['type' => $key]);
                    }
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('applicantfiles', function (Blueprint $table) {
            if (Schema::hasColumn('applicantfiles', 'type')) {
                $table->dropColumn('type');
            }
        });
        Schema::table('applicantdatas', function (Blueprint $table) {
            foreach (['company_id', 'email_verified_at'] as $col) {
                if (Schema::hasColumn('applicantdatas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        // Kolom NOT NULL lama sengaja tidak dikembalikan agar data tidak rusak.
    }
};
