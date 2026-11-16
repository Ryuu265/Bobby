<?php
/**
 * Validator — validasi input sisi SERVER (satu-satunya yang bisa dipercaya).
 * Aturan umum: required, email, min, max, numeric, integer, date, time,
 * in, exists, unique, confirmed, image, file.
 *
 * Pemakaian:
 *   $v = Validator::make($data, ['nama' => 'required|min:3|max:100']);
 *   if ($v->fails()) { ... $v->errors() ... }
 */

namespace App\Core;

use App\Core\Database;

class Validator
{
    private array $data;
    private array $rules;
    private array $labels;
    private array $errors = [];

    /** Aturan khusus upload: ['nama_file' => 'image|max:2048'] */
    private array $uploads = [];

    public function __construct(array $data, array $rules, array $labels = [], array $uploads = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
        $this->uploads = $uploads;
    }

    public static function make(array $data, array $rules, array $labels = [], array $uploads = []): self
    {
        return new self($data, $rules, $labels, $uploads);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $messages) {
            if ($messages !== []) {
                return $messages[0];
            }
        }
        return null;
    }

    /** Jalankan seluruh aturan dan kumpulkan pesan dalam Bahasa Indonesia. */
    public function validate(): self
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $label = $this->labels[$field] ?? ucfirst(str_replace('_', ' ', $field));

            $isRequired = in_array('required', $rules, true);
            $isEmpty = $value === null || (is_string($value) && trim($value) === '');

            if ($isRequired && $isEmpty) {
                $this->addError($field, "{$label} wajib diisi.");
                continue;
            }
            if (!$isRequired && $isEmpty) {
                continue; // kosong & tidak wajib -> lewati aturan lainnya
            }

            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    continue;
                }
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $this->applyRule($name, $field, $value, $param, $label);
            }
        }

        // Validasi file upload dipisah agar tidak mencampur string & array
        foreach ($this->uploads as $field => $ruleString) {
            $this->validateUpload($field, $ruleString, $this->labels[$field] ?? 'Berkas');
        }

        return $this;
    }

    private function applyRule(string $rule, string $field, mixed $value, ?string $param, string $label): void
    {
        switch ($rule) {
            case 'email':
                if (!filter_var((string) $value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "{$label} tidak valid, contoh: nama@email.com.");
                }
                break;

            case 'min':
                if (mb_strlen((string) $value) < (int) $param) {
                    $this->addError($field, "{$label} minimal {$param} karakter.");
                }
                break;

            case 'max':
                if (mb_strlen((string) $value) > (int) $param) {
                    $this->addError($field, "{$label} maksimal {$param} karakter.");
                }
                break;

            case 'numeric':
                if (!is_numeric(str_replace(',', '', (string) $value))) {
                    $this->addError($field, "{$label} harus berupa angka.");
                }
                break;

            case 'integer':
                if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $this->addError($field, "{$label} harus bilangan bulat.");
                }
                break;

            case 'minval':
                if ((float) $value < (float) $param) {
                    $this->addError($field, "{$label} tidak boleh kurang dari {$param}.");
                }
                break;

            case 'maxval':
                if ((float) $value > (float) $param) {
                    $this->addError($field, "{$label} tidak boleh lebih dari {$param}.");
                }
                break;

            case 'date':
                if (strtotime((string) $value) === false) {
                    $this->addError($field, "Format {$label} tidak valid (YYYY-MM-DD).");
                }
                break;

            case 'time':
                if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value)) {
                    $this->addError($field, "Format {$label} tidak valid (HH:MM).");
                }
                break;

            case 'phone':
                if (!preg_match('/^[0-9+\-\s()]{8,20}$/', (string) $value)) {
                    $this->addError($field, "Format {$label} tidak valid.");
                }
                break;

            case 'in':
                $allowed = explode(',', (string) $param);
                if (!in_array((string) $value, $allowed, true)) {
                    $this->addError($field, "{$label} harus salah satu dari: " . implode(', ', $allowed) . '.');
                }
                break;

            case 'after': // contoh: after:booking_date
                $other = (string) ($this->data[(string) $param] ?? '');
                if ($other !== '' && strtotime((string) $value) < strtotime($other)) {
                    $this->addError($field, "{$label} tidak boleh sebelum "
                        . ($this->labels[$param] ?? $param) . '.');
                }
                break;

            case 'confirmed':
                if ((string) $value !== (string) ($this->data[$field . '_confirmation'] ?? '')) {
                    $this->addError($field, "Konfirmasi {$label} tidak sama.");
                }
                break;

            case 'exists': // tabel,kolom_yang_dicek[,id yang dikecualikan]
                [$table, $column] = explode(',', (string) $param, 2);
                $column = $column === '' || $column === 'id' ? 'id' : $column;
                $found = Database::instance()->scalar(
                    "SELECT id FROM `{$table}` WHERE `{$column}` = ? LIMIT 1",
                    [$value]
                );
                if ($found === null) {
                    $this->addError($field, "{$label} tidak ditemukan pada data kami.");
                }
                break;

            case 'unique': // tabel,kolom[,id sekarang]
                [$table, $column] = array_pad(explode(',', (string) $param, 3), 3, 'id');
                $currentId = $this->data['_current_id'] ?? null;
                $sql = "SELECT id FROM `{$table}` WHERE `{$column}` = ?";
                $params = [$value];
                if ($currentId) {
                    $sql .= ' AND id <> ?';
                    $params[] = $currentId;
                }
                $sql .= ' LIMIT 1';
                if (Database::instance()->scalar($sql, $params) !== null) {
                    $this->addError($field, "{$label} sudah terdaftar. Silakan gunakan {$label} lain.");
                }
                break;

            default:
                // Aturan tak dikenal diabaikan diam-diam (sengaja, agar view tetap jalan)
                break;
        }
    }

    /** Validasi berkas upload: ukuran, tipe, dan keberadaan. */
    private function validateUpload(string $field, string $ruleString, string $label): void
    {
        $rules = explode('|', $ruleString);
        $file = request()->file($field);

        if (in_array('required', $rules, true) && $file === null) {
            $this->addError($field, "{$label} wajib diunggah.");
            return;
        }
        if ($file === null) {
            return;
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->addError($field, "{$label} gagal diunggah. Coba lagi.");
            return;
        }

        $maxKb = 0;
        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'max:')) {
                $maxKb = (int) substr($rule, 4);
            }
        }
        if ($maxKb > 0 && $file['size'] > $maxKb * 1024) {
            $this->addError($field, "Ukuran {$label} maksimal " . number_format($maxKb) . ' KB.');
        }

        if (in_array('image', $rules, true)) {
            $allowed = $this->isProof($rules) ? config('upload.proof_types') : config('upload.image_types');
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true) || !is_array(@getimagesize($file['tmp_name'])) && $ext !== 'pdf') {
                $this->addError($field, "{$label} harus berupa berkas: " . implode(', ', $allowed) . '.');
            }
        }
    }

    private function isProof(array $rules): bool
    {
        return in_array('proof', $rules, true);
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
    }
}
