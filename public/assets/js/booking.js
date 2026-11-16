/* =====================================================================
   booking.js — Logika client-side form pemesanan.
   1. Muat ulang slot jam dari /jadwal/{unit_id} saat unit/tanggal/durasi berubah.
   2. Muat ulang total harga dari /harga agar ringkasan selalu sinkron.
   3. Blok submit selama jam mulai belum dipilih.
   Hanya memakai helper QS dari public/assets/js/app.js (vanilla, tanpa library).
   ===================================================================== */
(function () {
  "use strict";

  const form = document.getElementById("formBooking");
  if (!form) return;

  const urlJadwal = form.dataset.jadwalUrl;
  const urlHarga = form.dataset.hargaUrl;

  const elUnit = form.querySelector('[name="unit"]');
  const elTanggal = form.querySelector('[name="tanggal"]');
  const elDurasi = form.querySelector('[name="durasi"]');
  const elController = form.querySelector('[name="controller"]');
  const elJam = document.getElementById("start_time");
  const grid = document.getElementById("slotGrid");
  const slotInfo = document.getElementById("slotInfo");
  const slotPesan = document.getElementById("slotPesan");
  const panelRekening = document.getElementById("panelRekening");
  const tombolKirim = document.getElementById("tombolKirim");

  const ringkas = {
    unit: document.getElementById("ringkasanUnit"),
    tanggal: document.getElementById("ringkasanTanggal"),
    tarif: document.getElementById("ringkasanTarif"),
    rincian: document.getElementById("ringkasanRincian"),
    subtotal: document.getElementById("ringkasanSubtotal"),
    diskon: document.getElementById("ringkasanDiskon"),
    total: document.getElementById("ringkasanTotal"),
    badge: document.getElementById("badgeUnitStatus"),
    info: document.getElementById("unitInfo"),
    operasional: document.getElementById("operasionalInfo"),
  };

  /* Peta status -> kelas badge (harus sama dengan status_class() di PHP). */
  const KELAS_BADGE = {
    tersedia: "badge badge-success",
    dipakai: "badge badge-info",
    dipesan: "badge badge-violet",
    maintenance: "badge badge-warning",
  };
  const LABEL_STATUS = {
    tersedia: "Tersedia",
    dipakai: "Dipakai",
    dipesan: "Dipesan",
    maintenance: "Maintenance",
  };

  /* Data ringan unit (kode, tipe, tarif) dirender server ke dalam <script type="application/json">. */
  let daftarUnit = {};
  try {
    const mentah = JSON.parse(document.getElementById("dataUnit")?.textContent || "[]");
    daftarUnit = Object.fromEntries(mentah.map((u) => [u.id, u]));
  } catch (err) {
    daftarUnit = {};
  }

  const nilai = (el, defaultKosong = "") => (el ? String(el.value ?? defaultKosong) : defaultKosong);

  function paketTerpilih() {
    const aktif = form.querySelector('[name="paket"]:checked');
    return aktif ? aktif.value : "";
  }

  function metodeTerpilih() {
    const aktif = form.querySelector('[name="metode"]:checked');
    return aktif ? aktif.value : "";
  }

  function jamLabel(hhmm) {
    return String(hhmm).replace(":", ".");
  }

  function jamOperasionalText(buka, tutup) {
    const fmt = (h) => (Number(h) === 24 ? "24.00" : QS.pad(h) + ".00");
    return "Operasional " + fmt(buka) + " - " + fmt(tutup);
  }

  function tampilkanPesan(teks) {
    if (!slotPesan) return;
    slotPesan.textContent = teks || "";
    slotPesan.style.display = teks ? "block" : "none";
  }

  function ubahInfoPilihan() {
    if (!slotInfo) return;
    if (!elJam.value) {
      slotInfo.innerHTML = "Klik salah satu jam di atas untuk memulai pesanan.";
      return;
    }
    const lama = Number(nilai(elDurasi, "1")) || 1;
    slotInfo.innerHTML =
      "Mulai pukul <strong>" + QS.escapeHtml(jamLabel(elJam.value)) + "</strong> selama " +
      lama + " jam.";
  }

  /* ---------------- Render ulang grid slot ---------------- */

  function renderSlot(slots) {
    if (!grid) return;

    if (!slots || slots.length === 0) {
      grid.innerHTML =
        '<p class="text-secondary" style="margin:0; font-size: var(--text-sm);">' +
        "Belum ada slot untuk tanggal ini. Ubah tanggal atau durasi sewa Anda.</p>";
      elJam.value = "";
      return;
    }

    const terpilih = elJam.value;
    let masihAda = false;

    grid.innerHTML = slots
      .map((s) => {
        const kelas = ["slot"];
        if (!s.tersedia) kelas.push(s.alasan === "lewat" ? "is-past" : "is-taken");
        if (s.tersedia && s.value === terpilih) {
          kelas.push("is-selected");
          masihAda = true;
        }
        return (
          '<button type="button" class="' + kelas.join(" ") + '" data-value="' + QS.escapeHtml(s.value) + '"' +
          (s.tersedia ? "" : " disabled") + ' title="' +
          QS.escapeHtml(s.tersedia ? "Pesan mulai jam " + s.label : s.alasan === "lewat" ? "Sudah terlewat" : "Sudah terisi pesanan lain") +
          '">' + QS.escapeHtml(s.label) + "</button>"
        );
      })
      .join("");

    if (!masihAda) elJam.value = "";
  }

  function pilihSlot(button) {
    grid.querySelectorAll(".slot.is-selected").forEach((b) => b.classList.remove("is-selected"));
    button.classList.add("is-selected");
    elJam.value = button.dataset.value;
    tampilkanPesan("");
    ubahInfoPilihan();
    /* Harga tidak menerima jam mulai sebagai masukan, jadi tidak perlu
       meminta ulang total saat slot yang berubah. */
  }

  if (grid) {
    grid.addEventListener("click", (e) => {
      const tombol = e.target.closest("button.slot");
      if (!tombol || tombol.disabled) return;
      pilihSlot(tombol);
    });
  }

  /* ---------------- Pengisi ringkasan ---------------- */

  function isiRingkasanUnit(data) {
    const u = daftarUnit[data.unit_id] || {};
    const status = data.status || u.status || "tersedia";

    if (ringkas.badge) {
      ringkas.badge.className = KELAS_BADGE[status] || "badge badge-neutral";
      ringkas.badge.textContent = LABEL_STATUS[status] || status;
    }
    if (ringkas.info && u.kode) {
      ringkas.info.textContent =
        u.kode + " — tarif " + QS.rupiah(u.harga || data.harga_per_jam) + " per jam · " + (LABEL_STATUS[status] || status);
    }
    if (ringkas.unit) ringkas.unit.textContent = u.kode || data.kode_unit || "";
    if (ringkas.tarif) ringkas.tarif.textContent = QS.rupiah(data.harga_per_jam || u.harga || 0) + "/jam";
    if (ringkas.operasional) ringkas.operasional.textContent = jamOperasionalText(data.buka, data.tutup);
  }

  function isiRingkasanBiaya(h) {
    if (ringkas.rincian) {
      ringkas.rincian.innerHTML = (h.rincian || [])
        .map(
          (r) =>
            '<li class="flex items-start justify-between gap-3 py-2" style="border-bottom: 1px solid var(--border-soft);">' +
            '<span class="text-secondary" style="font-size: var(--text-sm);">' + QS.escapeHtml(r.label) + "</span>" +
            '<span class="mono" style="font-size: var(--text-sm); white-space:nowrap;">' +
            QS.rupiah(r.nilai).replace("Rp-", "-Rp") +
            "</span></li>"
        )
        .join("");
    }
    if (ringkas.subtotal) ringkas.subtotal.textContent = QS.rupiah(h.subtotal);
    if (ringkas.diskon) ringkas.diskon.textContent = QS.rupiah(h.diskon);
    if (ringkas.total) ringkas.total.textContent = QS.rupiah(h.total);
  }

  /* ---------------- Pengambilan data ---------------- */

  function muatJadwal() {
    if (!elUnit || !urlJadwal) return;
    const params = new URLSearchParams({
      tanggal: nilai(elTanggal),
      durasi: nilai(elDurasi, "1"),
    });
    tampilkanPesan("");

    QS.getJSON(urlJadwal + "/" + encodeURIComponent(elUnit.value) + "?" + params.toString())
      .then((data) => {
        renderSlot(data.slots || []);
        isiRingkasanUnit(data);
        ubahInfoPilihan();
      })
      .catch(() => {
        tampilkanPesan("Ketersediaan jadwal gagal dimuat. Muat ulang halaman, atau pilih unit lain.");
      });
  }

  function muatHarga() {
    if (!elUnit || !urlHarga) return;
    const params = new URLSearchParams({
      unit_id: elUnit.value,
      tanggal: nilai(elTanggal),
      durasi: nilai(elDurasi, "1"),
      controller: nilai(elController, "0"),
      package_id: paketTerpilih(),
    });

    QS.getJSON(urlHarga + "?" + params.toString())
      .then((data) => {
        isiRingkasanBiaya(data);
        if (ringkas.tanggal) ringkas.tanggal.textContent = tanggalLabel(data.tanggal);
      })
      .catch(() => {
        tampilkanPesan("Perkiraan total gagal dimuat. Total final tetap dihitung saat pesanan dikirim.");
      });
  }

  /* Label tanggal Indonesia tanpa library: cukup "16 November 2026". */
  const BULAN = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
  function tanggalLabel(iso) {
    const parts = String(iso || "").split("-");
    if (parts.length !== 3) return "";
    return Number(parts[2]) + " " + (BULAN[Number(parts[1]) - 1] || parts[1]) + " " + parts[0];
  }

  /* ---------------- Pemicu (semua memakai debounce ~250ms) ---------------- */

  const segarkanJadwal = QS.debounce(() => {
    muatJadwal();
    muatHarga();
  }, 250);
  const segarkanHarga = QS.debounce(muatHarga, 250);

  [elUnit, elTanggal, elDurasi].forEach((el) => {
    if (el) el.addEventListener("change", segarkanJadwal);
  });
  if (elController) elController.addEventListener("input", segarkanHarga);

  form.querySelectorAll('[name="paket"]').forEach((r) => r.addEventListener("change", segarkanHarga));

  /* Panel rekening hanya untuk metode transfer. */
  function sinkronMetode() {
    if (!panelRekening) return;
    panelRekening.hidden = metodeTerpilih() !== "transfer";
  }
  form.querySelectorAll('[name="metode"]').forEach((r) => r.addEventListener("change", sinkronMetode));
  sinkronMetode();

  /* ---------------- Pengiriman ---------------- */

  form.addEventListener("submit", (e) => {
    if (!elJam.value) {
      e.preventDefault();
      tampilkanPesan("Pilih dulu jam mulai di daftar slot di atas sebelum mengirim pesanan.");
      if (grid) {
        const pertama = grid.querySelector("button.slot:not([disabled])");
        if (pertama) pertama.focus();
        grid.scrollIntoView({ behavior: "smooth", block: "center" });
      }
      return;
    }
    if (tombolKirim) {
      tombolKirim.disabled = true;
      tombolKirim.textContent = "Mengirim pesanan…";
      // Kembalikan tombol bila halaman dibuka kembali dari riwayat (bfcache)
      window.addEventListener("pageshow", () => {
        tombolKirim.disabled = false;
        tombolKirim.textContent = "Pesan & Kunci Jadwal";
      }, { once: true });
    }
  });

  ubahInfoPilihan();
})();
