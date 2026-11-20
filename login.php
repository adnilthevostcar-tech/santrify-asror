<?php
require_once 'includes/fungsi.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['pengguna_id']  = $user['id'];
        $_SESSION['nama_santri']  = $user['nama_santri'];
        $_SESSION['nama_sekolah'] = $user['nama_sekolah'];
        $_SESSION['role'] = $user['role'] ?? 'pengguna';
        header('Location: ' . (($_SESSION['role'] === 'admin') ? 'admin.php' : 'index.php'));
        exit;
    } else {
        $error = "Username atau password salah!";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - ASROR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100" style="background: linear-gradient(135deg, #093C5D, #3B7597); padding-bottom:0;">
    <div class="card-asror p-4 p-md-5 m-3" style="width: 100%; max-width: 420px;">
        <div class="text-center mb-4">
            <h2 class="fw-bold" style="color: var(--primary-dark);"> <img src="img/al_asror-removebg-preview.png" alt="Logo Al-Asror" style="width: 50px; height: 50px; object-fit: contain; background: white; border-radius: 50%; padding: 3px;"> 
             ASROR</h2>
            <p class="text-muted small mb-0">Assalamu’alaikum, silakan masuk</p>
        </div>
        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success py-2 small">Pendaftaran berhasil! Silakan masuk dengan username Anda.</div>
        <?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Username</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn btn-mint w-100 py-2">Masuk ke Aplikasi</button>
        </form>
        <div class="text-center mt-3">
            <small class="text-muted">Belum punya akun? <a href="register.php" class="fw-bold text-decoration-none" style="color:var(--primary-blue);">Daftar Santri Baru</a></small>
        </div>
    </div>
</body>
</html>