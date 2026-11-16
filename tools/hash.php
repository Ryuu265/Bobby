<?php
/**
 * tools/hash.php — Pembuat hash bcrypt untuk akun di database/seed.sql.
 *
 * Pemakaian:
 *     php tools/hash.php "password_baru_anda"
 *
 * Output dapat langsung ditempel ke kolom password pada database/seed.sql
 * atau ke statement UPDATE berikut:
 *     UPDATE users SET password = '<hash>' WHERE email = 'admin@qengamestation.id';
 *
 * Biaya (cost) 10 sengaja disamakan dengan App\Core\Auth::attempt() supaya
 * hash tidak di-upgrade otomatis saat login pertama.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya dijalankan dari terminal (php tools/hash.php "password").' . PHP_EOL);
}

$password = $argv[1] ?? '';
if (mb_strlen($password) < 8) {
    echo 'Password minimal 8 karakter.' . PHP_EOL;
    echo 'Contoh: php tools/hash.php "RahasiaKuat123"' . PHP_EOL;
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
if ($hash === false || !password_verify($password, $hash)) {
    echo 'Gagal membuat hash bcrypt. Periksa instalasi PHP (minimal PHP 8.1).' . PHP_EOL;
    exit(1);
}

echo 'Password : ' . $password . PHP_EOL;
echo 'Hash     : ' . $hash . PHP_EOL;
echo 'Verifikasi: OK' . PHP_EOL;
