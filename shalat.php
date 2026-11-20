
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require_once 'koneksi.php';

// Pemeriksaan login
if (!isset($_SESSION['pengguna_id'])) {
    header("Location: login.php");
    exit;
}

$pengguna_id = (int) $_SESSION['pengguna_id'];
$hari_ini = date('Y-m-d');

// Daftar shalat dan jadwal dari konfigurasi Santrify
$daftar_shalat = [
    'Subuh'   => '04:30',
    'Dzuhur'  => '12:00',
    'Ashar'   => '15:30',
    'Maghrib' => '18:00',
    'Isya'    => '19:15'
];

// Validasi tanggal
$tanggal_pilih = $_GET['tanggal'] ?? $hari_ini;

$format_tanggal = DateTime::createFromFormat('!Y-m-d', $tanggal_pilih);

if (!$format_tanggal || $format_tanggal->format('Y-m-d') !== $tanggal_pilih) {
    $tanggal_pilih = $hari_ini;
}

$success = '';
$error = '';

// Proses penyimpanan checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_shalat'])) {

    $tanggal_post = $_POST['tanggal'] ?? $hari_ini;
    $validasi_post = DateTime::createFromFormat('!Y-m-d', $tanggal_post);

    if (!$validasi_post || $validasi_post->format('Y-m-d') !== $tanggal_post) {
        $error = 'Tanggal tidak valid.';
    } else {
        try {
            $pdo->beginTransaction();

            foreach ($daftar_shalat as $nama_shalat => $jam) {

                $status = isset($_POST['shalat'][$nama_shalat]) ? 1 : 0;

                // Periksa data yang sudah ada
                $cek = $pdo->prepare("
                    SELECT id
                    FROM catatan_shalat
                    WHERE pengguna_id = ?
                    AND jenis_shalat = ?
                    AND tanggal = ?
                    LIMIT 1
                ");

                $cek->execute([
                    $pengguna_id,
                    $nama_shalat,
                    $tanggal_post
                ]);

                $data = $cek->fetch(PDO::FETCH_ASSOC);

                if ($data) {
                    // Update status yang sudah ada
                    $update = $pdo->prepare("
                        UPDATE catatan_shalat
                        SET status_centang = ?
                        WHERE pengguna_id = ?
                        AND jenis_shalat = ?
                        AND tanggal = ?
                    ");

                    $update->execute([
                        $status,
                        $pengguna_id,
                        $nama_shalat,
                        $tanggal_post
                    ]);

                } else {
                    // Tambahkan data baru
                    $insert = $pdo->prepare("
                        INSERT INTO catatan_shalat
                        (pengguna_id, jenis_shalat, tanggal, status_centang)
                        VALUES (?, ?, ?, ?)
                    ");

                    $insert->execute([
                        $pengguna_id,
                        $nama_shalat,
                        $tanggal_post,
                        $status
                    ]);
                }
            }

            $pdo->commit();

            header("Location: shalat.php?tanggal=" . urlencode($tanggal_post) . "&sukses=1");
            exit;

        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Gagal menyimpan catatan shalat. Periksa koneksi database.';
        }
    }

    $tanggal_pilih = $tanggal_post;
}

// Pesan berhasil
if (isset($_GET['sukses'])) {
    $success = 'Catatan shalat berhasil disimpan!';
}

// Ambil catatan shalat
$stmt = $pdo->prepare("
    SELECT jenis_shalat, status_centang
    FROM catatan_shalat
    WHERE pengguna_id = ?
    AND tanggal = ?
");

$stmt->execute([$pengguna_id, $tanggal_pilih]);

$status_db = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $status_db[$row['jenis_shalat']] = (int) $row['status_centang'];
}

// Hitung progres
$total_selesai = 0;

foreach ($daftar_shalat as $nama => $jam) {
    if (($status_db[$nama] ?? 0) == 1) {
        $total_selesai++;
    }
}

$persentase = ($total_selesai / 5) * 100;

// Tentukan pengingat salat
$shalat_terlambat = null;

if ($tanggal_pilih === $hari_ini) {
    $jam_sekarang = date('H:i');

    foreach ($daftar_shalat as $nama => $jam) {
        if (
            $jam_sekarang >= $jam &&
            ($status_db[$nama] ?? 0) == 0
        ) {
            $shalat_terlambat = [
                'nama' => $nama,
                'jam' => $jam
            ];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Catatan Shalat - Santrify</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        :root {
            --hijau-utama: #2A7C13;
            --hijau-muda: #7EC151;
            --kuning: #FFCB3D;
            --kuning-lembut: #FFF2A0;
        }

        body {
            background: #f5f8f1;
            color: #253323;
        }

        .shalat-navbar {
            background: var(--hijau-utama);
            padding: 14px 0;
        }

        .shalat-navbar .navbar-brand {
            color: white;
            font-weight: 700;
        }

        .shalat-card {
            background: white;
            border: 1px solid #e5ebdf;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(42,124,19,.06);
        }

        .shalat-title {
            color: var(--hijau-utama);
            font-weight: 750;
        }

        .progress {
            height: 12px;
            border-radius: 20px;
            background: #e9eee5;
        }

        .progress-bar {
            background: var(--hijau-utama);
            border-radius: 20px;
        }

        .shalat-item {
            border: 1px solid #e5ebdf;
            border-radius: 14px;
            padding: 17px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            transition: .2s;
        }

        .shalat-item.selesai {
            background: #eff8e9;
            border-color: var(--hijau-muda);
        }

        .shalat-item .nama {
            font-weight: 700;
            color: #293d25;
        }

        .shalat-item .jam {
            font-size: 13px;
            color: #788276;
        }

        .form-check-input {
            width: 23px;
            height: 23px;
            cursor: pointer;
            border-color: var(--hijau-utama);
        }

        .form-check-input:checked {
            background-color: var(--hijau-utama);
            border-color: var(--hijau-utama);
        }

        .btn-simpan {
            background: var(--hijau-utama);
            color: white;
            border: none;
            padding: 13px;
            border-radius: 12px;
            font-weight: 700;
        }

        .btn-simpan:hover {
            background: #205f0e;
            color: white;
        }

        .btn-kembali {
            border: 1px solid rgba(255,255,255,.7);
            color: white;
            border-radius: 9px;
        }

        .btn-kembali:hover {
            background: white;
            color: var(--hijau-utama);
        }

        .pengingat {
            background: #fff5d9;
            border: 1px solid var(--kuning);
            border-radius: 14px;
            padding: 16px;
        }

        .modal-pengingat {
            border: none;
            border-radius: 20px;
            overflow: hidden;
        }

        .modal-pengingat .modal-header {
            background: var(--hijau-utama);
            color: white;
        }

        .btn-konfirmasi {
            background: var(--hijau-utama);
            color: white;
        }

        .btn-konfirmasi:hover {
            background: #205f0e;
            color: white;
        }

        @media(max-width: 576px) {
            .shalat-card {
                padding: 18px;
            }

            .shalat-item {
                padding: 14px;
            }

            .shalat-navbar .container {
                gap: 10px;
            }
        }
    </style>
</head>

<body>

<nav class="navbar shalat-navbar">
    <div class="container d-flex justify-content-between align-items-center">

        <a class="navbar-brand" href="index.php">
            🌿 Santrify
        </a>

        <a href="index.php" class="btn btn-kembali btn-sm">
            Kembali
        </a>

    </div>
</nav>

<div class="container py-4">

    <div class="mb-4">
        <h3 class="shalat-title">🕌 Catatan Shalat</h3>
        <p class="text-muted mb-0">
            Catat dan pantau pelaksanaan shalat wajib setiap hari.
        </p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            ✓ <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($shalat_terlambat): ?>
        <div class="pengingat mb-4">
            <strong>🔔 Pengingat Shalat</strong>
            <p class="mb-0 mt-2">
                Waktu shalat <?= htmlspecialchars($shalat_terlambat['nama']) ?>
                telah tiba sejak pukul <?= htmlspecialchars($shalat_terlambat['jam']) ?>.
                Jangan lupa menunaikan shalat dan memperbarui checklist.
            </p>
        </div>
    <?php endif; ?>

    <!-- Progres Shalat -->
    <div class="shalat-card mb-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-bold mb-1">Progres Shalat</h5>
                <small class="text-muted">
                    <?= date('d M Y', strtotime($tanggal_pilih)) ?>
                </small>
            </div>

            <h4 class="shalat-title mb-0">
                <?= $total_selesai ?>/5
            </h4>
        </div>

        <div class="progress">
            <div
                class="progress-bar"
                role="progressbar"
                style="width: <?= $persentase ?>%">
            </div>
        </div>

        <small class="text-muted mt-2 d-block">
            <?= $total_selesai ?> dari 5 shalat telah dicentang
            (<?= number_format($persentase, 0) ?>%).
        </small>

    </div>

    <!-- Checklist -->
    <div class="shalat-card mb-4">

        <h5 class="fw-bold mb-3">Checklist Lima Waktu</h5>

        <form method="POST" action="shalat.php">

            <input
                type="hidden"
                name="tanggal"
                value="<?= htmlspecialchars($tanggal_pilih) ?>"
            >

            <?php foreach ($daftar_shalat as $nama => $jam): ?>

                <?php
                    $selesai = ($status_db[$nama] ?? 0) == 1;
                ?>

                <label class="shalat-item <?= $selesai ? 'selesai' : '' ?>">

                    <div>
                        <div class="nama">
                            <?= htmlspecialchars($nama) ?>
                            <?= $selesai ? '<span class="text-success">✓</span>' : '' ?>
                        </div>

                        <div class="jam">
                            Jadwal pengingat: <?= htmlspecialchars($jam) ?>
                        </div>
                    </div>

                    <input
                        type="checkbox"
                        class="form-check-input"
                        name="shalat[<?= htmlspecialchars($nama) ?>]"
                        value="1"
                        <?= $selesai ? 'checked' : '' ?>
                    >

                </label>

            <?php endforeach; ?>

            <button
                type="submit"
                name="simpan_shalat"
                class="btn btn-simpan w-100 mt-3"
            >
                Simpan Checklist Shalat
            </button>

        </form>

    </div>

    <!-- Riwayat -->
    <div class="shalat-card">

        <h5 class="fw-bold mb-3">📅 Riwayat Catatan Shalat</h5>

        <form method="GET" action="shalat.php">

            <label class="form-label">
                Pilih tanggal
            </label>

            <div class="row g-2">

                <div class="col-8">
                    <input
                        type="date"
                        name="tanggal"
                        value="<?= htmlspecialchars($tanggal_pilih) ?>"
                        class="form-control"
                        max="<?= $hari_ini ?>"
                    >
                </div>

                <div class="col-4">
                    <button class="btn btn-simpan w-100">
                        Lihat
                    </button>
                </div>

            </div>

        </form>

        <div class="mt-3">
            <a href="shalat.php" class="text-decoration-none" style="color:var(--hijau-utama)">
                ← Kembali ke hari ini
            </a>
        </div>

    </div>

</div>

<!-- Modal Pengingat -->
<div
    class="modal fade"
    id="modalPengingat"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content modal-pengingat">

            <div class="modal-header">
                <h5 class="modal-title fw-bold">
                    🔔 Pengingat Shalat
                </h5>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                ></button>
            </div>

            <div class="modal-body text-center p-4">

                <div style="font-size:45px">🕌</div>

                <h5 class="fw-bold mt-3">
                    Waktunya Menunaikan Shalat
                </h5>

                <p class="text-muted mb-0">
                    Waktu shalat
                    <strong><?= htmlspecialchars($shalat_terlambat['nama'] ?? '') ?></strong>
                    telah tiba. Jangan lupa menunaikan kewajibanmu.
                </p>

            </div>

            <div class="modal-footer border-0">
                <button
                    type="button"
                    class="btn btn-konfirmasi w-100"
                    data-bs-dismiss="modal"
                >
                    Baik, saya mengerti
                </button>
            </div>

        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php if ($shalat_terlambat): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = new bootstrap.Modal(
        document.getElementById('modalPengingat')
    );

    modal.show();
});
</script>
<?php endif; ?>

</body>
</html>
