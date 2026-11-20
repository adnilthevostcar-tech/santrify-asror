<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];

// Tambah Catatan Baru (Cepat atau Pribadi)
if (isset($_POST['tambah_catatan'])) {
    $isi  = trim($_POST['isi']);
    $tipe = $_POST['tipe'];
    if ($isi) {
        $pdo->prepare("INSERT INTO catatan_cepat (pengguna_id, isi, tipe) VALUES (?, ?, ?)")->execute([$uid, $isi, $tipe]);
    }
    header("Location: catatan_cepat.php");
    exit;
}

// Konversi Catatan Cepat menjadi Tugas (Sesuai PRD v1.1 Bagian 8.7)
if (isset($_GET['jadi_tugas'])) {
    $cid = (int)$_GET['jadi_tugas'];
    $stmt = $pdo->prepare("SELECT * FROM catatan_cepat WHERE id = ? AND pengguna_id = ?");
    $stmt->execute([$cid, $uid]);
    $cat = $stmt->fetch();
    if ($cat) {
        $pdo->prepare("INSERT INTO tugas (pengguna_id, judul_tugas, kategori, prioritas, poin) VALUES (?, ?, 'Dari Catatan', 'Sedang', 15)")
            ->execute([$uid, $cat['isi']]);
        $pdo->prepare("DELETE FROM catatan_cepat WHERE id = ?")->execute([$cid]);
    }
    header("Location: tugas.php");
    exit;
}

// Hapus Catatan
if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM catatan_cepat WHERE id = ? AND pengguna_id = ?")->execute([(int)$_GET['hapus'], $uid]);
    header("Location: catatan_cepat.php");
    exit;
}

// Ekspor Cadangan Data ke Format JSON (Sesuai PRD v1.1 Bagian 8.9)
if (isset($_GET['backup'])) {
    $data = [
        'versi_skema'      => '1.2',
        'tanggal_cadangan' => date('Y-m-d H:i:s'),
        'jadwal'           => $pdo->query("SELECT * FROM jadwal_harian WHERE pengguna_id = $uid")->fetchAll(),
        'tugas'            => $pdo->query("SELECT * FROM tugas WHERE pengguna_id = $uid")->fetchAll(),
        'hafalan'          => $pdo->query("SELECT * FROM catatan_hafalan WHERE pengguna_id = $uid")->fetchAll(),
        'catatan'          => $pdo->query("SELECT * FROM catatan_cepat WHERE pengguna_id = $uid")->fetchAll(),
    ];
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="cadangan_asror_' . date('Ymd') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Ambil daftar catatan
$catatan = $pdo->prepare("SELECT * FROM catatan_cepat WHERE pengguna_id = ? ORDER BY id DESC");
$catatan->execute([$uid]);
$list_catatan = $catatan->fetchAll();

renderHeader('Catatan & Cadangan');
?>

<div class="container my-4">
    <div class="row g-4">
        <!-- Kolom Kiri: Form Input & Tombol Backup -->
        <div class="col-md-5">
            <div class="card-asror p-4 mb-4">
                <h5 class="fw-bold mb-3">✍️ Tulis Catatan Baru</h5>
                <form method="POST">
                    <div class="mb-2">
                        <select name="tipe" class="form-select">
                            <option value="Cepat">Catatan Cepat</option>
                            <option value="Pribadi">Catatan Pribadi</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <textarea name="isi" class="form-control" rows="4" placeholder="Tulis catatan pribadi atau pengingat penting..." required></textarea>
                    </div>
                    <button type="submit" name="tambah_catatan" class="btn btn-mint w-100">Simpan Catatan</button>
                </form>
            </div>

            <!-- Fitur Cadangan Data JSON -->
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-2">💾 Cadangan Data</h5>
                <p class="text-muted small">Unduh seluruh data jadwal, tugas, hafalan, dan catatanmu dalam format JSON agar aman.</p>
                <a href="catatan_cepat.php?backup=1" class="btn btn-ocean w-100"><i class="bi bi-download me-1"></i> Unduh Cadangan JSON</a>
            </div>
        </div>

        <!-- Kolom Kanan: Daftar Catatan -->
        <div class="col-md-7">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-3">Daftar Catatan Pribadi & Cepat</h5>
                <?php if ($list_catatan): ?>
                    <?php foreach ($list_catatan as $c): ?>
                    <div class="p-3 mb-3 rounded-3 border">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="badge <?= $c['tipe']=='Pribadi'?'bg-primary':'bg-warning text-dark' ?>"><?= $c['tipe'] ?></span>
                            <small class="text-muted"><?= $c['tanggal'] ?></small>
                        </div>
                        <p class="mb-2" style="white-space: pre-line;"><?= htmlspecialchars($c['isi']) ?></p>
                        <div class="d-flex gap-2">
                            <a href="catatan_cepat.php?jadi_tugas=<?= $c['id'] ?>" onclick="return confirm('Ubah catatan ini menjadi tugas?')" class="btn btn-sm btn-outline-success">Jadikan Tugas</a>
                            <a href="catatan_cepat.php?hapus=<?= $c['id'] ?>" onclick="return confirm('Hapus catatan ini?')" class="btn btn-sm btn-outline-danger">Hapus</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-4">Belum ada catatan tersimpan.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderBottomNav('catatan'); ?>