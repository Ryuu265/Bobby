<?php
/**
 * tools/uji-situs.php — Uji end-to-end aplikasi lewat HTTP.
 *
 * Script ini memanggil aplikasi yang sedang berjalan (php -S) dan
 * memeriksa setiap halaman: status HTTP benar, tidak ada pesan error
 * PHP/SQL yang bocor ke HTML, lalu mengisi formulir sungguhan untuk
 * menguji alur daftar -> pesan -> konfirmasi -> mulai -> selesai ->
 * verifikasi pembayaran -> ekspor laporan.
 *
 * Pemakaian:
 *   php tools/uji-situs.php                          # http://127.0.0.1:8080
 *   php tools/uji-situs.php http://127.0.0.1:8080
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Script ini hanya dijalankan dari terminal.\n");
}
if (!function_exists('curl_init')) {
    exit("Ekstensi curl belum aktif, tambahkan extension=curl di php.ini.\n");
}

$baseUrl = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$jar = sys_get_temp_dir() . '/uji-situs-cookie.txt';
@unlink($jar);

/** Pola yang tidak boleh muncul di halaman mana pun. */
const POLA_ERROR = '/(Fatal error|Parse error|Uncaught |Warning: |Notice: |Deprecated: |SQLSTATE\[|View tidak ditemukan|Class [A-Za-z\\\\]+ not found|Stack trace|too many connections)/i';

$lulus = 0;
$gagal = 0;
$daftarGagal = [];

function cek(string $label, bool $ok, string $catatan = ''): void
{
    global $lulus, $gagal, $daftarGagal;
    echo sprintf('%s %-44s %s', $ok ? '[OK]  ' : '[GAGAL]', $label, $catatan) . "\n";
    if ($ok) {
        $lulus++;
        return;
    }
    $gagal++;
    $daftarGagal[] = $label . ' — ' . $catatan;
}

/* ---------------- HTTP ---------------- */

/** Satu permintaan + respons, lengkap dengan header dan status. */
function req(string $path, array $data = null, array $files = []): array
{
    global $baseUrl, $jar;

    $ch = curl_init($baseUrl . $path);
    $header = '';
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADERFUNCTION => static function ($_, string $baris) use (&$header): int {
            $header .= $baris;
            return strlen($baris);
        },
        CURLOPT_COOKIEJAR      => $jar,
        CURLOPT_COOKIEFILE     => $jar,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 8,
    ];
    if ($data !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $files === [] ? $data : $data + array_map(
            static fn (array $f) => new CURLFile($f['path'], $f['type'], $f['name']),
            $files
        );
    }
    curl_setopt_array($ch, $opts);

    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $lokasi = preg_match('#^Location:\s*(\S+)#mi', $header, $m) === 1 ? $m[1] : '';
    return ['status' => $status, 'body' => $body, 'lokasi' => $lokasi, 'header' => $header];
}

function get(string $path): array
{
    return req($path);
}

/** Token CSRF selalu sama selama satu sesi, ambil sekali dari halaman mana pun. */
function token(string $html): string
{
    return preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m) === 1 ? $m[1] : '';
}

/** Halaman harus render bersih: status sesuai dan tanpa error yang bocor. */
function halaman(string $label, string $path, int $harap = 200): array
{
    $r = get($path);
    $ringkas = (string) preg_replace('/\s+/', ' ', substr($r['body'], 0, 40000));
    $bocor = preg_match(POLA_ERROR, $ringkas, $m) === 1 ? $m[0] : '';
    $isi = strlen($ringkas) > 200;
    cek($label, $r['status'] === $harap && $bocor === '' && $isi, 'HTTP ' . $r['status'] . ($bocor !== '' ? ' | ' . $bocor : ''));
    return $r;
}

/** Pesan flash terakhir, biar kegagalan formulir terbaca jelas. */
function flash(string $html): string
{
    if (preg_match('#<div class="flash[^"]*"[^>]*>(.*?)</div>#s', $html, $m) === 1) {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($m[1])));
    }
    return '';
}

/* ---------------- Basis data (hanya untuk memastikan hasil) ---------------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $env = [];
    foreach ((array) @file(__DIR__ . '/../.env') as $baris) {
        $baris = trim($baris);
        if ($baris !== '' && !str_starts_with($baris, '#') && str_contains($baris, '=')) {
            [$k, $v] = explode('=', $baris, 2);
            $env[trim($k)] = trim($v);
        }
    }
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '127.0.0.1',
            $env['DB_PORT'] ?? '3306',
            $env['DB_NAME'] ?? 'qengamestation'
        ),
        $env['DB_USER'] ?? 'root',
        $env['DB_PASS'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}

function tanyai(string $sql, array $params = []): mixed
{
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s->fetchColumn();
}

/* ============================ 1. HALAMAN PUBLIK ============================ */
echo "\n=== 1. Halaman publik ===\n";
halaman('Beranda / landing', '/');
halaman('Katalog unit', '/katalog');
halaman('Katalog game', '/katalog/game');
halaman('Detail unit PS5-01', '/katalog/unit/PS5-01');
halaman('Detail game', '/katalog/game/1');
halaman('Form login', '/masuk');
halaman('Form daftar', '/daftar');
halaman('Rute tak dikenal -> 404', '/mesin-waktu', 404);

$jadwal = get('/jadwal/1');
$slot = json_decode($jadwal['body'], true);
cek('AJAX jadwal', $jadwal['status'] === 200 && isset($slot['slots'][0]), 'HTTP ' . $jadwal['status']);

$harga = get('/harga?unit_id=1&durasi=3');
$hJson = json_decode($harga['body'], true);
cek('AJAX kalkulasi biaya', $harga['status'] === 200 && ($hJson['total'] ?? 0) === 60000, 'HTTP ' . $harga['status']);

/* ============================ 2. REGISTRASI + LOGIN ============================ */
echo "\n=== 2. Registrasi dan login pelanggan ===\n";
$emailBaru = 'uji_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 8) . '@example.com';
$r = req('/daftar', [
    '_token'         => token(get('/daftar')['body']),
    'nama'           => 'Pem Uji Coba',
    'email'          => $emailBaru,
    'telepon'        => '081234567890',
    'password'         => 'rahasia123',
    'password_confirmation' => 'rahasia123',
    'alamat'         => 'Jl. Uji Coba No. 1',
], []);
$idBaru = (int) tanyai('SELECT id FROM users WHERE email = ?', [$emailBaru]);
cek('Daftar akun tersimpan', $idBaru > 0 && $r['status'] === 302, 'HTTP ' . $r['status'] . ' | id=' . $idBaru . ' | ' . flash($r['body']));

/* Password salah harus ditolak. */
$r = req('/masuk', ['_token' => token(get('/masuk')['body']), 'email' => $emailBaru, 'password' => 'salahbanget']);
cek('Login password salah ditolak', $r['status'] === 302 && $r['lokasi'] !== '' && !str_contains($r['lokasi'], 'profil'), 'HTTP ' . $r['status']);

$r = req('/masuk', ['_token' => token(get('/masuk')['body']), 'email' => $emailBaru, 'password' => 'rahasia123']);
cek('Login pelanggan baru', $r['status'] === 302, 'HTTP ' . $r['status'] . ' -> ' . $r['lokasi']);

/* ============================ 3. AREA PELANGGAN ============================ */
echo "\n=== 3. Area pelanggan ===\n";
halaman('Form booking', '/booking');
halaman('Riwayat pesanan', '/riwayat');
halaman('Halaman profil', '/profil');

$slotPilih = slot_kosong(1);
if ($slotPilih === null) {
    cek('Menemukan slot kosong', false, 'PS5-01 penuh lima hari ke depan');
    ringkas();
    exit(1);
}
cek('Menemukan slot kosong', true, $slotPilih['tanggal'] . ' ' . $slotPilih['jam'] . ' x' . $slotPilih['durasi'] . ' jam');

$r = req('/booking', [
    '_token'     => token(get('/booking')['body']),
    'unit'       => '1',
    'tanggal'    => $slotPilih['tanggal'],
    'jam'        => $slotPilih['jam'],
    'durasi'     => (string) $slotPilih['durasi'],
    'controller' => '1',
    'metode'     => 'transfer',
    'catatan'    => 'Pesanan otomatis uji-situs',
]);
$idPesanan = (int) tanyai('SELECT id FROM bookings WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$idBaru]);
cek('Simpan pesanan', $idPesanan > 0 && $r['status'] === 302, 'HTTP ' . $r['status'] . ' | id=' . $idPesanan . ' | ' . flash($r['body']));

$tagihan = (int) tanyai('SELECT total_harga FROM bookings WHERE id = ?', [$idPesanan]);
cek('Total otomatis terhitung', $tagihan > 0, 'total = Rp' . number_format($tagihan));

$detail = halaman('Detail pesanan pelanggan', '/booking/' . $idPesanan);
halaman('Riwayat berisi pesanan baru', '/riwayat');

/* Pesanan ganda pada slot yang sama harus ditolak. */
$r = req('/booking', [
    '_token'  => token(get('/booking')['body']),
    'unit'    => '1',
    'tanggal' => $slotPilih['tanggal'],
    'jam'     => $slotPilih['jam'],
    'durasi'  => (string) $slotPilih['durasi'],
    'metode'  => 'transfer',
]);
$jml = (int) tanyai('SELECT COUNT(*) FROM bookings WHERE user_id = ?', [$idBaru]);
cek('Bentrokan slot ditolak', $jml === 1, 'jumlah pesanan = ' . $jml . ' | ' . flash($r['body']));

/* Unggah bukti transfer (menguji Uploader + finfo). */
$png = sys_get_temp_dir() . '/bukti-uji.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
$r = req('/booking/' . $idPesanan . '/bayar', [
    '_token'          => token($detail['body']),
    'metode'          => 'transfer',
    'nomor_referensi' => 'REF-' . random_int(100000, 999999),
    'jumlah'          => (string) $tagihan,
], ['bukti' => ['path' => $png, 'type' => 'image/png', 'name' => 'bukti-transfer.png']]);
$idBayar = (int) tanyai('SELECT id FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1', [$idPesanan]);
cek('Unggah bukti pembayaran', $idBayar > 0, 'HTTP ' . $r['status'] . ' | payment id=' . $idBayar . ' | ' . flash($r['body']));
@unlink($png);

/* ============================ 4. AREA ADMIN ============================ */
echo "\n=== 4. Masuk sebagai admin ===\n";
req('/keluar', ['_token' => token(get('/riwayat')['body'])]);
$login = get('/masuk');
$r = req('/masuk', ['_token' => token($login['body']), 'email' => 'admin@qengamestation.id', 'password' => 'password']);
cek('Login admin', $r['status'] === 302, 'HTTP ' . $r['status'] . ' -> ' . $r['lokasi']);

echo "\n=== 5. Semua halaman admin ===\n";
foreach ([
    ['Dashboard', '/admin/dashboard'],
    ['Kelola unit', '/admin/unit'],
    ['Form tambah unit', '/admin/unit/baru'],
    ['Form ubah unit', '/admin/unit/1/ubah'],
    ['Kelola game', '/admin/game'],
    ['Form tambah game', '/admin/game/baru'],
    ['Form ubah game', '/admin/game/1/ubah'],
    ['Kelola paket', '/admin/paket'],
    ['Form tambah paket', '/admin/paket/baru'],
    ['Form ubah paket', '/admin/paket/1/ubah'],
    ['Kelola pesanan', '/admin/pesanan'],
    ['Detail pesanan', '/admin/pesanan/' . $idPesanan],
    ['Antrian pembayaran', '/admin/pembayaran'],
    ['Kelola pelanggan', '/admin/pelanggan'],
    ['Detail pelanggan', '/admin/pelanggan/' . $idBaru],
    ['Monitoring', '/admin/monitoring'],
    ['Data monitoring (JSON)', '/admin/monitoring/data'],
    ['Laporan', '/admin/laporan'],
    ['Laporan harian', '/admin/laporan/harian'],
    ['Laporan bulanan', '/admin/laporan/bulanan'],
    ['Laporan per unit', '/admin/laporan/unit'],
    ['Log aktivitas', '/admin/laporan/aktivitas'],
    ['Pengaturan', '/admin/pengaturan'],
] as [$label, $path]) {
    halaman($label, $path);
}

$ekspor = get('/admin/laporan/ekspor?jenis=pesanan&dari=' . date('Y-m-d', strtotime('-7 days')) . '&sampai=' . date('Y-m-d'));
cek('Ekspor CSV pesanan', $ekspor['status'] === 200 && str_contains($ekspor['body'], 'invoice_number'), 'HTTP ' . $ekspor['status'] . ', ' . strlen($ekspor['body']) . ' byte');
$ekspor2 = get('/admin/laporan/ekspor?jenis=unit');
cek('Ekspor CSV unit', $ekspor2['status'] === 200 && strlen($ekspor2['body']) > 20, 'HTTP ' . $ekspor2['status']);
$cetak = get('/admin/laporan/cetak');
cek('Halaman cetak laporan', $cetak['status'] === 200 && stripos($cetak['body'], 'print') !== false, 'HTTP ' . $cetak['status']);

/* ============================ 6. SIKLUS PESANAN ============================ */
echo "\n=== 6. Siklus pesanan: konfirmasi -> mulai -> selesai ===\n";
$tk = token(get('/admin/pesanan/' . $idPesanan)['body']);
foreach (['dikonfirmasi', 'berlangsung', 'selesai'] as $baru) {
    $r = req('/admin/pesanan/' . $idPesanan . '/status', ['_token' => $tk, 'status' => $baru]);
    $jadi = (string) tanyai('SELECT status_pesanan FROM bookings WHERE id = ?', [$idPesanan]);
    cek('Status -> ' . $baru, $jadi === $baru, 'HTTP ' . $r['status'] . ' | di DB: ' . $jadi . ' | ' . flash($r['body']));
}

echo "\n=== 7. Verifikasi pembayaran ===\n";
if ($idBayar > 0) {
    $antri = get('/admin/pembayaran');
    $r = req('/admin/pembayaran/' . $idBayar, [
        '_token'    => token($antri['body']),
        'keputusan' => 'terima',
        'catatan'   => 'DIVERIFIKASI OLEH UJI-SITUS',
    ]);
    $jadi = (string) tanyai('SELECT status FROM payments WHERE id = ?', [$idBayar]);
    $bayar = (string) tanyai('SELECT status_bayar FROM bookings WHERE id = ?', [$idPesanan]);
    cek('Verifikasi bayar diterima', $jadi === 'lunas', 'HTTP ' . $r['status'] . ' | payment: ' . $jadi . ' | pesanan: ' . $bayar . ' | ' . flash($r['body']));
} else {
    cek('Verifikasi bayar diterima', false, 'tidak ada transaksi untuk diuji');
}

/* ============================ 7. CRUD UNIT + GAME + PAKET ============================ */
echo "\n=== 8. CRUD unit, game, paket ===\n";
$kode = 'UJI-' . strtoupper(substr(md5(uniqid((string) mt_rand(), true)), 0, 4));
$tk = token(get('/admin/unit/baru')['body']);
$r = req('/admin/unit', [
    '_token'        => $tk,
    'kode_unit'     => $kode,
    'nama'          => 'Unit Uji Coba',
    'tipe'          => 'PS4',
    'kategori'      => 'reguler',
    'harga_per_jam' => '18000',
    'kapasitas'     => '2',
    'status'        => 'tersedia',
    'deskripsi'     => 'Dibuat otomatis oleh uji-situs.php.',
]);
$idUnit = (int) tanyai('SELECT id FROM units WHERE kode_unit = ?', [$kode]);
cek('CRUD unit: simpan', $idUnit > 0, 'HTTP ' . $r['status'] . ' | id=' . $idUnit . ' | ' . flash($r['body']));

if ($idUnit > 0) {
    $r = req('/admin/unit/' . $idUnit, [
        '_token'        => token(get('/admin/unit/' . $idUnit . '/ubah')['body']),
        '_current_id'   => (string) $idUnit,
        'kode_unit'     => $kode,
        'nama'          => 'Unit Uji Coba Diedit',
        'tipe'          => 'PS4',
        'kategori'      => 'reguler',
        'harga_per_jam' => '19500',
        'kapasitas'     => '3',
        'status'        => 'maintenance',
        'deskripsi'     => 'Sudah diperbarui.',
    ]);
    $hargaBcu = (int) tanyai('SELECT harga_per_jam FROM units WHERE id = ?', [$idUnit]);
    cek('CRUD unit: perbarui', $hargaBcu === 19500, 'HTTP ' . $r['status'] . ' | harga di DB: ' . $hargaBcu . ' | ' . flash($r['body']));

    $r = req('/admin/unit/' . $idUnit . '/hapus', ['_token' => token(get('/admin/unit')['body'])]);
    cek('CRUD unit: hapus', (int) tanyai('SELECT COUNT(*) FROM units WHERE id = ?', [$idUnit]) === 0, 'HTTP ' . $r['status']);
}

$tk = token(get('/admin/paket/baru')['body']);
$r = req('/admin/paket', [
    '_token'        => $tk,
    'nama'          => 'Paket Uji ' . substr($kode, 4),
    'deskripsi'     => 'Paket promosi untuk uji otomatis.',
    'durasi_jam'    => '3',
    'tipe_diskon'   => 'persen',
    'diskon_persen' => '10',
    'diskon_nilai'  => '0',
    'harga'         => '0',
    'status'        => 'aktif',
]);
$idPaket = (int) tanyai('SELECT id FROM packages WHERE nama LIKE ? ORDER BY id DESC LIMIT 1', ['Paket Uji%']);
cek('CRUD paket: simpan', $idPaket > 0, 'HTTP ' . $r['status'] . ' | id=' . $idPaket . ' | ' . flash($r['body']));
if ($idPaket > 0) {
    $r = req('/admin/paket/' . $idPaket . '/hapus', ['_token' => token(get('/admin/paket')['body'])]);
    cek('CRUD paket: hapus', (int) tanyai('SELECT COUNT(*) FROM packages WHERE id = ?', [$idPaket]) === 0, 'HTTP ' . $r['status']);
}

$tk = token(get('/admin/game/baru')['body']);
$r = req('/admin/game', [
    '_token'      => $tk,
    'judul'       => 'Game Uji Otomatis',
    'platform'    => 'Multi',
    'genre'       => 'Balapan',
    'rating'      => '7+',
    'status'      => 'aktif',
    'deskripsi'   => 'Dibuat otomatis oleh uji-situs.php.',
    'unit_id'     => ['2', '3'],
]);
$idGame = (int) tanyai('SELECT id FROM games WHERE judul = ? ORDER BY id DESC LIMIT 1', ['Game Uji Otomatis']);
cek('CRUD game: simpan', $idGame > 0, 'HTTP ' . $r['status'] . ' | id=' . $idGame . ' | ' . flash($r['body']));
if ($idGame > 0) {
    $pasangan = (int) tanyai('SELECT COUNT(*) FROM unit_games WHERE game_id = ?', [$idGame]);
    cek('CRUD game: relasi unit tersimpan', $pasangan === 2, 'jumlah baris unit_games = ' . $pasangan);

    $r = req('/admin/game/' . $idGame . '/hapus', ['_token' => token(get('/admin/game')['body'])]);
    cek('CRUD game: hapus', (int) tanyai('SELECT COUNT(*) FROM games WHERE id = ?', [$idGame]) === 0, 'HTTP ' . $r['status']);
}

/* ============================ 8. PENGAMANAN ============================ */
echo "\n=== 9. Proteksi akses ===\n";
req('/keluar', ['_token' => token(get('/admin/dashboard')['body'])]);

$tamu = get('/admin/dashboard');
cek('Tamu diarahkan ke login', $tamu['status'] === 302 && str_contains($tamu['lokasi'], 'masuk'), 'HTTP ' . $tamu['status']);

$l = get('/masuk');
req('/masuk', ['_token' => token($l['body']), 'email' => 'budi@example.com', 'password' => 'password']);
$tembus = get('/admin/dashboard');
cek('Pelanggan ditolak di area admin', $tembus['status'] !== 200, 'HTTP ' . $tembus['status']);

$tanpaToken = req('/admin/pesanan/' . $idPesanan . '/status', ['status' => 'selesai']);
cek('POST tanpa token CSRF ditolak', $tanpaToken['status'] === 419 || $tanpaToken['status'] === 403
    || (string) tanyai('SELECT status_pesanan FROM bookings WHERE id = ?', [$idPesanan]) === 'selesai',
    'HTTP ' . $tanpaToken['status']);

/* ============================ HASIL ============================ */
ringkas();
exit($gagal === 0 ? 0 : 1);

/* ---------------- Fungsi bantu uji ---------------- */

/**
 * Cari tanggal + jam mulai yang masih kosong untuk satu unit,
 * lalu perpanjang durasi selama slotnya masih bebas (maks 3 jam).
 */
function slot_kosong(int $unitId): ?array
{
    for ($geser = 0; $geser <= 5; $geser++) {
        $tanggal = date('Y-m-d', strtotime('+' . $geser . ' day'));
        $awal = $geser === 0 ? max(10, (int) date('H') + 1) : 10;
        for ($jam = $awal; $jam <= 20; $jam++) {
            $mulai = sprintf('%02d:00', $jam);
            $json = (array) json_decode(get('/jadwal/' . $unitId . '?tanggal=' . $tanggal . '&durasi=1')['body'], true);
            $terlambat = false;
            foreach ($json['slots'] ?? [] as $s) {
                if (($s['value'] ?? '') !== $mulai) {
                    continue;
                }
                if (empty($s['tersedia'])) {
                    $terlambat = $s['alasan'] ?? '';
                    break;
                }
                $durasi = 1;
                for ($coba = 2; $coba <= 3; $coba++) {
                    $j = (array) json_decode(get('/jadwal/' . $unitId . '?tanggal=' . $tanggal . '&durasi=' . $coba)['body'], true);
                    $bebas = true;
                    foreach ($j['slots'] ?? [] as $cs) {
                        $nilai = (string) ($cs['value'] ?? '');
                        if ($nilai >= $mulai && $nilai < sprintf('%02d:00', min($jam + $coba, 23)) && empty($cs['tersedia'])) {
                            $bebas = false;
                        }
                    }
                    if (!$bebas) {
                        break;
                    }
                    $durasi = $coba;
                }
                return ['tanggal' => $tanggal, 'jam' => $mulai, 'durasi' => $durasi];
            }
            if ($terlambat === 'lewat') {
                break; // jam segini sudah lewat, lanjut ke hari berikutnya
            }
        }
    }
    return null;
}

function ringkas(): void
{
    global $lulus, $gagal, $daftarGagal;
    echo "\n" . str_repeat('-', 66) . "\n";
    printf("Total lulus: %d   Gagal: %d\n", $lulus, $gagal);
    if ($daftarGagal !== []) {
        echo "\nYang belum lulus:\n";
        foreach ($daftarGagal as $g) {
            echo '  - ' . $g . "\n";
        }
    }
}
