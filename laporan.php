<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(jumlah_poin), 0) FROM riwayat_poin WHERE pengguna_id = ?");
$stmt->execute([$uid]);
$total_poin = (int)$stmt->fetchColumn();

$pangkat_aktif = $pdo->prepare("SELECT * FROM daftar_pangkat WHERE minimal_poin <= ? ORDER BY minimal_poin DESC LIMIT 1");
$pangkat_aktif->execute([$total_poin]);
$pangkat_sekarang = $pangkat_aktif->fetch();

$daftar_pangkat = $pdo->query("SELECT * FROM daftar_pangkat ORDER BY urutan ASC")->fetchAll();

$riwayat = $pdo->prepare("SELECT * FROM riwayat_poin WHERE pengguna_id = ? ORDER BY tanggal_dibuat DESC LIMIT 20");
$riwayat->execute([$uid]);
$riwayat_list = $riwayat->fetchAll();

renderHeader('Pangkat & Poin');
?>

<div class="container my-4">
    <div class="hero-banner p-4 text-center mb-4">
        <small class="text-uppercase opacity-75">Pencapaian Pribadi</small>
        <h2 class="fw-bold my-1">🏅 <?= htmlspecialchars($pangkat_sekarang['nama_pangkat']) ?></h2>
        <p class="mb-0">Total Poin Sah: <strong class="text-warning"><?= $total_poin ?> Poin</strong></p>
    </div>

    <h5 class="fw-bold mb-3">6 Tingkatan Pangkat Santri</h5>
    <div class="row g-3 mb-4">
        <?php foreach ($daftar_pangkat as $p): 
            $aktif = ($p['id'] == $pangkat_sekarang['id']);
            $tercapai = ($total_poin >= $p['minimal_poin']);
        ?>
        <div class="col-6 col-md-4">
            <div class="card-asror p-3 h-100 <?= $aktif ? 'card-accent' : '' ?>" style="<?= !$tercapai ? 'opacity:0.65;' : '' ?>">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong class="small"><?= htmlspecialchars($p['nama_pangkat']) ?></strong>
                    <?php if ($aktif): ?><span class="badge bg-success">Aktif</span><?php endif; ?>
                </div>
                <small class="text-muted">Syarat: <?= $p['minimal_poin'] ?> Poin</small>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card-asror p-4">
        <h5 class="fw-bold mb-3">📜 Audit Riwayat Poin</h5>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Keterangan</th>
                        <th>Poin</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($riwayat_list): foreach ($riwayat_list as $r): ?>
                    <tr>
                        <td class="small text-muted"><?= $r['tanggal_dibuat'] ?></td>
                        <td><?= htmlspecialchars($r['keterangan']) ?></td>
                        <td><span class="badge bg-success">+<?= $r['jumlah_poin'] ?></span></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada riwayat poin.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php renderBottomNav('beranda'); ?>