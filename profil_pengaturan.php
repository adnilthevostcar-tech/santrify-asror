<?php
require_once 'includes/fungsi.php';
wajibLogin();

$uid = (int)$_SESSION['pengguna_id'];
$pesan = '';
$error = '';

// Ambil data pengguna
$stmt = $pdo->prepare("SELECT * FROM pengguna WHERE id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Total poin
$stmtP = $pdo->prepare("SELECT COALESCE(SUM(jumlah_poin), 0) FROM riwayat_poin WHERE pengguna_id = ?");
$stmtP->execute([$uid]);
$total_poin = (int)$stmtP->fetchColumn();

// Pangkat aktif (minimal_poin sudah 2x lipat)
$stmtPk = $pdo->prepare("SELECT * FROM daftar_pangkat WHERE minimal_poin <= ? ORDER BY minimal_poin DESC LIMIT 1");
$stmtPk->execute([$total_poin]);
$pangkat_aktif = $stmtPk->fetch(PDO::FETCH_ASSOC);
$nama_pangkat = $pangkat_aktif['nama_pangkat'] ?? 'Pemula';

// Katalog avatar (pangkat & toko poin)
$avatar_pangkat = [
    'pangkat_pemula'    => ['nama' => 'Avatar Pemula', 'syarat_poin' => 0,   'icon' => '🌱'],
    'pangkat_istiqamah' => ['nama' => 'Avatar Istiqamah', 'syarat_poin' => 40,  'icon' => '📗'],
    'pangkat_hafidz'    => ['nama' => 'Avatar Hafidz Muda', 'syarat_poin' => 100, 'icon' => '🌟'],
    'pangkat_teladan'   => ['nama' => 'Avatar Santri Teladan', 'syarat_poin' => 200, 'icon' => '👑'],
];

$avatar_toko = [
    'toko_kacamata' => ['nama' => 'Santri Cendekia', 'harga' => 20, 'icon' => '👓'],
    'toko_kopiah'   => ['nama' => 'Pejuang Subuh', 'harga' => 35, 'icon' => '🕌'],
    'toko_pena'     => ['nama' => 'Penulis Kitab', 'harga' => 50, 'icon' => '✒️'],
];

// Avatar yang sudah dibeli user
$stmtM = $pdo->prepare("SELECT kode_avatar FROM avatar_milik_pengguna WHERE pengguna_id = ?");
$stmtM->execute([$uid]);
$milik_toko = $stmtM->fetchAll(PDO::FETCH_COLUMN);

// Proses beli avatar toko
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['beli_avatar'])) {
    $kode = $_POST['kode_avatar'] ?? '';
    if (isset($avatar_toko[$kode]) && !in_array($kode, $milik_toko, true)) {
        $harga = (int)$avatar_toko[$kode]['harga'];
        if ($total_poin >= $harga) {
            $pdo->beginTransaction();
            try {
                tambahPoinSantri($pdo, $uid, -$harga, "Beli avatar: " . $avatar_toko[$kode]['nama']);
                $ins = $pdo->prepare("INSERT INTO avatar_milik_pengguna (pengguna_id, kode_avatar) VALUES (?, ?)");
                $ins->execute([$uid, $kode]);
                $pdo->commit();
                $pesan = "Avatar berhasil dibeli dan ditambahkan ke koleksimu!";
                header("Location: profil_pengaturan.php?ok=1");
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                $error = "Gagal memproses pembelian avatar.";
            }
        } else {
            $error = "Poin belum cukup untuk membeli avatar ini.";
        }
    }
}

// Proses pilih avatar aktif
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pakai_avatar'])) {
    $kode = $_POST['kode_avatar'] ?? '';
    $boleh_pakai = false;

    if (isset($avatar_pangkat[$kode]) && $total_poin >= (int)$avatar_pangkat[$kode]['syarat_poin']) {
        $boleh_pakai = true;
    } elseif (in_array($kode, $milik_toko, true)) {
        $boleh_pakai = true;
    }

    if ($boleh_pakai) {
        $upd = $pdo->prepare("UPDATE pengguna SET avatar_aktif = ? WHERE id = ?");
        $upd->execute([$kode, $uid]);
        $_SESSION['avatar_aktif'] = $kode;
        header("Location: profil_pengaturan.php?dipakai=1");
        exit;
    } else {
        $error = "Avatar belum terbuka atau belum kamu miliki.";
    }
}

// Proses simpan pengaturan profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_profil'])) {
    $nama_baru = trim($_POST['nama_santri'] ?? '');
    $sekolah_baru = trim($_POST['sekolah'] ?? '');
    if ($nama_baru !== '') {
        $upd = $pdo->prepare("UPDATE pengguna SET nama_santri = ?, sekolah = ? WHERE id = ?");
        $upd->execute([$nama_baru, $sekolah_baru, $uid]);
        $_SESSION['nama_santri'] = $nama_baru;
        header("Location: profil_pengaturan.php?profil=1");
        exit;
    } else {
        $error = "Nama santri tidak boleh kosong.";
    }
}

if (isset($_GET['ok'])) $pesan = "Avatar berhasil dibeli!";
if (isset($_GET['dipakai'])) $pesan = "Foto profil avatar berhasil diperbarui!";
if (isset($_GET['profil'])) $pesan = "Informasi profil berhasil disimpan!";

// Refresh poin & milik setelah redirect
$stmtP->execute([$uid]);
$total_poin = (int)$stmtP->fetchColumn();
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$stmtM->execute([$uid]);
$milik_toko = $stmtM->fetchAll(PDO::FETCH_COLUMN);

$avatar_aktif_kode = $user['avatar_aktif'] ?? 'pangkat_pemula';
$icon_aktif = $avatar_pangkat[$avatar_aktif_kode]['icon'] ?? ($avatar_toko[$avatar_aktif_kode]['icon'] ?? '🌱');

renderHeader('Profil & Pengaturan');
?>

<div class="container my-4">
    <div class="p-4 rounded-4 text-white mb-4 shadow-sm" style="background-color: #095f1f;">
        <div class="d-flex align-items-center gap-3">
            <div class="rounded-circle bg-white d-flex align-items-center justify-content-center shadow" style="width:72px;height:72px;font-size:2rem;">
                <?= $icon_aktif ?>
            </div>
            <div>
                <h3 class="fw-bold mb-1"><?= htmlspecialchars($user['nama_santri']) ?></h3>
                <div class="small opacity-90">
                    Pangkat: <strong><?= htmlspecialchars($nama_pangkat) ?></strong> • Poin: <strong><?= $total_poin ?></strong>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pesan): ?>
        <div class="alert alert-success rounded-4"><?= htmlspecialchars($pesan) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger rounded-4"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Pengaturan Profil -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color:#095f1f;">Pengaturan Profil</h5>
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">Nama Santri</label>
                <input type="text" name="nama_santri" class="form-control" value="<?= htmlspecialchars($user['nama_santri']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Asal Sekolah</label>
                <input type="text" name="sekolah" class="form-control" value="<?= htmlspecialchars($user['sekolah'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button type="submit" name="simpan_profil" class="btn text-white fw-bold rounded-pill px-4" style="background:#095f1f;">Simpan Profil</button>
            </div>
        </form>
    </div>

    <!-- Avatar Sesuai Pangkat -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color:#095f1f;">Foto Profil Sesuai Pangkat</h5>
        <div class="row g-3">
            <?php foreach ($avatar_pangkat as $kode => $av):
                $terbuka = $total_poin >= (int)$av['syarat_poin'];
                $sedang_dipakai = ($avatar_aktif_kode === $kode);
            ?>
            <div class="col-md-3">
                <div class="border rounded-4 p-3 text-center h-100 <?= $sedang_dipakai ? 'border-success bg-success-subtle' : '' ?>">
                    <div style="font-size:2.2rem;"><?= $av['icon'] ?></div>
                    <div class="fw-bold mt-2"><?= htmlspecialchars($av['nama']) ?></div>
                    <div class="small text-muted mb-2">Syarat: <?= (int)$av['syarat_poin'] ?> poin</div>
                    <?php if ($terbuka): ?>
                        <form method="POST">
                            <input type="hidden" name="kode_avatar" value="<?= htmlspecialchars($kode) ?>">
                            <button type="submit" name="pakai_avatar" class="btn btn-sm <?= $sedang_dipakai ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3">
                                <?= $sedang_dipakai ? 'Dipakai' : 'Pakai' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="badge bg-secondary rounded-pill">Terkunci</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Toko Avatar dengan Poin -->
    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3" style="color:#095f1f;">Beli Avatar dengan Poin (Poinmu: <?= $total_poin ?>)</h5>
        <div class="row g-3">
            <?php foreach ($avatar_toko as $kode => $av):
                $sudah_punya = in_array($kode, $milik_toko, true);
                $sedang_dipakai = ($avatar_aktif_kode === $kode);
            ?>
            <div class="col-md-4">
                <div class="border rounded-4 p-3 text-center h-100">
                    <div style="font-size:2.2rem;"><?= $av['icon'] ?></div>
                    <div class="fw-bold mt-2"><?= htmlspecialchars($av['nama']) ?></div>
                    <div class="small text-muted mb-2">Harga: <strong><?= (int)$av['harga'] ?> Poin</strong></div>
                    <?php if ($sudah_punya): ?>
                        <form method="POST">
                            <input type="hidden" name="kode_avatar" value="<?= htmlspecialchars($kode) ?>">
                            <button type="submit" name="pakai_avatar" class="btn btn-sm <?= $sedang_dipakai ? 'btn-success' : 'btn-outline-success' ?> rounded-pill px-3">
                                <?= $sedang_dipakai ? 'Dipakai' : 'Pakai Avatar' ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="kode_avatar" value="<?= htmlspecialchars($kode) ?>">
                            <button type="submit" name="beli_avatar" class="btn btn-sm btn-warning fw-bold text-dark rounded-pill px-3" <?= $total_poin < (int)$av['harga'] ? 'disabled' : '' ?>>
                                Beli (<?= (int)$av['harga'] ?> Poin)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php renderBottomNav('profil'); ?>