/* =====================================================================
   QENGAMESTATION — app.js
   Perilaku bersama untuk semua halaman. Tidak memakai framework JS,
   semuanya vanilla agar ringan dan mudah dirawat.
   ===================================================================== */
(function () {
  "use strict";

  const QS = (window.QS = window.QS || {});

  /* ---------------- Utilitas ---------------- */

  QS.rupiah = function (angka) {
    return "Rp" + Number(angka || 0).toLocaleString("id-ID", { maximumFractionDigits: 0 });
  };

  QS.debounce = function (fn, wait) {
    let t;
    return function (...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), wait || 250);
    };
  };

  QS.escapeHtml = function (str) {
    return String(str ?? "").replace(/[&<>"']/g, (c) => ({
      "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;",
    }[c]));
  };

  QS.pad = (n) => String(n).padStart(2, "0");

  /* ---------------- Notifikasi flash ---------------- */

  function tutupAlert(el) {
    el.style.transition = "opacity 250ms ease, transform 250ms ease";
    el.style.opacity = "0";
    el.style.transform = "translateY(-6px)";
    setTimeout(() => el.remove(), 260);
  }

  document.addEventListener("click", (e) => {
    const tombol = e.target.closest("[data-dismiss]");
    if (tombol) {
      const alert = tombol.closest(".alert");
      if (alert) tutupAlert(alert);
    }
  });

  window.addEventListener("load", () => {
    document.querySelectorAll("[data-autoclose]").forEach((el) => {
      const ms = parseInt(el.getAttribute("data-autoclose"), 10);
      if (ms > 0) setTimeout(() => tutupAlert(el), ms);
    });
  });

  /* ---------------- Konfirmasi aksi berbahaya ---------------- */

  document.addEventListener("submit", (e) => {
    const form = e.target;
    const pesan = form.getAttribute("data-confirm");
    if (pesan && !window.confirm(pesan)) {
      e.preventDefault();
      // Pulihkan tombol agar tidak terlihat "loading" selamanya
      const submit = form.querySelector('[type="submit"]');
      if (submit) submit.disabled = false;
    }
  });

  /* ---------------- Drawer sidebar mobile ---------------- */

  document.addEventListener("click", (e) => {
    const pemicu = e.target.closest("[data-toggle-sidebar]");
    if (pemicu) {
      const drawer = document.getElementById("sidebarMobile");
      if (drawer) {
        drawer.classList.add("is-open");
        pemicu.setAttribute("aria-expanded", "true");
      }
      return;
    }
    if (e.target.closest("[data-dismiss-modal]")) {
      const modal = e.target.closest(".modal");
      if (modal) {
        modal.classList.remove("is-open");
        document.querySelectorAll('[data-toggle-sidebar]').forEach((b) => b.setAttribute("aria-expanded", "false"));
      }
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.is-open").forEach((m) => m.classList.remove("is-open"));
    }
  });

  /* ---------------- Modal generik ---------------- */

  QS.openModal = function (id) {
    const m = document.getElementById(id);
    if (m) m.classList.add("is-open");
  };
  QS.closeModal = function (id) {
    const m = document.getElementById(id);
    if (m) m.classList.remove("is-open");
  };

  document.addEventListener("click", (e) => {
    const pemicu = e.target.closest("[data-open-modal]");
    if (pemicu) {
      e.preventDefault();
      QS.openModal(pemicu.getAttribute("data-open-modal"));
    }
  });

  /* ---------------- Hitung mundur (halaman monitoring) ---------------- */

  function formatDetik(detik) {
    const d = Math.max(0, Math.floor(detik));
    const h = Math.floor(d / 3600);
    const m = Math.floor((d % 3600) / 60);
    const s = d % 60;
    return QS.pad(h) + ":" + QS.pad(m) + ":" + QS.pad(s);
  }

  QS.tickTimers = function () {
    document.querySelectorAll("[data-countdown]").forEach((el) => {
      let sisa = parseInt(el.getAttribute("data-countdown"), 10);
      if (isNaN(sisa)) return;
      const total = parseInt(el.getAttribute("data-total-detik") || "0", 10) || sisa;

      sisa -= 1;
      el.setAttribute("data-countdown", String(sisa));

      const teks = el.querySelector("[data-timer-text]") || el;
      if (sisa <= 0) {
        teks.textContent = "Selesai";
        teks.classList.add("text-success");
        el.closest("[data-monitor-card]")?.setAttribute("data-state", "selesai");
      } else {
        teks.textContent = formatDetik(sisa);
      }

      const bar = el.querySelector("[data-timer-bar]");
      if (bar) {
        const persen = Math.max(0, Math.min(100, ((total - sisa) / total) * 100));
        bar.style.width = persen.toFixed(1) + "%";
        if (persen >= 90) bar.classList.add("bar__fill--warning");
      }

      // Sisa < 10 menit -> tandai akan segera selesai
      if (sisa > 0 && sisa < 600) {
        const badge = el.querySelector("[data-timer-badge]");
        if (badge && !badge.classList.contains("badge-warning")) {
          badge.classList.remove("badge-info");
          badge.classList.add("badge-warning");
          badge.textContent = "Segera selesai";
        }
      }
    });
  };

  if (document.querySelector("[data-countdown]")) {
    QS.tickTimers();
    setInterval(QS.tickTimers, 1000);
  }

  /* ---------------- Grafik SVG sederhana (tanpa library) ---------------- */

  /**
   * QS.barChart(el, data, opts)
   * data: [{ label: "01/11", nilai: 120000 }, ...]
   * opts: { warna: "cyan"|"violet", tinggi: 200, format: fn }
   */
  QS.barChart = function (el, data, opts) {
    opts = opts || {};
    const warna = opts.warna === "violet" ? "var(--violet)" : "var(--cyan)";
    const tinggi = opts.tinggi || 220;
    const format = opts.format || QS.rupiah;
    if (!el || !data || data.length === 0) return;

    const maks = Math.max(1, ...data.map((d) => Number(d.nilai) || 0));
    const lebarBar = (100 / data.length).toFixed(4);

    el.innerHTML = data
      .map((d) => {
        const nilai = Number(d.nilai) || 0;
        const persen = (nilai / maks) * 100;
        return `
          <div class="flex-1 min-w-0 flex flex-col items-center gap-2" style="flex-basis:${lebarBar}%"
               title="${QS.escapeHtml(d.label)}: ${format(nilai)}">
            <div class="w-full flex items-end justify-center" style="height:${tinggi}px;">
              <div class="rounded-md w-full"
                   style="height:${Math.max(2, persen).toFixed(2)}%;
                          background: linear-gradient(180deg, ${warna}, rgba(${
                            opts.warna === "violet" ? "139,124,246" : "79,209,224"
                          } / 0.25));
                          opacity:${nilai > 0 ? 0.92 : 0.25};
                          transition: height 300ms var(--ease), opacity 200ms var(--ease);"></div>
            </div>
            <span class="text-muted" style="font-size: 11px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;">
              ${QS.escapeHtml(d.label)}
            </span>
          </div>`;
      })
      .join("");
  };

  /**
   * QS.lineChart(el, data, opts) -> grafik garis untuk tren pendapatan.
   */
  QS.lineChart = function (el, data, opts) {
    opts = opts || {};
    if (!el || !data || data.length < 2) return;

    const w = 100, h = 40;
    const maks = Math.max(1, ...data.map((d) => Number(d.nilai) || 0));
    const titik = data.map((d, i) => {
      const x = (i / (data.length - 1)) * w;
      const y = h - ((Number(d.nilai) || 0) / maks) * h;
      return `${x.toFixed(2)},${y.toFixed(2)}`;
    });

    el.innerHTML = `
      <svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" style="width:100%;height:${opts.tinggi || 160}px;display:block;">
        <defs>
          <linearGradient id="qsArea" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="var(--cyan)" stop-opacity="0.22"/>
            <stop offset="1" stop-color="var(--cyan)" stop-opacity="0"/>
          </linearGradient>
        </defs>
        <polygon points="0,${h} ${titik.join(" ")} ${w},${h}" fill="url(#qsArea)"></polygon>
        <polyline points="${titik.join(" ")}" fill="none" stroke="var(--cyan)" stroke-width="0.8"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
      </svg>`;
  };

  /* ---------------- Helper AJAX ketersediaan jadwal ---------------- */

  /**
   * Ambil JSON dari endpoint aplikasi. Selalu memakai fetch + credentials.
   */
  QS.getJSON = function (url) {
    return fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" }, credentials: "same-origin" })
      .then((r) => {
        if (!r.ok) throw new Error("Respons server tidak valid (" + r.status + ")");
        return r.json();
      });
  };

  /* ---------------- Pencarian langsung pada tabel ---------------- */

  QS.filterTable = function (inputEl, tableEl) {
    const kata = (inputEl.value || "").toLowerCase().trim();
    tableEl.querySelectorAll("tbody tr").forEach((tr) => {
      tr.hidden = kata !== "" && !tr.textContent.toLowerCase().includes(kata);
    });
  };

  document.addEventListener("input", (e) => {
    const target = e.target.closest("[data-table-filter]");
    if (!target) return;
    const table = document.querySelector(target.getAttribute("data-table-filter"));
    if (table) QS.filterTable(target, table);
  });
})();
