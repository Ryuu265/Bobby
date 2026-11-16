<?php
/**
 * tools/uji-closure.php — Pencari closure yang memakai variabel luar
 * tanpa menuliskannya pada daftar `use (...)`.
 *
 * Kesalahan seperti ini tidak terlihat oleh `php -l`: PHP baru mengirim
 * Warning saat baris itu benar-benar dijalankan. Karena aplikasi ini
 * mengubah Warning menjadi exception, efeknya halaman menjadi 500.
 *
 * Cara kerjanya dua tahap per closure:
 *   1. kumpulkan semua nama variabel yang lahir di dalam body (penugasan,
 *      foreach as, parameter closure bersarang);
 *   2. laporkan variabel yang dibaca tapi tidak pernah lahir di dalamnya.
 *
 * Arrow function (`fn(...) => ...`) tidak diperiksa sebagai closure sendiri,
 * karena variabel luar otomatis ditangkap oleh nilai.
 *
 * Pemakaian:
 *   php tools/uji-closure.php
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Script ini hanya dijalankan dari terminal.\n");
}

/** Variabel yang tidak pernah perlu di-capture. */
const SUPER = ['_GET', '_POST', '_SERVER', '_SESSION', '_FILES', '_ENV', 'GLOBALS', 'this'];

$root = dirname(__DIR__);
$temuan = 0;

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $temuan += periksa((string) $file->getPathname(), $root);
    }
}

echo "\n" . str_repeat('-', 62) . "\n";
echo $temuan === 0
    ? "Aman: tidak ada closure yang memakai variabel tanpa `use`.\n"
    : "Ditemukan {$temuan} closure yang perlu diperbaiki.\n";
exit($temuan === 0 ? 0 : 1);

/** Periksa satu file, kembalikan jumlah closure bermasalah. */
function periksa(string $path, string $root): int
{
    $token = token_get_all((string) file_get_contents($path));
    $n = count($token);
    $temuan = 0;

    for ($i = 0; $i < $n; $i++) {
        if (!is_array($token[$i]) || $token[$i][0] !== T_FUNCTION) {
            continue;
        }

        $j = lompatSpasi($token, $i + 1, $n);
        if ($j < $n && $token[$j] === '&') {
            $j = lompatSpasi($token, $j + 1, $n);
        }
        if ($j >= $n || $token[$j] !== '(') {
            continue; // fungsi bernama, bukan closure
        }

        // Closure di dalam curly string ("...{$a->b}") tidak ikut dihitung.
        [$param, $pakai, $tutup] = bacaDaftar($token, $j, $n);
        $body = isiBody($token, $tutup, $n);
        if ($body === null) {
            continue;
        }

        $lokal = array_fill_keys(array_merge(
            bindingLokal($body),
            $param,
            SUPER,
        ), true);
        // Nama yang di-capture lewat `use ($x)` boleh dibaca di dalam body.
        foreach ($pakai as $nama) {
            $lokal[$nama] = true;
        }

        $luar = [];
        foreach ($body as $t) {
            if (!is_array($t) || $t[0] !== T_VARIABLE) {
                continue;
            }
            $nama = ltrim((string) $t[1], '$');
            if (!isset($lokal[$nama])) {
                $luar[$nama] = true;
            }
        }

        if ($luar !== []) {
            printf(
                "%s:%d  closure memakai $%s tanpa `use`\n",
                str_replace('\\', '/', substr($path, strlen($root))),
                $token[$i][2],
                implode(', $', array_keys($luar))
            );
            $temuan++;
        }
    }

    return $temuan;
}

/**
 * Semua nama variabel yang dibuat di dalam body:
 * target penugasan, target `foreach ... as`, dan parameter closure bersarang.
 *
 * @return string[]
 */
function bindingLokal(array $body): array
{
    $lokal = [];
    $n = count($body);

    foreach ($body as $k => $t) {
        // [$a, $b] = ... atau list($a, $b) = ... : target destructuring.
        // Token `[` berupa string, jadi periksa sebelum guard is_array().
        if ($t === '[' || (is_array($t) && $t[0] === T_LIST)) {
            foreach (targetList($body, $k, $n) as $nama) {
                $lokal[] = $nama;
            }
        }

        if (!is_array($t)) {
            continue;
        }

        // $x = ..., $x += ..., foreach ($a as $x), foreach ($a as $k => $v)
        if ($t[0] === T_VARIABLE && adalahPenugasan($body[berikutnya($body, $k, $n)] ?? null)) {
            $lokal[] = ltrim((string) $t[1], '$');
            continue;
        }

        if ($t[0] === T_AS || $t[0] === T_FOREACH) {
            $i = berikutnya($body, $k, $n);
            while ($i < $n && is_array($body[$i] ?? null) && $body[$i][0] === T_VARIABLE) {
                $lokal[] = ltrim((string) $body[$i][1], '$');
                $i = berikutnya($body, $i, $n);
                // `as $k => $v`: lompat tanda => lalu ambil variabel berikutnya.
                if ($i < $n && is_array($body[$i] ?? null) && $body[$i][0] === T_DOUBLE_ARROW) {
                    $i = berikutnya($body, $i, $n);
                    if ($i < $n && is_array($body[$i] ?? null) && $body[$i][0] === T_VARIABLE) {
                        $lokal[] = ltrim((string) $body[$i][1], '$');
                    }
                    break;
                }
                break;
            }
            continue;
        }

        // Parameter closure atau arrow function di dalam body.
        if ($t[0] === T_FUNCTION || $t[0] === T_FN) {
            foreach (bacaParamClosure($body, $k, $n) as $nama) {
                $lokal[] = $nama;
            }
        }
    }

    return $lokal;
}

/** Nama variabel hasil destruktur `[$a, $b] = ...` pada indeks tertentu. */
function targetList(array $t, int $buka, int $n): array
{
    $dalam = 0;
    $nama = [];
    for ($k = $buka; $k < $n; $k++) {
        if ($t[$k] === '[') {
            $dalam++;
            continue;
        }
        if ($t[$k] === ']') {
            if (--$dalam === 0) {
                // Kurung siku di sisi kiri tanda sama dengan = destructuring.
                $setelah = lompatSpasi($t, $k + 1, $n);
                return $setelah < $n && adalahPenugasan($t[$setelah]) ? $nama : [];
            }
            continue;
        }
        if ($dalam === 1 && is_array($t[$k]) && $t[$k][0] === T_VARIABLE) {
            $nama[] = ltrim((string) $t[$k][1], '$');
        }
    }

    return [];
}

/** Indeks parameter closure bersarang, dibaca dari daftar `(...)`. */
function bacaParamClosure(array $t, int $pos, int $n): array
{
    $buka = berikutnya($t, $pos, $n);
    $param = [];
    $dalam = 0;
    for ($k = $buka; $k < $n; $k++) {
        if ($t[$k] === '(') {
            $dalam++;
            continue;
        }
        if ($t[$k] === ')') {
            if (--$dalam === 0) {
                return $param;
            }
            continue;
        }
        if ($dalam === 1 && is_array($t[$k]) && $t[$k][0] === T_VARIABLE) {
            $param[] = ltrim((string) $t[$k][1], '$');
        }
    }

    return $param;
}

/** Indeks token berikutnya yang bukan spasi/komentar. */
function berikutnya(array $t, int $k, int $n): int
{
    return lompatSpasi($t, $k + 1, $n);
}

function lompatSpasi(array $t, int $dari, int $n): int
{
    for ($k = $dari; $k < $n; $k++) {
        $x = $t[$k] ?? null;
        if ($x === null) {
            return $n;
        }
        if (!is_array($x) || !in_array($x[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            return $k;
        }
    }
    return $n;
}

/** Baca daftar parameter + `use (...)`; kembalikan [param, pakai, indeks kurung tutup]. */
function bacaDaftar(array $t, int $buka, int $n): array
{
    $param = $pakai = [];
    $dalam = 0;
    $mode = 'param';

    for ($k = $buka; $k < $n; $k++) {
        $x = $t[$k];
        if ($x === '(') {
            $dalam++;
            continue;
        }
        if ($x === ')') {
            if (--$dalam === 0) {
                // Setelah daftar parameter, masih boleh ada `use (...)`.
                if ($mode === 'use') {
                    return [$param, $pakai, $k];
                }
                $i = lompatSpasi($t, $k + 1, $n);
                if ($i < $n && is_array($t[$i]) && $t[$i][0] === T_USE) {
                    $mode = 'use';
                    continue;
                }
                return [$param, $pakai, $k];
            }
            continue;
        }
        if (is_array($x) && $x[0] === T_VARIABLE) {
            $nama = ltrim((string) $x[1], '$');
            if ($mode === 'param') {
                $param[] = $nama;
            } else {
                $pakai[] = $nama;
            }
        }
    }

    return [$param, $pakai, $buka];
}

/** Token di dalam blok `{ ... }` setelah kurung tutup daftar parameter. */
function isiBody(array $t, int $setelah, int $n): ?array
{
    $k = $setelah;
    while ($k < $n && $t[$k] !== '{') {
        $k++;
    }
    if ($k >= $n) {
        return null;
    }

    $body = [];
    $dalam = 0;
    for (; $k < $n; $k++) {
        if ($t[$k] === '{') {
            if (++$dalam === 1) {
                continue;
            }
        } elseif ($t[$k] === '}') {
            if (--$dalam === 0) {
                return $body;
            }
        }
        $body[] = $t[$k];
    }

    return null;
}

/** Apakah token ini operator yang memberi nilai ke variabel di kirinya? */
function adalahPenugasan(mixed $t): bool
{
    if ($t === null) {
        return false;
    }
    if (is_string($t)) {
        return $t === '=';
    }
    return in_array(
        $t[0],
        [T_PLUS_EQUAL, T_MINUS_EQUAL, T_MUL_EQUAL, T_DIV_EQUAL, T_CONCAT_EQUAL,
         T_COALESCE_EQUAL, T_POW_EQUAL, T_MOD_EQUAL, T_AND_EQUAL, T_OR_EQUAL,
         T_XOR_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_INC, T_DEC],
        true
    );
}
