<?php
require_once 'includes/fungsi.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE username = ? AND role = 'admin'");
    $stmt->execute([$username]);
    $adminUser = $stmt->fetch();

    if ($adminUser && password_verify($password, $adminUser['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['pengguna_id'] = $adminUser['id'];
        $_SESSION['nama_santri'] = $adminUser['nama_santri'];
        $_SESSION['role'] = 'admin';
        
        header('Location: admin.php');
        exit;
    } else {
        $error = "ID Admin atau Sandi salah, atau akun ini bukan admin!";
    }
}
renderHeader('Login Khusus Admin');
?>

<div class="container d-flex align-items-center justify-content-center" style="min-height: 80vh;">
    <div class="card-asror p-4 p-md-5 shadow-sm" style="width: 100%; max-width: 420px;">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-danger">🛡️ Verifikasi Admin</h3>
            <p class="text-muted small mb-0">Masukkan ID dan sandi akun pengurus/admin</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Username / ID Admin</label>
                <input type="text" name="username" class="form-control" placeholder="Masukkan username admin" required>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold small">Sandi / Password</label>
                <input type="password" name="password" class="form-control" placeholder="Masukkan sandi admin" required>
            </div>
            <button type="submit" class="btn btn-dark w-100 py-2 mb-2">Masuk ke Panel Admin</button>
            <a href="index.php" class="btn btn-outline-secondary w-100 py-2 btn-sm">Kembali ke Beranda</a>
        </form>
    </div>
</div>