<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pengaman: tes memakai RefreshDatabase yang MENGHAPUS semua tabel. Tolak berjalan kecuali database-nya
     * SQLite in-memory, supaya database MySQL lokal (atau server) tidak pernah ikut terhapus oleh `php artisan test`.
     * (Di dalam setUp, refreshApplication() berjalan sebelum RefreshDatabase menjalankan migrate:fresh.)
     */
    protected function refreshApplication()
    {
        parent::refreshApplication();

        $default = config('database.default');
        $db      = config("database.connections.{$default}.database");

        if ($default !== 'sqlite' || $db !== ':memory:') {
            throw new RuntimeException(
                "Tes dihentikan: koneksi database tes adalah \"{$default}\" ({$db}), bukan SQLite :memory:. " .
                'Tes akan menghapus semua tabel. Periksa DB_CONNECTION/DB_DATABASE di phpunit.xml dan variabel environment shell Anda.'
            );
        }
    }
}
