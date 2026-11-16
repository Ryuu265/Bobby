<?php
/**
 * Payment — pencatatan & verifikasi pembayaran.
 * Satu pesanan dapat memiliki lebih dari satu percobaan pembayaran
 * (mis. bukti pertama ditolak lalu diunggah ulang).
 */

namespace App\Models;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Model;
use RuntimeException;

class Payment extends Model
{
    protected string $table = 'payments';
    protected array $fillable = [
        'booking_id', 'user_id', 'metode', 'jumlah', 'nomor_referensi',
        'bukti_path', 'status', 'catatan_admin', 'verifikasi_oleh', 'verifikasi_pada',
    ];

    /** Simpan pembayaran (atau percobaan ulang) lalu sinkronkan status pesanan. */
    public static function catat(array $input): array
    {
        $db = Database::instance();
        $booking = (new Booking())->find((int) $input['booking_id']);
        if ($booking === null) {
            throw new RuntimeException('Pesanan tidak ditemukan.');
        }
        if ($booking['status_pesanan'] === 'dibatalkan') {
            throw new RuntimeException('Pesanan yang dibatalkan tidak dapat dibayar.');
        }

        $jumlah = round((float) ($input['jumlah'] ?? $booking['total_harga']));
        if ($jumlah <= 0) {
            throw new RuntimeException('Nominal pembayaran tidak valid.');
        }

        // Pembayaran di tempat dicatat sebagai menunggu verifikasi kasir,
        // transfer dengan bukti dicatat menunggu verifikasi admin.
        $status = 'menunggu_verifikasi';
        $id = $db->insert('payments', [
            'booking_id'      => (int) $booking['id'],
            'user_id'         => (int) $booking['user_id'],
            'metode'          => $input['metode'] ?? $booking['metode_bayar'],
            'jumlah'          => $jumlah,
            'nomor_referensi' => $input['nomor_referensi'] ?? null,
            'bukti_path'      => $input['bukti_path'] ?? null,
            'status'          => $status,
        ]);

        $db->update('bookings', (int) $booking['id'], ['status_bayar' => $status]);
        Logger::write((int) $booking['user_id'], 'payment', 'buat', 'payments', $id,
            'Pembayaran ' . rupiah($jumlah) . ' untuk ' . $booking['invoice_number'] . ' (' . $status . ')');

        return (new self())->find($id) ?? [];
    }

    /** Pembayaran terakhir sebuah pesanan. */
    public static function terakhir(int $bookingId): ?array
    {
        return Database::instance()->one(
            'SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC, id DESC LIMIT 1',
            [$bookingId]
        );
    }

    public static function semuaUntuk(int $bookingId): array
    {
        return Database::instance()->all(
            'SELECT p.*, u.nama AS nama_verifikator FROM payments p
              LEFT JOIN users u ON u.id = p.verifikasi_oleh
             WHERE p.booking_id = ? ORDER BY p.created_at DESC',
            [$bookingId]
        );
    }

    /** Antrian verifikasi pembayaran di dashboard admin. */
    public static function menungguVerifikasi(int $limit = 50): array
    {
        return Database::instance()->all(
            "SELECT p.*, b.invoice_number, b.total_harga, b.booking_date, b.start_time,
                    u.nama AS nama_pelanggan, un.kode_unit
               FROM payments p
               JOIN bookings b ON b.id = p.booking_id
               JOIN users u    ON u.id = b.user_id
               JOIN units un   ON un.id = b.unit_id
              WHERE p.status = 'menunggu_verifikasi'
              ORDER BY p.created_at ASC LIMIT " . max(1, $limit)
        );
    }

    /** Setujui pembayaran: payments.lunas + bookings.status_bayar lunas. */
    public static function verifikasi(int $id, bool $terima, string $catatan = '', ?int $oleh = null): array
    {
        $db = Database::instance();
        $p = $db->one('SELECT * FROM payments WHERE id = ? LIMIT 1', [$id]);
        if ($p === null) {
            throw new RuntimeException('Data pembayaran tidak ditemukan.');
        }
        if ($p['status'] !== 'menunggu_verifikasi') {
            throw new RuntimeException('Pembayaran ini sudah diverifikasi sebelumnya.');
        }

        $statusBaru = $terima ? 'lunas' : 'ditolak';

        $db->transaction(function () use ($db, $p, $id, $terima, $statusBaru, $catatan, $oleh): void {
            $db->update('payments', $id, [
                'status'          => $statusBaru,
                'catatan_admin'   => $catatan !== '' ? $catatan : ($terima ? 'Dana diterima.' : 'Bukti ditolak.'),
                'verifikasi_oleh' => $oleh ?? Auth::id(),
                'verifikasi_pada' => date('Y-m-d H:i:s'),
            ]);

            // Status bayar pesanan mengikuti pembayaran terakhir yang sah
            $db->update('bookings', (int) $p['booking_id'], ['status_bayar' => $statusBaru]);

            // Bila pesanan sudah dibatalkan, jangan ubah status pesannya
            Logger::write($oleh ?? Auth::id(), 'payment', $terima ? 'verifikasi' : 'tolak', 'payments', $id,
                'Pembayaran # ' . $id . ' -> ' . $statusBaru . ($catatan !== '' ? ' | ' . $catatan : ''));
        });

        return (new self())->find($id) ?? [];
    }

    /** Panjang antrian verifikasi (badge sidebar) — COUNT murni, tanpa JOIN. */
    public static function hitungAntrian(): int
    {
        return (int) Database::instance()->scalar(
            "SELECT COUNT(*) FROM payments WHERE status = 'menunggu_verifikasi'"
        );
    }

    /** Ringkasan untuk kartu statistik admin. */
    public static function statistik(): array
    {
        $db = Database::instance();
        return [
            'menunggu' => (int) ($db->scalar("SELECT COUNT(*) FROM payments WHERE status = 'menunggu_verifikasi'") ?? 0),
            'lunas_hari_ini' => (float) ($db->scalar(
                "SELECT COALESCE(SUM(jumlah), 0) FROM payments
                  WHERE status = 'lunas' AND DATE(verifikasi_pada) = CURDATE()"
            ) ?? 0),
            'ditolak' => (int) ($db->scalar("SELECT COUNT(*) FROM payments WHERE status = 'ditolak'") ?? 0),
        ];
    }

    /** Riwayat pembayaran admin dengan filter tanggal. */
    public static function daftar(string $status = '', string $dari = '', string $sampai = ''): array
    {
        $kondisi = ['1 = 1'];
        $params = [];
        if ($status !== '') {
            $kondisi[] = 'p.status = ?';
            $params[] = $status;
        }
        if ($dari !== '') {
            $kondisi[] = 'DATE(p.created_at) >= ?';
            $params[] = $dari;
        }
        if ($sampai !== '') {
            $kondisi[] = 'DATE(p.created_at) <= ?';
            $params[] = $sampai;
        }

        return Database::instance()->all(
            'SELECT p.*, b.invoice_number, b.total_harga, b.metode_bayar, b.status_pesanan,
                    u.nama AS nama_pelanggan, un.kode_unit, v.nama AS nama_verifikator
               FROM payments p
               JOIN bookings b ON b.id = p.booking_id
               JOIN users u    ON u.id = b.user_id
               JOIN units un   ON un.id = b.unit_id
               LEFT JOIN users v ON v.id = p.verifikasi_oleh
              WHERE ' . implode(' AND ', $kondisi) . '
              ORDER BY p.created_at DESC LIMIT 200',
            $params
        );
    }
}
