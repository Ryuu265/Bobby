<?php
/**
 * Database — lapisan PDO tunggal (singleton) untuk seluruh aplikasi.
 * Menyediakan metode singkat untuk query agar controller tetap bersih.
 * Semua statement memakai prepared statement => aman dari SQL Injection.
 */

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $cfg = config('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // prepared statement asli
                PDO::ATTR_STRINGIFY_FETCHES  => false, // numeric tetap float/int
            ]);
            $this->pdo->exec("SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}");
        } catch (PDOException $e) {
            // Jangan pernah menampilkan kredensial database ke pengguna
            throw new RuntimeException(
                'Koneksi database gagal. Periksa isi file .env dan pastikan MySQL berjalan. '
                . (config('app.debug') ? $e->getMessage() : ''),
                (int) $e->getCode(),
                $e
            );
        }
    }

    public static function instance(): Database
    {
        return self::$instance ??= new self();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Jalankan statement dengan parameter terikat. */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Ambil SATU baris (atau null bila kosong). */
    public function one(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Ambil BANYAK baris. */
    public function all(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Ambil satu kolom nilai pertama (untuk COUNT/SUM/EXISTS). */
    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** INSERT dan kembalikan id yang baru dibuat. */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns))
        );
        $this->run($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** UPDATE berdasarkan id. */
    public function update(string $table, int $id, array $data): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = implode(', ', array_map(static fn ($c) => "`{$c}` = :{$c}", array_keys($data)));
        $sql = "UPDATE `{$table}` SET {$sets} WHERE id = :__id";
        $data['__id'] = $id;
        return $this->run($sql, $data)->rowCount();
    }

    public function delete(string $table, int $id): int
    {
        return $this->run("DELETE FROM `{$table}` WHERE id = ?", [$id])->rowCount();
    }

    /** Jalankan kumpulan perintah dalam satu transaksi (rollback otomatis). */
    public function transaction(callable $callback): mixed
    {
        $this->begin();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    public function begin(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { if ($this->pdo->inTransaction()) $this->pdo->commit(); }
    public function rollback(): void { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); }

    private function __clone() {}
    public function __wakeup() { throw new RuntimeException('Tidak boleh di-serialize.'); }
}
