<?php
/**
 * Uploader — menyimpan berkas unggahan dengan aman.
 * Proteksi: cek tipe MIME asli (finfo), ekstensi whitelist, ubah nama jadi
 * acak, dan folder uploads diblokir dari eksekusi PHP lewat .htaccess.
 */

namespace App\Core;

use RuntimeException;

class Uploader
{
    /**
     * @param string $folder subfolder uploads, mis. 'units' | 'games' | 'payments'
     * @return string|null   path relatif (mis. 'units/ps5-01_a1b2c3.jpg') atau null
     */
    public static function store(array|null $file, string $folder, array $allowed = null, int $maxKb = 0): ?string
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Berkas gagal diunggah. Coba lagi atau pilih berkas lain.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Unggahan tidak valid.');
        }

        $maxKb = $maxKb ?: (int) config('upload.max_size_kb', 2048);
        if ($file['size'] > $maxKb * 1024) {
            throw new RuntimeException('Ukuran berkas melebihi ' . number_format($maxKb) . ' KB.');
        }

        $allowed = $allowed ?? config('upload.image_types', ['jpg', 'png']);

        // Tipe MIME dibaca dari ISI berkas, bukan dari nama yang dikirim browser
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $mimeBoleh = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf',
        ];
        if (!isset($mimeBoleh[$mime])) {
            throw new RuntimeException('Tipe berkas tidak dikenali (' . $mime . ').');
        }
        $ekstensi = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($ekstensi === 'jpeg') {
            $ekstensi = 'jpg'; // samakan penulisan, JPEG dan JPG berkas yang sama
        }
        if (!in_array($ekstensi, $allowed, true) || $mimeBoleh[$mime] !== $ekstensi) {
            throw new RuntimeException('Ekstensi berkas harus: ' . implode(', ', $allowed) . '.');
        }

        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?: 'umum';
        $dir = APP_ROOT . '/public/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Folder unggahan tidak dapat dibuat.');
        }

        // Nama file acak: tidak menebak, tidak menimpa, bebas karakter pengguna
        $nama = bin2hex(random_bytes(8)) . '.' . $mimeBoleh[$mime];
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nama)) {
            throw new RuntimeException('Berkas tidak dapat disimpan.');
        }
        @chmod($dir . '/' . $nama, 0644);

        return $folder . '/' . $nama;
    }

    /** Hapus berkas lama secara aman (hanya di dalam folder uploads). */
    public static function delete(?string $relative): void
    {
        if (!$relative) {
            return;
        }
        $path = realpath(APP_ROOT . '/public/uploads/' . ltrim($relative, '/'));
        $root = realpath(APP_ROOT . '/public/uploads');
        if ($path !== false && $root !== false && str_starts_with($path, $root) && is_file($path)) {
            @unlink($path);
        }
    }
}
