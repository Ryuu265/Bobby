/* =====================================================================
   QENGAMESTATION — dashboard.js
   Dua tugas: (1) menggambar grafik dari data PHP, (2) menyegarkan papan
   monitoring tiap 30 detik lewat endpoint JSON (hanya membaca).
   Konfigurasi dibaca dari <script id="qs-admin-data" type="application/json">.
   ===================================================================== */
(function () {
  "use strict";

  const QS = window.QS || {};
  const node = document.getElementById("qs-admin-data");
  if (!node) return;

  let cfg;
  try {
    cfg = JSON.parse(node.textContent || "{}");
  } catch (err) {
    return;
  }

  const pad = (n) => String(n).padStart(2, "0");

  function formatDetik(detik) {
    const d = Math.max(0, Math.floor(detik));
    return pad(Math.floor(d / 3600)) + ":" + pad(Math.floor((d % 3600) / 60)) + ":" + pad(d % 60);
  }

  /* ---------------- 1. GRAFIK ---------------- */

  function gambarGrafik() {
    (cfg.grafik || []).forEach(function (g) {
      const el = document.getElementById(g.el);
      if (!el || !g.data || g.data.length === 0) return;

      if (g.jenis === "line" && QS.lineChart) {
        QS.lineChart(el, g.data, { tinggi: g.tinggi || 150 });
      } else if (QS.barChart) {
        QS.barChart(el, g.data, {
          warna: g.warna || "cyan",
          tinggi: g.tinggi || 190,
          format: g.format === "angka" ? (n) => String(n) : QS.rupiah,
        });
      }
    });
  }

  /* ---------------- 2. MONITORING (AJAX) ---------------- */

  const m = cfg.monitor || null;
  const daftar = m ? document.getElementById(m.daftar) : null;
  if (!daftar) {
    gambarGrafik();
    return;
  }

  const penghitung = document.getElementById(m.penghitung);
  const sinkron = document.getElementById(m.sinkron);

  // app.js sudah menyalakan interval timer HANYA bila ada [data-countdown]
  // saat halaman dimuat. Bila tidak, tick kita jalankan sendiri agar
  // kartu hasil AJAX tetap bergerak (dan tidak dihitung dua kali).
  const tickMilikAppJs = Boolean(document.querySelector("[data-countdown]"));
  let tickKita = false;

  function mulaiTick() {
    if (tickMilikAppJs || tickKita) return;
    tickKita = true;
    setInterval(function () {
      if (QS.tickTimers) QS.tickTimers();
    }, 1000);
  }

  function kartuKosong() {
    return (
      '<div class="empty-state" style="grid-column: 1 / -1;">' +
      '<p class="display" style="font-size: var(--text-lg); margin-bottom: 4px;">Tidak ada sesi berjalan</p>' +
      '<p class="text-secondary" style="margin: 0; font-size: var(--text-sm); max-width: 56ch;">' +
      "Semua unit sedang kosong atau pesanannya belum dimulai. Timer muncul otomatis begitu ada sesi berlangsung.</p>" +
      "</div>"
    );
  }

  function kartu(b) {
    const total = Number(b.total_detik) > 0 ? Number(b.total_detik) : 60;
    const sisa = Math.max(0, Number(b.sisa_detik) || 0);
    const persen = Math.min(100, Math.round(((total - sisa) / total) * 100));
    const segera = sisa > 0 && sisa < 600;
    const tautan = (m.pesanan || "").replace("__ID__", encodeURIComponent(b.id));

    return (
      '<article class="qs-card qs-card--hoverable" data-monitor-card' +
      ' data-sesi-id="' + Number(b.id) + '"' +
      ' data-countdown="' + sisa + '"' +
      ' data-total-detik="' + total + '"' +
      ' style="padding: var(--sp-4); display: flex; flex-direction: column; gap: var(--sp-3);">' +
      '<header class="flex items-start justify-between gap-3">' +
      '<div><p class="display" style="margin:0;font-size:var(--text-lg);color:var(--text-primary);">' +
      QS.escapeHtml(b.kode_unit) +
      '</p><p class="text-muted" style="margin:2px 0 0;font-size:var(--text-xs);">' +
      QS.escapeHtml(b.status === "berlangsung" ? "Sesi aktif" : "Jadwal berjalan") +
      "</p></div>" +
      '<span class="badge ' + (segera ? "badge-warning" : "badge-info") + '" data-timer-badge>' +
      (segera ? "Segera selesai" : "Berlangsung") +
      "</span></header>" +
      '<div><p style="margin:0;font-size:var(--text-sm);font-weight:600;">' + QS.escapeHtml(b.nama_pelanggan) +
      '</p><p class="text-muted mono" style="margin:2px 0 0;font-size:var(--text-xs);">' + QS.escapeHtml(b.invoice) +
      "</p></div>" +
      '<p class="timer" style="margin:0;color:var(--cyan);"><span data-timer-text>' + formatDetik(sisa) + "</span></p>" +
      '<div class="bar"><div class="bar__fill' + (persen >= 90 ? " bar__fill--warning" : "") +
      '" data-timer-bar style="width:' + persen + '%;"></div></div>' +
      (tautan
        ? '<a href="' + tautan + '" class="btn btn-outline btn-sm" style="margin-top:auto;">Buka detail pesanan</a>'
        : "") +
      "</article>"
    );
  }

  function render(respons) {
    const sesi = (respons && respons.sesi) || [];
    daftar.innerHTML = sesi.length === 0 ? kartuKosong() : sesi.map(kartu).join("");

    if (penghitung) penghitung.textContent = String(sesi.length);
    if (sinkron) {
      const jamSinkron = respons && respons.generated_at ? new Date(respons.generated_at) : new Date();
      sinkron.textContent = "sinkron terakhir: " + pad(jamSinkron.getHours()) + "." + pad(jamSinkron.getMinutes()) + "." + pad(jamSinkron.getSeconds());
    }

    if (QS.tickTimers) QS.tickTimers();
    mulaiTick();
  }

  function muatUlang() {
    if (typeof QS.getJSON !== "function") return;
    QS.getJSON(m.url)
      .then(render)
      .catch(function () {
        if (sinkron) sinkron.textContent = "sinkron gagal — periksa koneksi";
      });
  }

  setInterval(muatUlang, 30000);

  document.addEventListener("click", function (e) {
    if (e.target.closest("[data-refresh-monitor]")) muatUlang();
  });

  gambarGrafik();
})();
