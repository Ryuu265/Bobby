/* =====================================================================
   tools/cek-konsistensi.js
   Pemeriksa statis lintas file untuk proyek PHP yang tidak bisa dijalankan
   di mesin ini. Memastikan:
     1. Setiap handler rute (Class@method) benar-benar ada di file controller.
     2. Setiap nama rute yang dipakai route('...') terdaftar di routes/web.php.
     3. Setiap view yang dirender ($this->view/adminView/fragment/View::include)
        ada berkasnya di app/Views.
     4. Setiap pemanggilan statis App\Models\Kls::metode(...) tersedia,
        termasuk pewarisan dari App\Core\Model.
     5. Tidak ada warna hex literal di markup view (larangan design system).
     6. Kurung kurawal & tanda kurung tiap file PHP seimbang (deteksi dini
        file yang terpotong di tengah jalan).
   Cara pakai: node tools/cek-konsistensi.js
   ===================================================================== */
const fs = require("fs");
const path = require("path");

const AKAR = path.resolve(__dirname, "..");
const masalah = [];
const info = [];

function baca(f) {
  return fs.readFileSync(path.join(AKAR, f), "utf8");
}

function daftarPhp(dir) {
  const out = [];
  const walk = (d, rel) => {
    for (const e of fs.readdirSync(path.join(d, ""), { withFileTypes: true })) {
      const abs = path.join(d, e.name);
      const r = rel ? rel + "/" + e.name : e.name;
      if (e.isDirectory()) walk(abs, r);
      else if (e.name.endsWith(".php")) out.push({ abs, rel: r });
    }
  };
  walk(path.join(AKAR, dir), "");
  return out;
}

/* ---------- 1. Kumpulkan rute ---------- */
const ruteSrc = baca("routes/web.php");
const handlers = [];
const namaRute = new Set();
for (const m of ruteSrc.matchAll(
  /\$r(?:outer)?->(get|post|put|delete)\(\s*'([^']+)'\s*,\s*'([^']+)'\s*(?:,\s*'([^']+)')?/g
)) {
  handlers.push({ path: m[2], handler: m[3], name: m[4] });
  if (m[4]) namaRute.add(m[4]);
}

/* ---------- 2. Kumpulkan controller + method publik ---------- */
const controllerFiles = daftarPhp("app/Controllers");
const methodByClass = new Map();
for (const { abs, rel } of controllerFiles) {
  const src = fs.readFileSync(abs, "utf8");
  const ns = (src.match(/namespace\s+([^;\n]+);/) || [, "App\\Controllers"])[1].trim();
  const kelas = (src.match(/class\s+(\w+)/) || [, path.basename(rel, ".php")])[1];
  const fqn = ns + "\\" + kelas;
  const ms = new Set();
  for (const m of src.matchAll(
    /(?:public|protected|private)?\s*(?:static\s+)?function\s+(\w+)\s*\(/g
  )) ms.add(m[1]);
  methodByClass.set(fqn, ms);
}

for (const r of handlers) {
  if (!r.handler.includes("@")) continue; // closure
  const [kelasRel, method] = r.handler.split("@");
  // 'Admin\\UnitController' pada sumber PHP = Admin\UnitController sesungguhnya
  const kelas = kelasRel.replace(/\\\\/g, "\\").replace(/\\+/g, "\\");
  const fqn = "App\\Controllers\\" + kelas;
  const ms = methodByClass.get(fqn);
  if (!ms) {
    masalah.push(`RUTE ${r.path} -> controller tidak ada: ${fqn}`);
  } else if (!ms.has(method)) {
    masalah.push(`RUTE ${r.path} -> method hilang: ${fqn}::${method}()`);
  }
}

/* ---------- 3. Nama rute yang dipakai ---------- */
const viewFiles = daftarPhp("app/Views");
for (const { abs, rel } of [...viewFiles, ...controllerFiles]) {
  const src = fs.readFileSync(abs, "utf8");
  for (const m of src.matchAll(/route\(\s*'([^']+)'/g)) {
    if (!namaRute.has(m[1])) {
      masalah.push(`${rel}: route('${m[1]}') tidak terdaftar di routes/web.php`);
    }
  }
}

/* ---------- 4. View yang dirender ---------- */
const viewExists = (nama) =>
  fs.existsSync(path.join(AKAR, "app/Views", nama.replace(/\./g, "/") + ".php"));

const polaRender =
  /\$this->(view|adminView|fragment|render)\(\s*'([^']+)'|View::(include|partial|display|render|capture)\(\s*'([^']+)'/g;
for (const { abs, rel } of [...viewFiles, ...controllerFiles]) {
  const src = fs.readFileSync(abs, "utf8");
  for (const m of src.matchAll(polaRender)) {
    const nama = m[2] || m[5];
    if (nama && !viewExists(nama)) {
      masalah.push(`${rel}: view '${nama}' tidak ditemukan di app/Views`);
    }
  }
}

/* ---------- 5. Method statis model ---------- */
const modelFiles = daftarPhp("app/Models");
const modelMethods = new Map();
const coreModel = baca("app/Core/Model.php");
const metodeInti = new Set();
for (const m of coreModel.matchAll(/function\s+(\w+)\s*\(/g)) metodeInti.add(m[1]);
for (const { abs, rel } of modelFiles) {
  const src = fs.readFileSync(abs, "utf8");
  const kelas = (src.match(/class\s+(\w+)/) || [, ""])[1];
  const ms = new Set(metodeInti);
  for (const m of src.matchAll(/function\s+(\w+)\s*\(/g)) ms.add(m[1]);
  modelMethods.set(kelas, ms);
}
for (const { abs, rel } of [...controllerFiles, ...viewFiles]) {
  const src = fs.readFileSync(abs, "utf8");
  for (const m of src.matchAll(/(\w+)::([a-z_][a-z0-9_]*)\s*\(/gi)) {
    const [, kelas, method] = m;
    if (!modelMethods.has(kelas)) continue; // Schedule/Pricing/UnitGame dll. di luar daftar
    if (!modelMethods.get(kelas).has(method)) {
      masalah.push(`${rel}: ${kelas}::${method}() tidak ada`);
    }
  }
}

/* ---------- 6. Warna hex literal di markup view ---------- */
for (const { abs, rel } of viewFiles) {
  const src = fs.readFileSync(abs, "utf8");
  let tanpaKomentar = src.replace(/\/\*[\s\S]*?\*\//g, "").replace(/^\s*\/\/.*$/gm, "");
  // meta name="theme-color" wajib berisi warna asli (tidak bisa pakai var())
  tanpaKomentar = tanpaKomentar.replace(/<meta[^>]*theme-color[^>]*>/gi, "");
  const hits = tanpaKomentar.match(/#[0-9a-fA-F]{3,8}\b/g);
  if (hits) {
    masalah.push(`${rel}: warna hex literal di markup (pakai var(--...)): ${hits.join(", ")}`);
  }
}

/* ---------- 7. Keseimbangan kurung (deteksi file terpotong) ---------- */
for (const { abs, rel } of [...controllerFiles, ...viewFiles, ...modelFiles]) {
  const src = fs.readFileSync(abs, "utf8");
  const hit = (ch) => (src.match(new RegExp(`\\${ch}`, "g")) || []).length;
  if (hit("{") !== hit("}")) {
    masalah.push(`${rel}: kurung kurawal tidak seimbang (${hit("{")} vs ${hit("}")})`);
  }
  if (!src.includes("<?php")) {
    masalah.push(`${rel}: tidak ada tag <?php`);
  }
}

/* ---------- Hasil ---------- */
info.push(`Rute terdeteksi: ${handlers.length}, nama rute: ${namaRute.size}`);
info.push(`Controller: ${controllerFiles.length}, View: ${viewFiles.length}, Model: ${modelFiles.length}`);

console.log(info.join("\n"));
if (masalah.length === 0) {
  console.log("\nOK - tidak ada inkonsistensi yang terdeteksi.");
} else {
  console.log(`\nDITEMUKAN ${masalah.length} MASALAH:\n`);
  for (const m of [...new Set(masalah)]) console.log(" - " + m);
  process.exitCode = 1;
}
