# Portal Calon Karyawan (multi-perusahaan)

Satu aplikasi untuk banyak perusahaan dan domain. Nama, logo, warna, dan daftar dokumen tiap
perusahaan disimpan di **database**, jadi menambah perusahaan tidak perlu mengubah kode.

```
frontend/   Vue 3 + Vite + Pinia + Vue Router + Tailwind  (satu build untuk semua domain)
backend/    File untuk proyek Laravel BARU (disarankan Laravel 12), terpisah dari aplikasi lama
```

## Cara kerja
1. Browser membuka domain (mis. `sti-applicant.yifang.co.id`). Frontend memanggil `/api/tenant`.
2. Laravel mencocokkan domain dengan tabel `company_domains` lalu mengembalikan nama, logo, dan warna.
   Domain yang tidak terdaftar mendapat halaman "alamat ini belum terdaftar".
3. Semua data (pelamar, file, email) dibatasi pada perusahaan itu. Email yang sama boleh mendaftar di perusahaan berbeda.
4. Login berupa cookie sesi milik masing-masing domain (tidak berlaku lintas domain).
5. Alur pelamar: register -> email konfirmasi (Gmail SMTP) -> login -> isi data -> unggah dokumen
   (pilih file / foto) -> tinjau -> kirim. Gambar: kompres ringan di browser, lalu di server dikoreksi
   orientasinya, metadata dihapus, diperkecil maks 1200 px, WebP kualitas 75, disimpan ke S3.
   Pelamar bisa mengubah data dan mengganti/menghapus file miliknya kapan saja.

## A. Siapkan backend (localhost)

0. Cek versi PHP di VPS: `php -v`. Laravel 12 butuh PHP 8.2 atau lebih baru.
   Pilih **proyek polos (tanpa starter kit)**: jangan pakai Breeze, Jetstream, Livewire, Inertia, atau kit React/Vue,
   karena semuanya menambah login, rute, dan tabel sendiri yang bentrok dengan kode di sini.
   Jangan pakai Laravel 10: dukungan perbaikan keamanannya sudah berakhir (Februari 2025).
1. Buat proyek baru (terpisah dari aplikasi lama). Gunakan `composer create-project`, bukan `laravel new`,
   karena `laravel new` menanyakan starter kit:
   ```
   composer create-project laravel/laravel:^12.0 portal-pelamar
   cd portal-pelamar
   composer require intervention/image "league/flysystem-aws-s3-v3:^3.0"
   ```
2. Salin isi folder `backend/` ke proyek tersebut (struktur sama). Ini **menimpa**:
   `config/auth.php`, `app/Providers/AppServiceProvider.php`, `routes/web.php`.
3. Hapus migration bawaan Laravel (users, cache, jobs, dst.) agar tidak membuat tabel yang tidak dipakai
   di database bersama:
   ```
   rm database/migrations/0001_01_01_*        # Laravel 11/12/13
   # (Laravel 10: rm database/migrations/2014_* database/migrations/2019_*)
   ```
   Laravel Sanctum tidak dipakai.
4. Sesuaikan `.env` dengan `.env.additions.example`. Ubah baris yang sudah ada (jangan membuat dobel), terutama
   `SESSION_DRIVER=file` dan `CACHE_STORE=file` (di Laravel 11 ke atas bawaannya `database`, yang butuh tabel
   yang sudah kita hapus). Gunakan **database yang sama** dengan
   aplikasi lama. Buat **App Password** Gmail untuk `yifangindonesia@gmail.com` dan isi di `MAIL_PASSWORD`
   (jangan dikirim ke chat atau di-commit).
5. **Cadangkan database**, lalu:
   ```
   php artisan key:generate
   php artisan storage:link
   php artisan migrate
   php artisan db:seed --class=CompanySeeder
   php artisan company:list
   php artisan company:adopt payroll      # masukkan pelamar lama ke perusahaan yang benar (lihat catatan)
   php artisan serve                      # http://127.0.0.1:8000
   ```
   `migrate` hanya menambah kolom dan melonggarkan NOT NULL ke NULL pada tabel lama, tidak menghapus data.
   Tabel `migrations` dipakai bersama aplikasi lama. Itu aman, tetapi jangan jalankan `migrate:fresh/rollback`.
6. PHP: `memory_limit` minimal 256M, `upload_max_filesize=12M`, `post_max_size=14M`, ekstensi `gd` dengan WebP.

### Data awal (seed) yang saya buat dan PERLU Anda periksa
| Kode | Nama (placeholder) | Domain |
|---|---|---|
| payroll | Yifang Payroll | `payrolll-applicant.yifang.co.id` (tiga huruf "l", sesuai yang Anda tulis. Perbaiki jika salah ketik) |
| salary | Yifang Salary | `salary-applicant.yifang.co.id` |
| sti | Yifang STI | `sti-applicant.yifang.co.id` |
| bai | Yifang BAI | `bai-applicant.yifang.co.id` |

Nama, warna, dan logo di atas hanya sementara. Ganti dengan data sebenarnya (lihat bagian C).
Di luar produksi seeder juga mendaftarkan `payroll.localhost`, `salary.localhost`, `sti.localhost`,
`bai.localhost`, dan `localhost` (-> payroll) untuk uji di komputer Anda.

**Pelamar lama** (data di tabel lama) belum punya perusahaan dan tidak bisa login sampai dimasukkan ke
satu perusahaan lewat `company:adopt <kode>`. Pilih perusahaan yang benar. Mereka login dengan "Lupa password".

## B. Jalankan frontend
```
cd frontend
npm install
npm run dev          # http://localhost:5173
```
Buka `http://localhost:5173` (perusahaan payroll) atau `http://sti.localhost:5173`, `http://bai.localhost:5173`, dst.
untuk menguji perusahaan lain. Vite meneruskan `/api` dan `/storage` ke Laravel dan menjaga header Host asli,
jadi Laravel tahu domain mana yang dibuka. Uji tampilan per perusahaan dengan
`php artisan company:update sti --name="..." --color="#0F766E"` lalu muat ulang halaman.

## C. Mengelola perusahaan (tanpa ubah kode)

```
php artisan company:add "PT Contoh Baru" hr.contoh.co.id --code=contoh --color="#0F766E" --logo=/path/logo.png
php artisan company:update sti --name="PT STI" --color="#1D4ED8" --logo=/path/logo-sti.png --from-name="HRD PT STI"
php artisan company:domain sti tambahan.sti.co.id          # tambah domain (--remove untuk menghapus)
php artisan company:docs bai --off=skck --off=bri --required=npwp --max=cv=3   # dokumen per perusahaan
php artisan company:list
```
Pilih warna utama yang cukup gelap supaya teks putih pada tombol tetap terbaca.
Daftar key dokumen: ktp, kk, ijazah, nilai, cv, pasfoto, npwp, sertifikat, paklaring, bpjs, skck, bri.
Data bisa juga diedit langsung di tabel `companies` (kolom `settings` berisi JSON untuk dokumen).

**Menambah perusahaan baru = 3 langkah:** (1) arahkan DNS domain ke IP VPS, (2) `company:add ...`,
(3) HTTPS: otomatis dengan Caddy, atau satu perintah certbot jika memakai Nginx.

## D. Deploy di VPS

### Opsi 1 (disarankan): Caddy, HTTPS otomatis untuk domain yang terdaftar di database
Caddy hanya menerbitkan sertifikat jika Laravel menjawab bahwa domain itu terdaftar (`/internal/tls-ask`).
Contoh `Caddyfile` (sesuaikan path dan versi PHP, dan periksa dokumentasi Caddy versi Anda):
```
{
	on_demand_tls {
		ask http://127.0.0.1:9123/internal/tls-ask
	}
}

# Pemeriksa domain untuk Caddy (hanya bisa dari komputer ini)
http://127.0.0.1:9123 {
	bind 127.0.0.1
	root * /var/www/portal/backend/public
	php_fastcgi unix//run/php/php8.2-fpm.sock
}

http:// {
	redir https://{host}{uri} permanent
}

https:// {
	tls {
		on_demand
	}
	encode gzip
	header {
		X-Content-Type-Options nosniff
		X-Frame-Options DENY
		Referrer-Policy strict-origin-when-cross-origin
		Strict-Transport-Security "max-age=31536000"
	}
	request_body {
		max_size 12MB
	}

	handle /api/* {
		root * /var/www/portal/backend/public
		php_fastcgi unix//run/php/php8.2-fpm.sock
	}
	handle /storage/* {
		root * /var/www/portal/backend/public
		file_server
	}
	handle {
		root * /var/www/portal/frontend
		try_files {path} /index.html
		file_server
	}
}
```

### Opsi 2: Nginx + certbot
Satu blok `server` dengan semua domain di `server_name`. Domain baru = tambah di `server_name`, lalu
`certbot --nginx --expand -d domain-lama ... -d domain-baru`.
```nginx
server {
    listen 443 ssl http2;
    server_name payrolll-applicant.yifang.co.id salary-applicant.yifang.co.id sti-applicant.yifang.co.id bai-applicant.yifang.co.id;
    # ssl_certificate ... (dari certbot)
    client_max_body_size 12m;
    add_header X-Content-Type-Options nosniff always;
    add_header X-Frame-Options DENY always;
    add_header Referrer-Policy strict-origin-when-cross-origin always;
    add_header Strict-Transport-Security "max-age=31536000" always;

    root /var/www/portal/frontend;
    location / { try_files $uri /index.html; }

    location ^~ /storage/ { alias /var/www/portal/backend/public/storage/; }
    location ~ ^/api/ {
        root /var/www/portal/backend/public;
        try_files /index.php =404;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/portal/backend/public/index.php;
        fastcgi_param REQUEST_URI $request_uri;
    }
}
```
(`/internal/tls-ask` hanya dibutuhkan Caddy. Pada Nginx tidak perlu diekspos.)

### Langkah umum
1. Build frontend: `npm run build`, salin `dist/` ke `/var/www/portal/frontend`.
2. `.env` produksi: `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`; lalu
   `php artisan config:cache route:cache`.
3. Jika ada proxy/Cloudflare di depan, atur trusted proxy agar HTTPS dikenali (link di email memakai skema dan
   domain dari request). Laravel 11+: di `bootstrap/app.php`, `->withMiddleware(fn ($m) => $m->trustProxies(at: '*'))`.
   Laravel 10: `app/Http/Middleware/TrustProxies.php`.
4. Buat user MySQL terbatas dengan `backend/mysql-limited-user.sql`. MySQL hanya `bind-address=127.0.0.1`.
5. Firewall (ufw): hanya 22, 80, 443.

## E. Keamanan
Sudah diterapkan: password di-hash (bcrypt), password plain text lama ditolak; verifikasi email (tautan HMAC, 24 jam);
reset password dengan token ter-hash sekali pakai (60 menit); cookie sesi HttpOnly + CSRF, sesi diganti saat login;
sesi satu domain tidak berlaku di domain lain; seluruh query dibatasi per perusahaan; pembatasan laju login/register/
kirim ulang/reset; respons yang tidak membocorkan email terdaftar; validasi input di server; validasi isi file
(bukan hanya ekstensi), batas ukuran dan resolusi; metadata/GPS dihapus; nama file acak; akses file dicek pemiliknya;
`applicant_id` baru acak; sertifikat HTTPS hanya untuk domain yang terdaftar (Caddy).

Perlu perhatian:
- **S3 public (sementara).** KTP, KK, ijazah bisa dibuka siapa pun yang punya URL (nama file acak hanya mempersulit tebakan).
  Sebelum dipakai publik: `APPLICANT_FILE_VISIBILITY=private` dan bucket tidak publik. Aplikasi lalu memakai presigned URL 10 menit.
- **Livewire lama** (`/applicant`) menyimpan password plain text. Matikan setelah portal baru berjalan.
- Batas Gmail sekitar 500 email/hari untuk semua perusahaan. Jika volume besar, pakai layanan email transaksional.
- Logo di email tidak disertakan (hanya nama perusahaan), karena memakai template email bawaan Laravel.
- Halaman admin yang menampilkan data pelamar harus memakai `{{ }}` (escape) di Blade agar aman dari XSS.
