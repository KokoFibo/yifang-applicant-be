-- Jalankan sebagai admin MySQL. Ganti NAMA_DB dan password.
-- User ini dipakai portal pelamar saat berjalan (bukan untuk migrate).
-- Jika aplikasi diretas, tabel lain di database tidak ikut terbuka.

CREATE USER IF NOT EXISTS 'portal_pelamar'@'127.0.0.1' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
CREATE USER IF NOT EXISTS 'portal_pelamar'@'localhost'  IDENTIFIED BY 'GANTI_PASSWORD_KUAT';

GRANT SELECT, INSERT, UPDATE         ON `NAMA_DB`.`applicantdatas`            TO 'portal_pelamar'@'127.0.0.1', 'portal_pelamar'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `NAMA_DB`.`applicantfiles`            TO 'portal_pelamar'@'127.0.0.1', 'portal_pelamar'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `NAMA_DB`.`applicant_password_resets` TO 'portal_pelamar'@'127.0.0.1', 'portal_pelamar'@'localhost';
-- companies & company_domains hanya diubah lewat perintah artisan (company:add, dst.)
GRANT SELECT, INSERT, UPDATE, DELETE ON `NAMA_DB`.`companies`                 TO 'portal_pelamar'@'127.0.0.1', 'portal_pelamar'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON `NAMA_DB`.`company_domains`           TO 'portal_pelamar'@'127.0.0.1', 'portal_pelamar'@'localhost';

FLUSH PRIVILEGES;
-- Untuk `php artisan migrate` (CREATE/ALTER) pakai user admin sementara, lalu kembali ke user di atas.
