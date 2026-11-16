<?php
/**
 * Model — kelas dasar CRUD sederhana di atas PDO.
 * Anak kelas cukup mendeklarasikan $table (dan opsional $fillable).
 */

namespace App\Core;

use JsonSerializable;

abstract class Model implements JsonSerializable
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    /** Kolom yang boleh diisi dari input pengguna (whitelist anti mass-assignment). */
    protected array $fillable = [];

    /** Kolom yang bernilai tanggal/waktu dan perlu dinormalisasi. */
    protected array $dates = ['created_at', 'updated_at'];

    protected Database $db;

    public function __construct()
    {
        $this->db = Database::instance();
    }

    public function table(): string
    {
        return $this->table;
    }

    /* ------------------------- READ ------------------------- */

    public function find(int $id): ?array
    {
        return $this->db->one("SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1", [$id]);
    }

    /** Cari atau hentikan request dengan 404. */
    public function findOrFail(int $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            Error::abort(404, 'Data tidak ditemukan atau sudah dihapus.');
        }
        return $row;
    }

    public function first(): ?array
    {
        return $this->db->one("SELECT * FROM `{$this->table}` LIMIT 1");
    }

    /** Semua baris, boleh diurutkan. */
    public function all(string $order = ''): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $this->safeOrder($order);
        }
        return $this->db->all($sql);
    }

    /**
     * Ambil beberapa baris dengan where sederhana.
     * $where = ['status' => 'aktif', 'tipe' => 'PS5']  (selalu di-AND)
     */
    public function where(array $where, string $order = '', ?int $limit = null, int $offset = 0): array
    {
        [$sql, $params] = $this->buildWhere($where, $order, $limit, $offset);
        return $this->db->all($sql, $params);
    }

    public function whereFirst(array $where, string $order = ''): ?array
    {
        [$sql, $params] = $this->buildWhere($where, $order, 1, 0);
        return $this->db->one($sql, $params);
    }

    public function countWhere(array $where = []): int
    {
        [$condition, $params] = $this->condition($where);
        return (int) $this->db->scalar("SELECT COUNT(*) FROM `{$this->table}`{$condition}", $params);
    }

    /* ------------------------- WRITE ------------------------- */

    public function create(array $data): int
    {
        $payload = $this->onlyFillable($data);
        if ($payload === []) {
            throw new \RuntimeException('Tidak ada kolom yang dapat disimpan pada ' . $this->table);
        }
        return $this->db->insert($this->table, $payload);
    }

    public function update(int $id, array $data): int
    {
        return $this->db->update($this->table, $id, $this->onlyFillable($data));
    }

    /** Update langsung tanpa filter fillable (dipakai untuk kolom sistem). */
    public function updateRaw(int $id, array $data): int
    {
        return $this->db->update($this->table, $id, $data);
    }

    public function delete(int $id): int
    {
        return $this->db->delete($this->table, $id);
    }

    /* ------------------------- UTILITAS ------------------------- */

    /** Whitelist kolom: hanya $fillable + primary key yang boleh ditulis. */
    protected function onlyFillable(array $data): array
    {
        $allow = $this->fillable !== [] ? $this->fillable : array_keys($data);
        $payload = [];
        foreach ($allow as $column) {
            if (array_key_exists($column, $data)) {
                $value = $data[$column];
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                } elseif ($value === '' && in_array($column, $this->dates, true)) {
                    // Kolom tanggal/waktu yang kosong dari form disimpan sebagai NULL
                    $value = null;
                }
                $payload[$column] = $value;
            }
        }
        return $payload;
    }

    /** Bangun klausa WHERE yang di-parameterisasi. */
    private function buildWhere(array $where, string $order, ?int $limit, int $offset): array
    {
        [$condition, $params] = $this->condition($where);
        $sql = "SELECT * FROM `{$this->table}`{$condition}";
        if ($order !== '') {
            $sql .= ' ORDER BY ' . $this->safeOrder($order);
        }
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        return [$sql, $params];
    }

    private function condition(array $where): array
    {
        $parts = [];
        $params = [];
        foreach ($where as $column => $value) {
            if (is_array($value)) {
                if ($value === []) {
                    $parts[] = '1 = 0'; // IN () kosong tidak valid di MySQL
                    continue;
                }
                $placeholders = implode(', ', array_fill(0, count($value), '?'));
                $parts[] = "`{$column}` IN ({$placeholders})";
                array_push($params, ...array_values($value));
            } elseif ($value === null) {
                $parts[] = "`{$column}` IS NULL";
            } else {
                $parts[] = "`{$column}` = ?";
                $params[] = $value;
            }
        }
        $sql = $parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts);
        return [$sql, $params];
    }

    /** Cegah ORDER BY injection: hanya nama kolom + ASC/DESC. */
    private function safeOrder(string $order): string
    {
        $clean = [];
        foreach (explode(',', $order) as $piece) {
            $tokens = preg_split('/\s+/', trim($piece)) ?: [];
            $column = str_replace(['`', '"'], '', $tokens[0] ?? '');
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
                continue;
            }
            $dir = strtoupper($tokens[1] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';
            $pos = strpos($column, '.');
            $qualified = $pos === false
                ? "`{$column}`"
                : '`' . substr($column, 0, $pos) . '`.`' . substr($column, $pos + 1) . '`';
            $clean[] = $qualified . ' ' . $dir;
        }
        return $clean === [] ? '1' : implode(', ', $clean);
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
