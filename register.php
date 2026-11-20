<?php
require_once 'includes/fungsi.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_santri  = trim($_POST['nama_santri']);
    $nama_sekolah = trim($_POST['nama_sekolah']);
    $username     = trim($_POST['username']);
    $password     = $_POST['password'];

    if ($nama_santri && $nama_sekolah && $username && $password) {
        $cek = $pdo->prepare("SELECT id FROM pengguna WHERE username = ?");
        $cek->execute([$username]);
        if ($cek->fetch()) {
            $error = "Username sudah digunakan santri lain, silakan pilih username lain.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO pengguna (nama_santri, nama_sekolah, username, password_hash, role) VALUES (?, ?, ?, ?, 'pengguna')");
            $stmt->execute([$nama_santri, $nama_sekolah, $username, $hash]);
            
            $user_id = $pdo->lastInsertId();
            // Catatan awal mahkota orang tua sesuai PRD v1.1 Bagian 8.7
            $pdo->prepare("INSERT INTO catatan_cepat (pengguna_id, isi, tipe) VALUES (?, ?, 'Pribadi')")
                ->execute([$user_id, "Niatkan setiap ayat hafalan untuk memakaikan mahkota cahaya kepada kedua orang tua di akhirat kelak."]);

            header("Location: login.php?registered=1");
            exit;
        }
    } else {
        $error = "Semua kolom wajib diisi!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - ASROR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: linear-gradient(135deg, #093C5D, #3B7597); padding-bottom:0;">
    <div class="card-asror p-4 p-md-5 m-3" style="width: 100%; max-width: 440px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold" style="color: var(--primary-dark);">🌙 ASROR</h2>
            <p class="text-muted small mb-0">PPTQ Al-Asror • Manajemen Waktu & Ibadah Santri</p>
        </div>
        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Nama Lengkap Santri</label>
                <input type="text" name="nama_santri" class="form-control" placeholder="Contoh: Ahmad Firdaus" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Nama Sekolah</label>
                <input type="text" name="nama_sekolah" class="form-control" placeholder="Contoh: SMA Negeri 1" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Username (untuk Login)</label>
                <input type="text" name="username" class="form-control" placeholder="Contoh: ahmad_firdaus" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Minimal 6 karakter" required>
            </div>
            <button type="submit" class="btn btn-mint w-100 py-2">Daftar Sekarang</button>
        </form>
        <div class="text-center mt-3">
            <small class="text-muted">Sudah punya akun? <a href="login.php" class="fw-bold text-decoration-none" style="color:var(--primary-blue);">Masuk di sini</a></small>
        </div>
    </div>
</body>
</html>