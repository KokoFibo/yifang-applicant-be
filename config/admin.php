<?php

return [
    // SHA-256 dari token API admin. Kosong = API admin nonaktif (semua permintaan ditolak).
    // Buat: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   -> token (simpan di Cloudflare)
    //       echo -n "TOKEN" | sha256sum                          -> isi ADMIN_API_TOKEN_HASH di .env
    'api_token_hash' => env('ADMIN_API_TOKEN_HASH'),
];
