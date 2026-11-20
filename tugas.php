<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];

if (isset($_POST['tambah_tugas'])) {
    $judul = trim($_POST['judul_tugas']);
    $kat   = trim($_POST['kategori']);
    $dl    = !empty($_POST['tenggat_waktu']) ? $_POST['tenggat_waktu'] : null;
    $prio  = $_POST['prioritas'];
    $poin  = (int)$_POST['poin'];

    if ($judul) {
        $pdo->prepare("INSERT INTO tugas (pengguna_id, judul_tugas, kategori, tenggat_waktu, prioritas, poin) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $judul, $kat, $dl, $prio, $poin]);
    }
    header("Location: tugas.php");
    exit;
}

if (isset($_GET['status']) && isset($_GET['id'])) {
    $tid = (int)$_GET['id'];
    $st  = $_GET['status'];
    $stmt = $pdo->prepare("SELECT * FROM tugas WHERE id = ? AND pengguna_id = ?");
    $stmt->execute([$tid, $uid]);
    $tugas = $stmt->fetch();

    if ($tugas && in_array($st, ['Belum', 'Proses', 'Selesai'])) {
        $pdo->prepare("UPDATE tugas SET status = ? WHERE id = ?")->execute([$st, $tid]);
        $sumber = "tugas_{$tid}";
        if ($st === 'Selesai') {
            tambahPoin($pdo, $uid, $tugas['poin'], "Tugas selesai: " . $tugas['judul_tugas'], $sumber);
        } else {
            batalPoin($pdo, $uid, $sumber);
        }
    }
    header("Location: tugas.php");
    exit;
}

if (isset($_GET['hapus'])) {
    $tid = (int)$_GET['hapus'];
    batalPoin($pdo, $uid, "tugas_{$tid}");
    $pdo->prepare("DELETE FROM tugas WHERE id = ? AND pengguna_id = ?")->execute([$tid, $uid]);
    header("Location: tugas.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT * FROM tugas WHERE pengguna_id = ?
    ORDER BY CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END,
             CASE WHEN tenggat_waktu IS NULL THEN 1 ELSE 0 END,
             tenggat_waktu ASC
");
$stmt->execute([$uid]);
$daftar_tugas = $stmt->fetchAll();

renderHeader('Tugas');
?>

<div class="container my-4">
    <div class="row g-4">
        <!-- Kolom Kiri: Form Tambah Tugas -->
        <div class="col-md-4">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-3">Tambah Tugas Baru</h5>
                <form method="POST">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Judul Tugas</label>
                        <input type="text" name="judul_tugas" class="form-control" placeholder="Contoh: Rangkuman Fiqih" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Mata Pelajaran / Kategori</label>
                        <input type="text" name="kategori" class="form-control" placeholder="Contoh: Sekolah / Pondok" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Tenggat Waktu (Opsional)</label>
                        <input type="date" name="tenggat_waktu" class="form-control">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Prioritas</label>
                            <select name="prioritas" class="form-select">
                                <option value="Rendah">Rendah</option>
                                <option value="Sedang" selected>Sedang</option>
                                <option value="Tinggi">Tinggi</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Poin</label>
                        <!-- Dikunci otomatis 10 poin dan tidak bisa diubah -->
                        <input type="hidden" name="poin" value="2">
                        <input type="text" class="form-control bg-light" value="2 Poin" readonly>
                    </div>
                    <button type="submit" name="tambah_tugas" class="btn btn-mint w-100">Simpan Tugas</button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Daftar Tugas -->
        <div class="col-md-8">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-3">Daftar Tugas Sekolah & Pondok</h5>
                <?php if ($daftar_tugas): ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Tugas</th>
                                    <th>Deadline</th>
                                    <th>Prioritas</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($daftar_tugas as $t): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold <?= $t['status']=='Selesai'?'text-decoration-line-through text-muted':'' ?>">
                                            <?= htmlspecialchars($t['judul_tugas']) ?>
                                        </div>
                                        <small class="text-muted"><?= htmlspecialchars($t['kategori']) ?> • <?= $t['poin'] ?> Poin</small>
                                    </td>
                                    <td class="small"><?= $t['tenggat_waktu'] ?? '<em>Tanpa deadline</em>' ?></td>
                                    <td>
                                        <?php if ($t['prioritas'] == 'Tinggi'): ?>
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle-fill"></i> Tinggi</span>
                                        <?php elseif ($t['prioritas'] == 'Sedang'): ?>
                                            <span class="badge bg-warning text-dark">Sedang</span>
                                        <?php else: ?>
                                            <span class="badge bg-info text-dark">Rendah</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="tugas.php?id=<?= $t['id'] ?>&status=Belum" class="btn <?= $t['status']=='Belum'?'btn-secondary':'btn-outline-secondary' ?>">Belum</a>
                                            <a href="tugas.php?id=<?= $t['id'] ?>&status=Proses" class="btn <?= $t['status']=='Proses'?'btn-primary':'btn-outline-primary' ?>">Proses</a>
                                            <a href="tugas.php?id=<?= $t['id'] ?>&status=Selesai" class="btn <?= $t['status']=='Selesai'?'btn-success':'btn-outline-success' ?>">Selesai</a>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="tugas.php?hapus=<?= $t['id'] ?>" onclick="return confirm('Hapus tugas ini?')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">Belum ada tugas tercatat.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderBottomNav('tugas'); ?>