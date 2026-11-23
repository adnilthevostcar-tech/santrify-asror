<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];
cekJadwalWajib($pdo, $uid);

$hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i');

// Simpan Catatan Cepat langsung dari Beranda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_cepat'])) {
    $isi = trim($_POST['isi_cepat']);
    if ($isi) {
        $pdo->prepare("INSERT INTO catatan_cepat (pengguna_id, isi, tipe) VALUES (?, ?, 'Cepat')")->execute([$uid, $isi]);
    }
    header("Location: index.php");
    exit;
}

$progres = hitungProgres($pdo, $uid);
$periode_ring = hitungPersentasePeriodeJadwalTugas($pdo, (int)$uid);
$tugas_mingguan = hitungProgresTugasMingguan($pdo, (int)$uid);

// Total Poin & Pangkat
$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah_poin), 0) FROM riwayat_poin WHERE pengguna_id = ?");
$stmt->execute([$uid]);
$total_poin = (int)$stmt->fetchColumn();

$pangkat_aktif = $pdo->prepare("SELECT * FROM daftar_pangkat WHERE minimal_poin <= ? ORDER BY minimal_poin DESC LIMIT 1");
$pangkat_aktif->execute([$total_poin]);
$pangkat = $pangkat_aktif->fetch();

$pangkat_next = $pdo->prepare("SELECT * FROM daftar_pangkat WHERE minimal_poin > ? ORDER BY minimal_poin ASC LIMIT 1");
$pangkat_next->execute([$total_poin]);
$next = $pangkat_next->fetch();

// Cek Evaluasi Harian (pengingat setelah 11.00)
$stmt = $pdo->prepare("SELECT id FROM evaluasi_harian WHERE pengguna_id = ? AND tanggal = ?");
$stmt->execute([$uid, $hari_ini]);
$sudah_evaluasi = (bool)$stmt->fetch();

// Agenda Berikutnya (belum selesai hari ini)
$stmt = $pdo->prepare("
    SELECT j.* FROM jadwal_harian j
    LEFT JOIN ceklist_kegiatan c ON j.id = c.jadwal_id AND c.tanggal = ?
    WHERE j.pengguna_id = ? AND j.status_aktif = 1 AND COALESCE(c.sudah_selesai, 0) = 0 AND j.jam_selesai >= ?
    ORDER BY j.jam_mulai ASC LIMIT 1
");
$stmt->execute([$hari_ini, $uid, $jam_sekarang]);
$agenda_next = $stmt->fetch();

// Maksimal 3 Tugas Aktif Terdekat
$stmt = $pdo->prepare("
    SELECT * FROM tugas WHERE pengguna_id = ? AND status != 'Selesai'
    ORDER BY CASE WHEN tenggat_waktu IS NULL THEN 1 ELSE 0 END, tenggat_waktu ASC LIMIT 3
");
$stmt->execute([$uid]);
$tugas_terdekat = $stmt->fetchAll();

// Status Shalat Hari Ini
$stmt = $pdo->prepare("SELECT jenis_shalat FROM catatan_shalat WHERE pengguna_id = ? AND tanggal = ?");
$stmt->execute([$uid, $hari_ini]);
$shalat_selesai = $stmt->fetchAll(PDO::FETCH_COLUMN);
$GLOBALS['shalat_selesai_hari_ini'] = $shalat_selesai;

renderHeader('Beranda');
?>

<style>
.donut-ring {
    width: 112px;
    height: 112px;
    border-radius: 50%;
    background: conic-gradient(#095f1f calc(var(--pct) * 1%), #e9ecef 0);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    position: relative;
}
.donut-ring::before {
    content: "";
    width: 82px;
    height: 82px;
    border-radius: 50%;
    background: #ffffff;
    position: absolute;
}
[data-theme="dark"] .donut-ring::before {
    background: #1b1f24;
}
.donut-label {
    position: relative;
    z-index: 1;
    font-weight: 700;
    color: #095f1f;
    font-size: 1.05rem;
}
[data-theme="dark"] .donut-label {
    color: #4ade80;
}
.balok-kurva-wrap {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 10px;
    align-items: end;
    min-height: 210px;
    padding: 16px 12px 10px;
    border-radius: 16px;
    background: rgba(9, 95, 31, 0.04);
    border: 1px solid rgba(9, 95, 31, 0.12);
}
[data-theme="dark"] .balok-kurva-wrap {
    background: rgba(255, 255, 255, 0.03);
    border-color: rgba(255, 255, 255, 0.08);
}
.balok-kolom {
    display: flex;
    flex-direction: column;
    align-items: center;
    height: 100%;
    justify-content: flex-end;
}
.balok-track {
    width: 100%;
    max-width: 44px;
    height: 135px;
    border-radius: 12px 12px 6px 6px;
    background: rgba(148, 163, 184, 0.2);
    display: flex;
    align-items: flex-end;
    overflow: hidden;
    position: relative;
}
.balok-fill {
    width: 100%;
    border-radius: 10px 10px 4px 4px;
    transition: height 0.35s ease;
    min-height: 4px;
}
.balok-persen {
    font-size: 0.78rem;
    font-weight: 700;
    margin-bottom: 6px;
}
.balok-hari {
    font-size: 0.8rem;
    font-weight: 700;
    margin-top: 8px;
}
.balok-sub {
    font-size: 0.7rem;
    opacity: 0.75;
}
</style>

<div class="container my-4">
    <!-- Hero Banner Modern (#095f1f) -->
    <div class="hero-banner p-4 p-md-5 mb-4 shadow-sm position-relative overflow-hidden" style="background-color: #095f1f; color: white; border-radius: 24px;">
        <div class="row align-items-center">
            <!-- Kolom Kiri: Sambutan & Tombol Aksi -->
            <div class="col-lg-7 mb-4 mb-lg-0">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <img src="img/al_asror-removebg-preview.png" alt="Logo Al-Asror" style="width: 40px; height: 40px; object-fit: cover; border-radius: 50%; background: white; padding: 2px;">
                    <span class="badge bg-white text-success px-3 py-2 rounded-pill fw-bold shadow-sm">
                        <?= date('l, d F Y') ?>
                    </span>
                </div>
                <h1 class="fw-bold display-6 mb-2">Assalamu’alaikum, <?= htmlspecialchars($_SESSION['nama_santri']) ?>!</h1>
                <!-- Teks Penyemangat Lebih Kontras -->
                <p class="mb-4 fs-6 fw-medium text-white opacity-100" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);">
                    “<?= htmlspecialchars(getPenyemangatHarian()) ?>”
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <a href="serah_hp.php" class="btn btn-warning fw-bold px-4 py-2 rounded-pill shadow-sm text-dark">
                        <i class="bi bi-phone-flip me-1"></i> Mode Serah HP
                    </a>
                    <a href="jadwal.php" class="btn btn-outline-light px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-calendar-check me-1"></i> Cek Jadwal
                    </a>
                    <a href="pengaturan.php" class="btn btn-outline-light px-4 py-2 rounded-pill fw-semibold">
                        <i class="bi bi-person-gear me-1"></i> Profil & Pengaturan
                    </a>
                </div>
            </div>

            <!-- Kolom Kanan: Gambar Diperkecil & Keterangan Aplikasi Terpisah -->
            <div class="col-lg-5 text-center">
                <!-- Wadah Gambar Kotak Persegi yang Lebih Kecil (180x180 px) -->
                <div class="p-2 rounded-4 shadow-lg mb-3 mx-auto" style="background: #ffffff !important; width: 180px; height: 180px; display: flex; align-items: center; justify-content: center;">
                    <img src="img/WhatsApp Image 2026-11-17 at 14.35.13.jpeg" alt="Ilustrasi Santri Belajar" class="rounded-3" style="width: 100%; height: 100%; object-fit: cover;">
                </div>

                <!-- Kotak Keterangan Aplikasi Terpisah di Bawah Gambar -->
                <div class="p-3 rounded-4 shadow-sm text-start mx-auto" style="background: #ffffff !important; color: #212529 !important; max-width: 280px;">
                    <div class="fw-bold mb-1 small" style="color: #095f1f !important;"><i class="bi bi-info-circle-fill me-1"></i> Santrify | ASROR</div>
                    <p class="mb-0" style="font-size: 0.75rem; color: #495057 !important; line-height: 1.3;">
                        Alhamdulillah aplikasi Santrify | Asror telah berhasil saya buat. Aplikasi menejemen jadwal, tugas & ibadah khusus santri Al-Asror yang sekolah di luar, menjaga kedisiplinan & murojaah. Semoga dengan adanya aplikasi ini dapat memberikan manfaat yang nyata bagi santri.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Kartu Pangkat & Poin (di bawah kotak hijau keterangan & di atas Menu Pengurus / Admin) -->
    <div class="card-asror card-accent p-4 mb-3">
        <div class="row align-items-center">
            <div class="col-md-7">
                <small class="text-muted fw-bold text-uppercase">Pangkat Saat Ini</small>
                <h3 class="fw-bold mb-1">🏅 <?= htmlspecialchars($pangkat['nama_pangkat'] ?? 'Pemula') ?></h3>
                <p class="mb-0 text-muted small">
                    Total Poin: <strong class="text-success"><?= $total_poin ?> Poin</strong>
                    <?php if ($next): ?>
- Kurang <strong><?= $next['minimal_poin'] - $total_poin ?> poin</strong> menuju <em><?= htmlspecialchars($next['nama_pangkat']) ?></em>
                    <?php else: ?>
- Alhamdulillah! Pangkat tertinggi telah tercapai. Tetap istiqamah!
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <a href="laporan.php" class="btn btn-ocean btn-sm">Lihat Pangkat & Riwayat Poin</a>
            </div>
        </div>
    </div>

    <!-- Menu Panel Admin -->
    <div class="alert alert-dark d-flex justify-content-between align-items-center rounded-4 shadow-sm mb-3" style="background: #093C5D; color: white;">
        <div>
            <strong>⚡ Menu Pengurus / Admin:</strong> Akses khusus pemantauan sistem dan rekapitulasi santri.
        </div>
        <a href="login_admin.php" class="btn btn-sm btn-warning fw-bold rounded-pill">Buka Panel Admin</a>
    </div>

    <!-- Banner Peringatan Shalat & Evaluasi -->
    <?php if (count($shalat_selesai) < 5): ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center rounded-4 shadow-sm mb-3">
        <div>
            <strong><i class="bi bi-bell-fill me-1"></i> Pengingat Shalat:</strong> 
            Baru <?= count($shalat_selesai) ?>/5 waktu shalat tercatat hari ini. Jaga shalat tepat waktu!
        </div>
        <a href="ibadah.php" class="btn btn-sm btn-dark rounded-pill">Cek Shalat</a>
    </div>
    <?php endif; ?>

    <?php if ($jam_sekarang >= '11:00' && !$sudah_evaluasi): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center rounded-4 shadow-sm mb-4">
        <div>
            <strong><i class="bi bi-journal-check me-1"></i> Evaluasi Harian:</strong> 
            Sudah lewat pukul 11.00, luangkan 1 menit untuk mengisi evaluasi harimu.
        </div>
        <a href="fokus.php#evaluasi" class="btn btn-sm btn-primary rounded-pill">Isi Evaluasi</a>
    </div>
    <?php endif; ?>

    <!-- Kartu Progres Harian, Mingguan, Bulanan -->
    <div class="row g-3 mb-4">
        <?php 
        $labels = ['hari' => 'Progres Hari Ini', 'minggu' => 'Minggu Ini (Sen-Min)', 'bulan' => 'Bulan Ini'];
        foreach ($labels as $key => $label): 
            $p = $progres[$key];
        ?>
        <div class="col-md-4">
            <div class="card-asror p-3 h-100">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <small class="text-muted fw-semibold"><?= $label ?></small>
                    <span class="fw-bold" style="color:var(--primary-blue);"><?= $p['persen'] ?>%</span>
                </div>
                <div class="progress progress-asror mb-2">
                    <div class="progress-bar" style="width: <?= $p['persen'] ?>%"></div>
                </div>
                <small class="text-muted"><?= $p['selesai'] ?>/<?= $p['total'] ?> aktivitas selesai</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Tabel & Lingkaran Persentase Jadwal & Tugas (1 Hari, 1 Minggu, 1 Bulan) -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color: #095f1f;">Persentase Jadwal & Tugas (1 Hari • 1 Minggu • 1 Bulan)</h5>

        <div class="row g-3 text-center mb-4">
            <div class="col-4">
                <div class="donut-ring" style="--pct: <?= (int)$periode_ring['hari']['persen'] ?>;">
                    <span class="donut-label"><?= (int)$periode_ring['hari']['persen'] ?>%</span>
                </div>
                <div class="fw-semibold small mt-2">1 Hari</div>
            </div>
            <div class="col-4">
                <div class="donut-ring" style="--pct: <?= (int)$periode_ring['minggu']['persen'] ?>;">
                    <span class="donut-label"><?= (int)$periode_ring['minggu']['persen'] ?>%</span>
                </div>
                <div class="fw-semibold small mt-2">1 Minggu</div>
            </div>
            <div class="col-4">
                <div class="donut-ring" style="--pct: <?= (int)$periode_ring['bulan']['persen'] ?>;">
                    <span class="donut-label"><?= (int)$periode_ring['bulan']['persen'] ?>%</span>
                </div>
                <div class="fw-semibold small mt-2">1 Bulan</div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Periode</th>
                        <th>Jadwal + Tugas Tuntas</th>
                        <th>Persentase</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold">1 Hari</td>
                        <td><?= (int)$periode_ring['hari']['selesai'] ?> / <?= (int)$periode_ring['hari']['total'] ?></td>
                        <td><span class="badge" style="background:#095f1f;"><?= (int)$periode_ring['hari']['persen'] ?>%</span></td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">1 Minggu</td>
                        <td><?= (int)$periode_ring['minggu']['selesai'] ?> / <?= (int)$periode_ring['minggu']['total'] ?></td>
                        <td><span class="badge bg-success"><?= (int)$periode_ring['minggu']['persen'] ?>%</span></td>
                    </tr>
                    <tr class="table-success fw-bold">
                        <td>1 Bulan</td>
                        <td><?= (int)$periode_ring['bulan']['selesai'] ?> / <?= (int)$periode_ring['bulan']['total'] ?></td>
                        <td><?= (int)$periode_ring['bulan']['persen'] ?>%</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Riwayat / Kurva Balok Penyelesaian Tugas (Senin - Minggu) di bawah Diagram Lingkaran -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #095f1f;">
                    <i class="bi bi-clock-history me-1"></i> Riwayat &amp; Kurva Balok Penyelesaian Tugas (Senin – Minggu)
                </h5>
                <small class="text-muted">Memantau riwayat dan arah tren penyelesaian tugas apakah semakin meningkat atau menurun.</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                Pekan Ini: <?= date('d M', strtotime('monday this week')) ?> – <?= date('d M Y', strtotime('sunday this week')) ?>
            </span>
        </div>

        <div class="balok-kurva-wrap mb-3">
            <?php
            $prev_persen = null;
            foreach ($tugas_mingguan as $h):
                $pct = (int)$h['persen'];
                $tinggi = $h['total'] > 0 ? max(8, $pct) : 4;
                if ($h['total'] === 0) {
                    $warna_balok = 'rgba(148, 163, 184, 0.45)';
                } elseif ($prev_persen !== null && $pct > $prev_persen) {
                    $warna_balok = '#16a34a';
                } elseif ($prev_persen !== null && $pct < $prev_persen) {
                    $warna_balok = '#ef4444';
                } else {
                    $warna_balok = '#095f1f';
                }
                $prev_persen = $pct;
            ?>
            <div class="balok-kolom">
                <div class="balok-persen"><?= $pct ?>%</div>
                <div class="balok-track" title="<?= htmlspecialchars($h['hari']) ?>: <?= $h['selesai'] ?>/<?= $h['total'] ?> tugas selesai (<?= $pct ?>%)">
                    <div class="balok-fill" style="height: <?= $tinggi ?>%; background-color: <?= $warna_balok ?>;"></div>
                </div>
                <div class="balok-hari">
                    <?= htmlspecialchars($h['hari']) ?>
                    <?php if ($h['is_today']): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.62rem;">Hari Ini</span>
                    <?php endif; ?>
                </div>
                <div class="balok-sub"><?= $h['selesai'] ?>/<?= $h['total'] ?> tugas</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th>Hari</th>
                        <th>Tanggal</th>
                        <th>Riwayat Tugas Selesai</th>
                        <th>Persentase</th>
                        <th>Arah Tren</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $prev = null;
                    foreach ($tugas_mingguan as $h):
                        $pct = (int)$h['persen'];
                        if ($h['total'] === 0) {
                            $tren_badge = '<span class="badge bg-secondary-subtle text-secondary">Tidak ada jadwal tugas</span>';
                        } elseif ($prev === null) {
                            $tren_badge = '<span class="badge bg-primary-subtle text-primary">Titik Awal Pekan</span>';
                        } elseif ($pct > $prev) {
                            $selisih = $pct - $prev;
                            $tren_badge = '<span class="badge bg-success-subtle text-success"><i class="bi bi-arrow-up-right"></i> Naik +' . $selisih . '%</span>';
                        } elseif ($pct < $prev) {
                            $selisih = $prev - $pct;
                            $tren_badge = '<span class="badge bg-danger-subtle text-danger"><i class="bi bi-arrow-down-right"></i> Menurun -' . $selisih . '%</span>';
                        } else {
                            $tren_badge = '<span class="badge bg-info-subtle text-info"><i class="bi bi-dash"></i> Stabil (0%)</span>';
                        }
                        $prev = $pct;
                    ?>
                    <tr class="<?= $h['is_today'] ? 'table-success fw-semibold' : '' ?>">
                        <td><?= htmlspecialchars($h['hari']) ?></td>
                        <td><?= date('d/m/Y', strtotime($h['tanggal'])) ?></td>
                        <td><?= (int)$h['selesai'] ?> / <?= (int)$h['total'] ?></td>
                        <td><?= $pct ?>%</td>
                        <td><?= $tren_badge ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <!-- Agenda Berikutnya & Tugas Terdekat -->
        <div class="col-lg-7">
            <div class="card-asror p-4 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-clock-history me-1"></i> Agenda Berikutnya</h5>
                    <a href="jadwal.php" class="small text-decoration-none">Semua Jadwal →</a>
                </div>
                <?php if ($agenda_next): ?>
                    <div class="p-3 rounded-3" style="background-color: rgba(111, 209, 215, 0.15);">
                        <span class="badge bg-primary mb-1"><?= substr($agenda_next['jam_mulai'],0,5) ?> - <?= substr($agenda_next['jam_selesai'],0,5) ?></span>
                        <span class="badge bg-secondary mb-1"><?= $agenda_next['kategori'] ?></span>
                        <h6 class="fw-bold mb-0 mt-1"><?= htmlspecialchars($agenda_next['nama_kegiatan']) ?></h6>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Tidak ada agenda tersisa hari ini atau semua sudah tuntas!</p>
                <?php endif; ?>
            </div>

            <div class="card-asror p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-check2-square me-1"></i> Tugas Terdekat (Maks. 3)</h5>
                    <a href="tugas.php" class="small text-decoration-none">Kelola Tugas →</a>
                </div>
                <?php if ($tugas_terdekat): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($tugas_terdekat as $t): ?>
                        <div class="list-group-item bg-transparent px-0 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold"><?= htmlspecialchars($t['judul_tugas']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($t['kategori']) ?> | Deadline: <?= $t['tenggat_waktu'] ?? 'Tanpa batas' ?></small>
                            </div>
                            <span class="badge <?= $t['prioritas']=='Tinggi'?'bg-danger':($t['prioritas']=='Sedang'?'bg-warning text-dark':'bg-info') ?>">
                                <?= $t['prioritas'] ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">Belum ada tugas aktif. Alhamdulillah!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Catatan Cepat -->
        <div class="col-lg-5">
            <div class="card-asror p-4 h-100">
                <h5 class="fw-bold mb-2"><i class="bi bi-lightning-charge-fill text-warning"></i> Catatan Cepat</h5>
                <p class="text-muted small">Catat info mendadak sebelum HP dikumpulkan. Bisa diubah jadi tugas nanti.</p>
                <form method="POST">
                    <textarea name="isi_cepat" class="form-control mb-3" rows="4" placeholder="Tulis pengingat cepat di sini..." required></textarea>
                    <button type="submit" name="simpan_cepat" class="btn btn-mint w-100">Simpan Catatan Kilat</button>
                </form>
                <div class="mt-3 text-center">
                    <a href="catatan_cepat.php" class="small text-decoration-none">Buka Semua Catatan & Cadangan Data →</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderBottomNav('beranda'); ?>