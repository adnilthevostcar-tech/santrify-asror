<?php
require_once 'includes/fungsi.php';
wajibLogin();

$uid = (int)($_SESSION['pengguna_id'] ?? 0);
$nama_santri = $_SESSION['nama_santri'] ?? 'Santri';
$nama_sekolah = $_SESSION['nama_sekolah'] ?? '';

$pesan = '';
$tipe_pesan = 'success';

$katalog_avatar = [
    ['id' => 'santri_1', 'nama' => 'Santri Hijau',    'file' => 'img/01_Santri_Hijau.png', 'harga' => 0],
    ['id' => 'santri_2', 'nama' => 'Hafidz Cendekia', 'file' => 'img/02_Hafidz_Cendekia.png', 'harga' => 10],
    ['id' => 'santri_3', 'nama' => 'Pejuang Waktu',   'file' => 'img/03_Pejuang_Waktu.png', 'harga' => 20],
    ['id' => 'santri_4', 'nama' => 'Bintang Asror',   'file' => 'img/04_Bintang_Asror.png', 'harga' => 30],
    ['id' => 'santri_5', 'nama' => 'Muhafidz Muda',   'file' => 'img/05_Muhafidz_Muda.png', 'harga' => 50],
    ['id' => 'santri_6', 'nama' => 'Mahkota Quran',   'file' => 'img/06_Mahkota_Quran.png', 'harga' => 75],
];

$file_data_avatar = __DIR__ . '/assets/user_avatars.json';
if (!is_dir(__DIR__ . '/assets')) {
    @mkdir(__DIR__ . '/assets', 0777, true);
}
if (!is_dir(__DIR__ . '/img/avatars')) {
    @mkdir(__DIR__ . '/img/avatars', 0777, true);
}

$semua_avatar_user = [];
if (file_exists($file_data_avatar)) {
    $decoded = json_decode(file_get_contents($file_data_avatar), true);
    if (is_array($decoded)) {
        $semua_avatar_user = $decoded;
    }
}

$data_user_avatar = $semua_avatar_user[(string)$uid] ?? [
    'dimiliki' => ['santri_1'],
    'aktif'    => 'santri_1',
    'tema'     => 'light',
    'jam_serah'=> '16:00',
];

if (!is_array($data_user_avatar['dimiliki'] ?? null)) {
    $data_user_avatar['dimiliki'] = ['santri_1'];
}
if (!in_array('santri_1', $data_user_avatar['dimiliki'], true)) {
    $data_user_avatar['dimiliki'][] = 'santri_1';
}

$stmtPoin = $pdo->prepare("SELECT COALESCE(SUM(jumlah_poin), 0) FROM riwayat_poin WHERE pengguna_id = ?");
$stmtPoin->execute([$uid]);
$total_poin = (int)$stmtPoin->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_pengaturan'])) {
    $tema_baru = ($_POST['tema'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $jam_baru  = preg_match('/^\d{2}:\d{2}$/', $_POST['jam_serah'] ?? '') ? $_POST['jam_serah'] : '16:00';

    $data_user_avatar['tema'] = $tema_baru;
    $data_user_avatar['jam_serah'] = $jam_baru;
    $semua_avatar_user[(string)$uid] = $data_user_avatar;
    file_put_contents($file_data_avatar, json_encode($semua_avatar_user, JSON_PRETTY_PRINT));

    $_SESSION['jam_serah_hp'] = $jam_baru;
    $pesan = 'Pengaturan aplikasi berhasil disimpan!';
    $tipe_pesan = 'success';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi_avatar'], $_POST['avatar_id'])) {
    $aksi = $_POST['aksi_avatar'];
    $aid  = $_POST['avatar_id'];

    $item_dipilih = null;
    foreach ($katalog_avatar as $av) {
        if ($av['id'] === $aid) {
            $item_dipilih = $av;
            break;
        }
    }

    if ($item_dipilih) {
        $sudah_punya = in_array($aid, $data_user_avatar['dimiliki'], true);

        if ($aksi === 'beli' && !$sudah_punya) {
            $harga = (int)$item_dipilih['harga'];
            if ($total_poin >= $harga) {
                if ($harga > 0) {
                    $sumber_beli = 'avatar_' . $aid . '_' . $uid . '_' . time();
                    $stmtIns = $pdo->prepare("INSERT INTO riwayat_poin (pengguna_id, jumlah_poin, keterangan, sumber_transaksi) VALUES (?, ?, ?, ?)");
                    $stmtIns->execute([$uid, -$harga, 'Beli Avatar: ' . $item_dipilih['nama'], $sumber_beli]);
                }
                $data_user_avatar['dimiliki'][] = $aid;
                $data_user_avatar['aktif'] = $aid;
                $data_user_avatar['foto_profil'] = $item_dipilih['file'];
                $semua_avatar_user[(string)$uid] = $data_user_avatar;
                file_put_contents($file_data_avatar, json_encode($semua_avatar_user, JSON_PRETTY_PRINT));

                $_SESSION['foto_profil'] = $item_dipilih['file'];

                $stmtPoin->execute([$uid]);
                $total_poin = (int)$stmtPoin->fetchColumn();

                $pesan = 'Berhasil membeli dan memakai avatar "' . htmlspecialchars($item_dipilih['nama']) . '"!';
                $tipe_pesan = 'success';
            } else {
                $pesan = 'Poin kamu belum cukup untuk membeli avatar ini (butuh ' . $harga . ' poin, poinmu ' . $total_poin . ').';
                $tipe_pesan = 'danger';
            }
        } elseif ($aksi === 'pakai' && $sudah_punya) {
            $data_user_avatar['aktif'] = $aid;
            $data_user_avatar['foto_profil'] = $item_dipilih['file'];
            $semua_avatar_user[(string)$uid] = $data_user_avatar;
            file_put_contents($file_data_avatar, json_encode($semua_avatar_user, JSON_PRETTY_PRINT));

            $_SESSION['foto_profil'] = $item_dipilih['file'];

            $pesan = 'Foto profil / avatar "' . htmlspecialchars($item_dipilih['nama']) . '" sekarang aktif!';
            $tipe_pesan = 'success';
        }
    }
}

$avatar_aktif_id = $data_user_avatar['aktif'] ?? 'santri_1';
$avatar_aktif = $katalog_avatar[0];
foreach ($katalog_avatar as $av) {
    if ($av['id'] === $avatar_aktif_id) {
        $avatar_aktif = $av;
        break;
    }
}
$_SESSION['foto_profil'] = $avatar_aktif['file'];
$foto_aktif_url = getFotoProfilAktif();
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($data_user_avatar['tema'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil & Pengaturan - Santrify | ASROR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --asror-green: #095f1f;
            --asror-green-dark: #064416;
            --asror-bg: #f4f7f5;
            --asror-card: #ffffff;
            --asror-text: #1b2620;
        }
        [data-theme="dark"] {
            --asror-bg: #101713;
            --asror-card: #18231d;
            --asror-text: #eef5f0;
        }
        body {
            background-color: var(--asror-bg);
            color: var(--asror-text);
        }
        .topbar-asror {
            background-color: var(--asror-green);
            color: #ffffff;
            padding: 14px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .card-asror-box {
            background-color: var(--asror-card);
            border: 1px solid rgba(9, 95, 31, 0.14);
            border-radius: 20px;
            box-shadow: 0 4px 16px rgba(9, 95, 31, 0.06);
        }
        .btn-asror-green {
            background-color: var(--asror-green);
            color: #ffffff;
            border: none;
            font-weight: 600;
        }
        .btn-asror-green:hover {
            background-color: var(--asror-green-dark);
            color: #ffffff;
        }
        .profil-foto-besar {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--asror-green);
            background: #e9f5ec;
        }
        .avatar-grid-img {
            width: 84px;
            height: 84px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dee2e6;
            background: #f8f9fa;
        }
        .avatar-card {
            border: 2px solid rgba(9, 95, 31, 0.14);
            border-radius: 18px;
            transition: transform 0.15s ease, border-color 0.15s ease;
            background-color: var(--asror-card);
        }
        .avatar-card:hover {
            transform: translateY(-2px);
            border-color: var(--asror-green);
        }
        .avatar-card.aktif {
            border-color: var(--asror-green);
            box-shadow: 0 0 0 3px rgba(9, 95, 31, 0.15);
        }
        .avatar-card.aktif .avatar-grid-img {
            border-color: var(--asror-green);
        }
    </style>
</head>
<body>

<nav class="topbar-asror sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="text-white text-decoration-none fw-bold d-flex align-items-center gap-2">
            <img src="img/al_asror-removebg-preview.png" alt="Logo Al-Asror" style="width: 38px; height: 38px; object-fit: contain; background: #fff; border-radius: 50%; padding: 2px;">
            <span>Santrify | Profil & Pengaturan</span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-warning text-dark rounded-pill px-3 py-2 fw-bold">
                <i class="bi bi-star-fill me-1"></i> <?= (int)$total_poin ?> Poin
            </span>
            <a href="index.php" class="btn btn-sm btn-light rounded-pill px-3 fw-semibold" style="color: #095f1f;">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</nav>

<div class="container my-4" style="max-width: 960px;">
    <?php if ($pesan !== ''): ?>
        <div class="alert alert-<?= $tipe_pesan ?> rounded-4 shadow-sm mb-4">
            <?= $pesan ?>
        </div>
    <?php endif; ?>

    <div class="card-asror-box p-4 mb-4">
        <div class="d-flex flex-column flex-sm-row align-items-center gap-4">
            <img
                src="<?= htmlspecialchars($foto_aktif_url) ?>"
                alt="Foto Profil <?= htmlspecialchars($nama_santri) ?>"
                class="profil-foto-besar"
                onerror="this.onerror=null;this.src='img/al_asror-removebg-preview.png';"
            >
            <div class="text-center text-sm-start flex-grow-1">
                <span class="badge rounded-pill mb-2 px-3 py-2" style="background-color: rgba(9, 95, 31, 0.12); color: #095f1f;">
                    Profil Santri Al-Asror
                </span>
                <h4 class="fw-bold mb-1"><?= htmlspecialchars($nama_santri) ?></h4>
                <div class="text-muted small mb-2">
                    <?= htmlspecialchars($nama_sekolah ?: 'Pondok Pesantren Tahfidzil Qur\'an Al-Asror') ?>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-sm-start">
                    <span class="badge bg-success rounded-pill px-3 py-2">
                        Avatar Aktif: <?= htmlspecialchars($avatar_aktif['nama']) ?>
                    </span>
                    <span class="badge bg-dark rounded-pill px-3 py-2">
                        Saldo Poin: <?= (int)$total_poin ?> Poin
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card-asror-box p-4 mb-4">
        <h5 class="fw-bold mb-3" style="color: #095f1f;">
            <i class="bi bi-gear-fill me-2"></i>Pengaturan Aplikasi
        </h5>
        <form method="POST">
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Tema Tampilan</label>
                    <select name="tema" class="form-select">
                        <option value="light" <?= ($data_user_avatar['tema'] ?? 'light') === 'light' ? 'selected' : '' ?>>Terang (Light)</option>
                        <option value="dark" <?= ($data_user_avatar['tema'] ?? 'light') === 'dark' ? 'selected' : '' ?>>Gelap (Dark)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Waktu Pengingat Serah HP</label>
                    <input type="time" name="jam_serah" class="form-control" value="<?= htmlspecialchars($data_user_avatar['jam_serah'] ?? '16:00') ?>" required>
                </div>
            </div>
            <button type="submit" name="simpan_pengaturan" class="btn btn-asror-green rounded-pill px-4 py-2">
                <i class="bi bi-check2-circle me-1"></i> Simpan Pengaturan
            </button>
        </form>
    </div>

    <div class="card-asror-box p-4 mb-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="fw-bold mb-1" style="color: #095f1f;">
                    <i class="bi bi-person-badge-fill me-2"></i>Katalog Avatar & Foto Profil
                </h5>
                <p class="text-muted small mb-0">
                    Tukarkan poinmu untuk membuka avatar baru dan menjadikannya foto profil. Letakkan file gambar di <code>img/avatars/</code>.
                </p>
            </div>
            <span class="badge rounded-pill px-3 py-2 fs-6" style="background-color: #095f1f;">
                Poin Kamu: <?= (int)$total_poin ?>
            </span>
        </div>

        <div class="row g-3">
            <?php foreach ($katalog_avatar as $av):
                $punya = in_array($av['id'], $data_user_avatar['dimiliki'], true);
                $sedang_dipakai = ($av['id'] === $avatar_aktif_id);
                $bisa_beli = ($total_poin >= (int)$av['harga']);
            ?>
            <div class="col-6 col-md-4">
                <div class="avatar-card p-3 h-100 text-center d-flex flex-column justify-content-between <?= $sedang_dipakai ? 'aktif' : '' ?>">
                    <div>
                        <img
                            src="<?= htmlspecialchars($av['file']) ?>"
                            alt="<?= htmlspecialchars($av['nama']) ?>"
                            class="avatar-grid-img mb-2"
                            onerror="this.onerror=null;this.src='img/al_asror-removebg-preview.png';"
                        >
                        <div class="fw-bold"><?= htmlspecialchars($av['nama']) ?></div>
                        <div class="small text-muted mb-3">
                            <?= (int)$av['harga'] === 0 ? 'Gratis' : (int)$av['harga'] . ' Poin' ?>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="avatar_id" value="<?= htmlspecialchars($av['id']) ?>">
                        <?php if ($sedang_dipakai): ?>
                            <button type="button" class="btn btn-success btn-sm w-100 rounded-pill" disabled>
                                <i class="bi bi-check-circle-fill me-1"></i> Sedang Dipakai
                            </button>
                        <?php elseif ($punya): ?>
                            <button type="submit" name="aksi_avatar" value="pakai" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-semibold">
                                Pakai Foto Profil
                            </button>
                        <?php else: ?>
                            <button
                                type="submit"
                                name="aksi_avatar"
                                value="beli"
                                class="btn btn-sm w-100 rounded-pill fw-semibold <?= $bisa_beli ? 'btn-asror-green' : 'btn-secondary' ?>"
                                <?= $bisa_beli ? '' : 'disabled' ?>
                            >
                                <i class="bi bi-bag-check me-1"></i> Beli (<?= (int)$av['harga'] ?> Poin)
                            </button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>