<?php
require_once 'includes/fungsi.php';
wajibLogin();

$pesan = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_akun'])) {
    $nama = trim($_POST['nama_santri']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $sekolah = trim($_POST['sekolah']);

    if (!empty($nama) && !empty($username) && !empty($password)) {
        $cek = $pdo->prepare("SELECT id FROM pengguna WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetch()) {
            $error = "Username '$username' sudah digunakan oleh santri lain!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO pengguna (nama_santri, username, password, sekolah) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$nama, $username, $password_hash, $sekolah])) {
                $pesan = "Akun santri baru berhasil ditambahkan!";
            } else {
                $error = "Gagal menambahkan akun.";
            }
        }
    } else {
        $error = "Nama, username, dan password wajib diisi!";
    }
}

if (isset($_GET['hapus']) && is_numeric($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    if ($id_hapus != (int)$_SESSION['pengguna_id']) {
        $pdo->prepare("DELETE FROM riwayat_poin WHERE pengguna_id = ?")->execute([$id_hapus]);
        $pdo->prepare("DELETE FROM catatan_shalat WHERE pengguna_id = ?")->execute([$id_hapus]);
        $pdo->prepare("DELETE FROM jadwal_harian WHERE pengguna_id = ?")->execute([$id_hapus]);
        $pdo->prepare("DELETE FROM tugas WHERE pengguna_id = ?")->execute([$id_hapus]);

        $stmt = $pdo->prepare("DELETE FROM pengguna WHERE id = ?");
        if ($stmt->execute([$id_hapus])) {
            $pesan = "Akun santri berhasil dihapus.";
        } else {
            $error = "Gagal menghapus akun.";
        }
    } else {
        $error = "Anda tidak dapat menghapus akun yang sedang aktif digunakan!";
    }
}

$hari_ini = date('Y-m-d');

$total_akun = $pdo->query("SELECT COUNT(*) FROM pengguna")->fetchColumn();

$total_shalat = $pdo->prepare("SELECT COUNT(*) FROM catatan_shalat WHERE tanggal = ?");
$total_shalat->execute([$hari_ini]);
$jml_shalat_hari_ini = $total_shalat->fetchColumn();

$stmt_santri = $pdo->query("
    SELECT p.*,
           COALESCE(SUM(rp.jumlah_poin), 0) AS total_poin
    FROM pengguna p
    LEFT JOIN riwayat_poin rp ON p.id = rp.pengguna_id
    GROUP BY p.id
    ORDER BY total_poin DESC
");
$daftar_santri = $stmt_santri->fetchAll(PDO::FETCH_ASSOC);

renderHeader('Admin - Pemantauan Santri');
?>

<style>
    .donut-akun-ring {
        width: 58px;
        height: 58px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    .donut-akun-hole {
        width: 42px;
        height: 42px;
        border-radius: 50%;
        background-color: #ffffff;
        color: #1b2620;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.75rem;
    }
    [data-theme="dark"] .donut-akun-hole {
        background-color: #1b1f24;
        color: #eef2f6;
    }
    .mini-bar-wrap {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        gap: 6px;
        align-items: end;
        padding: 10px 8px 6px;
        border-radius: 12px;
        background: rgba(9, 95, 31, 0.04);
        border: 1px solid rgba(9, 95, 31, 0.1);
        margin-top: 8px;
    }
    [data-theme="dark"] .mini-bar-wrap {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.08);
    }
    .mini-bar-col {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .mini-bar-pct {
        font-size: 0.65rem;
        font-weight: 700;
        margin-bottom: 3px;
    }
    .mini-bar-track {
        width: 100%;
        max-width: 24px;
        height: 46px;
        border-radius: 6px 6px 3px 3px;
        background: rgba(148, 163, 184, 0.22);
        display: flex;
        align-items: flex-end;
        overflow: hidden;
    }
    .mini-bar-fill {
        width: 100%;
        border-radius: 5px 5px 2px 2px;
        min-height: 3px;
    }
    .mini-bar-day {
        font-size: 0.65rem;
        font-weight: 600;
        margin-top: 4px;
    }
</style>

<div class="container my-4">
    <div class="p-4 mb-4 text-white shadow-sm rounded-4 position-relative overflow-hidden" style="background-color: #095f1f;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge bg-white text-success px-3 py-2 rounded-pill fw-bold mb-2 shadow-sm">
                    ADMIN • MONITORING LENGKAP
                </span>
                <h2 class="fw-bold mb-1">Dashboard Pemantauan Santri</h2>
                <p class="mb-0 opacity-90 small">Pantau progres jadwal & tugas harian, mingguan, bulanan, diagram persentase & batang mingguan, serta kelola akun santri.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-light fw-semibold px-3 py-2 rounded-pill">
                    <i class="bi bi-house-door-fill me-1"></i> Beranda & Fitur
                </a>
                <button type="button" class="btn btn-warning fw-bold px-4 py-2 rounded-pill text-dark shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahAkun">
                    <i class="bi bi-person-plus-fill me-1"></i> Tambah Akun Santri
                </button>
            </div>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-1"></i> <?= htmlspecialchars($pesan) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 p-3 rounded-4 shadow-sm h-100">
                <small class="text-muted fw-bold">Total Akun Santri</small>
                <h3 class="fw-bold mt-1 mb-0 text-success"><?= (int)$total_akun ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 p-3 rounded-4 shadow-sm h-100">
                <small class="text-muted fw-bold">Total Shalat Hari Ini (Semua Santri)</small>
                <h3 class="fw-bold mt-1 mb-0 text-primary"><?= (int)$jml_shalat_hari_ini ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 p-3 rounded-4 shadow-sm h-100">
                <small class="text-muted fw-bold">Periode Pemantauan</small>
                <h5 class="fw-bold mt-1 mb-0 text-dark"><?= date('d M Y') ?></h5>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-1 text-success"></i> Daftar Akun, Progres & Diagram Persentase</h5>
                <small class="text-muted">Menampilkan progres jadwal & tugas serta diagram batang penyelesaian tugas Senin–Minggu tiap akun.</small>
            </div>
            <span class="badge bg-dark rounded-pill px-3 py-2">Mode Admin</span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Santri & Pangkat</th>
                        <th>Username / Sekolah</th>
                        <th>Total Poin</th>
                        <th>Diagram</th>
                        <th>Harian</th>
                        <th>Mingguan</th>
                        <th>Bulanan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($daftar_santri) > 0): ?>
                        <?php foreach ($daftar_santri as $s):
                            $tp = (int)$s['total_poin'];
                            $p_q = $pdo->prepare("SELECT nama_pangkat FROM daftar_pangkat WHERE minimal_poin <= ? ORDER BY minimal_poin DESC LIMIT 1");
                            $p_q->execute([$tp]);
                            $pangkat_nama = $p_q->fetchColumn() ?: 'Pemula';

                            $periode = hitungPersentasePeriodeJadwalTugas($pdo, (int)$s['id']);
                            $p_hari = $periode['hari'];
                            $p_minggu = $periode['minggu'];
                            $p_bulan = $periode['bulan'];

                            $batang_mingguan = hitungProgresTugasMingguan($pdo, (int)$s['id']);

                            $persen_utama = max(0, min(100, (int)($p_hari['persen'] ?? 0)));
                            $sudut = round(($persen_utama / 100) * 360, 1);
                            $warna = $persen_utama >= 75 ? '#16a34a' : ($persen_utama >= 40 ? '#eab308' : '#095f1f');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($s['nama_santri']) ?></div>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill small">
                                    🏅 <?= htmlspecialchars($pangkat_nama) ?>
                                </span>
                            </td>
                            <td>
                                <div class="small fw-semibold text-secondary">@<?= htmlspecialchars($s['username']) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($s['sekolah'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="fw-bold text-success"><?= $tp ?> Poin</span>
                            </td>
                            <td>
                                <div class="donut-akun-ring" style="background: conic-gradient(<?= $warna ?> 0deg <?= $sudut ?>deg, rgba(148, 163, 184, 0.22) <?= $sudut ?>deg 360deg);">
                                    <div class="donut-akun-hole"><?= $persen_utama ?>%</div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small"><?= (int)$p_hari['persen'] ?>%</div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= (int)$p_hari['selesai'] ?>/<?= (int)$p_hari['total'] ?> aktivitas</div>
                                <div class="progress mt-1" style="height: 5px; width: 85px;">
                                    <div class="progress-bar bg-success" style="width: <?= (int)$p_hari['persen'] ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small"><?= (int)$p_minggu['persen'] ?>%</div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= (int)$p_minggu['selesai'] ?>/<?= (int)$p_minggu['total'] ?> aktivitas</div>
                                <div class="progress mt-1" style="height: 5px; width: 85px;">
                                    <div class="progress-bar bg-primary" style="width: <?= (int)$p_minggu['persen'] ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark small"><?= (int)$p_bulan['persen'] ?>%</div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?= (int)$p_bulan['selesai'] ?>/<?= (int)$p_bulan['total'] ?> aktivitas</div>
                                <div class="progress mt-1" style="height: 5px; width: 85px;">
                                    <div class="progress-bar bg-info" style="width: <?= (int)$p_bulan['persen'] ?>%"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <a href="admin.php?hapus=<?= (int)$s['id'] ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('Yakin ingin menghapus akun santri <?= htmlspecialchars($s['nama_santri'], ENT_QUOTES) ?>? Semua data terkait akan ikut terhapus.')">
                                    <i class="bi bi-trash-fill"></i> Hapus
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="8" class="pt-0 pb-3 border-top-0">
                                <div class="d-flex justify-content-between align-items-center small text-muted px-1">
                                    <span><i class="bi bi-bar-chart-steps me-1 text-success"></i> Diagram Batang Tugas (Sen–Min): <strong><?= htmlspecialchars($s['nama_santri']) ?></strong></span>
                                    <span><?= date('d M', strtotime('monday this week')) ?> – <?= date('d M Y', strtotime('sunday this week')) ?></span>
                                </div>
                                <div class="mini-bar-wrap">
                                    <?php
                                    $prev_b = null;
                                    foreach ($batang_mingguan as $b):
                                        $bpct = (int)$b['persen'];
                                        $btinggi = $b['total'] > 0 ? max(10, $bpct) : 6;
                                        if ($b['total'] === 0) {
                                            $bwarna = 'rgba(148, 163, 184, 0.45)';
                                        } elseif ($prev_b !== null && $bpct > $prev_b) {
                                            $bwarna = '#16a34a';
                                        } elseif ($prev_b !== null && $bpct < $prev_b) {
                                            $bwarna = '#ef4444';
                                        } else {
                                            $bwarna = '#095f1f';
                                        }
                                        $prev_b = $bpct;
                                    ?>
                                    <div class="mini-bar-col" title="<?= htmlspecialchars($b['hari']) ?>: <?= $b['selesai'] ?>/<?= $b['total'] ?> tugas selesai (<?= $bpct ?>%)">
                                        <div class="mini-bar-pct"><?= $bpct ?>%</div>
                                        <div class="mini-bar-track">
                                            <div class="mini-bar-fill" style="height: <?= $btinggi ?>%; background-color: <?= $bwarna ?>;"></div>
                                        </div>
                                        <div class="mini-bar-day"><?= htmlspecialchars(substr($b['hari'], 0, 3)) ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">Belum ada akun santri yang terdaftar.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalTambahAkun" tabindex="-1" aria-labelledby="modalTambahAkunLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-success text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalTambahAkunLabel"><i class="bi bi-person-plus-fill me-1"></i> Tambah Akun Santri Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Nama Lengkap Santri</label>
                        <input type="text" name="nama_santri" class="form-control rounded-3" placeholder="Contoh: Ahmad Fauzi" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Username (untuk Login)</label>
                        <input type="text" name="username" class="form-control rounded-3" placeholder="Contoh: fauzi2026" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control rounded-3" placeholder="Masukkan password akun" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Asal Sekolah Luar</label>
                        <input type="text" name="sekolah" class="form-control rounded-3" placeholder="Contoh: SMKN 1 Bangkalan">
                    </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_akun" class="btn btn-success rounded-pill px-4 fw-bold">Simpan Akun</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php renderBottomNav('admin'); ?>