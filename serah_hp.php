<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];
$hari_ini = date('Y-m-d');
$jam_sekarang = date('H:i');

// Batasan Akses Jam 16.05 (Waktu Pengumpulan HP)
// Jika jam >= 16:05, paksa user hanya bisa di halaman serah_hp.php
$waktu_pengumpulan = "16:05";
$harus_serah = ($jam_sekarang >= $waktu_pengumpulan);

// Tambah Rencana Setelah Sekolah / Jadwal Serah HP
if (isset($_POST['tambah_rencana'])) {
    $tanggal_input = !empty($_POST['tanggal']) ? $_POST['tanggal'] : $hari_ini;
    $jam = !empty($_POST['jam']) ? $_POST['jam'] : null;
    $isi = trim($_POST['isi']);
    $jam_serah = !empty($_POST['jam_serah']) ? $_POST['jam_serah'] : '16:05';
    $jam_ambil = !empty($_POST['jam_ambil']) ? $_POST['jam_ambil'] : '06:00';

    if ($isi) {
        $pdo->prepare("INSERT INTO rencana_serah (pengguna_id, tanggal, jam, isi, jam_serah, jam_ambil) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $tanggal_input, $jam, $isi, $jam_serah, $jam_ambil]);
    }
    header("Location: serah_hp.php");
    exit;
}

if (isset($_GET['hapus'])) {
    $pdo->prepare("DELETE FROM rencana_serah WHERE id = ? AND pengguna_id = ?")->execute([(int)$_GET['hapus'], $uid]);
    header("Location: serah_hp.php");
    exit;
}

$rencana = $pdo->prepare("SELECT * FROM rencana_serah WHERE pengguna_id = ? AND tanggal = ? ORDER BY jam ASC");
$rencana->execute([$uid, $hari_ini]);
$list_rencana = $rencana->fetchAll();

$tugas_belum = $pdo->prepare("SELECT * FROM tugas WHERE pengguna_id = ? AND status != 'Selesai'");
$tugas_belum->execute([$uid]);
$list_tugas = $tugas_belum->fetchAll();

// Ambil info jam serah/ambil terakhir jika ada
$info_serah = $list_rencana ? end($list_rencana) : null;
$tampil_jam_serah = $info_serah['jam_serah'] ?? '16:05';
$tampil_jam_ambil = $info_serah['jam_ambil'] ?? '06:00';

// Format teks untuk disalin ke buku
$teks_salin = "Rencana & Jadwal Serah HP (" . date('d/m/Y') . ")\n";
$teks_salin .= "Diserahkan ke Keamanan: Pukul " . $tampil_jam_serah . "\n";
$teks_salin .= "Diambil dari Keamanan: Pukul " . $tampil_jam_ambil . "\n";
$teks_salin .="----------------------------------------\n";
$no = 1;
foreach ($list_rencana as $r) {
    $jam_str = $r['jam'] ? "[" . substr($r['jam'],0,5) . "] " : "";
    $teks_salin .= $no++ . ". " . $jam_str . $r['isi'] . "\n";
}
foreach ($list_tugas as $t) {
    $teks_salin .= $no++ . ". [Tugas] " . $t['judul_tugas'] . "\n";
}

renderHeader('Mode Serah HP');
?>

<div class="container my-4">
    <div class="hero-banner p-4 mb-4">
        <h4 class="fw-bold mb-1">📱 Mode Persiapan Serah HP</h4>
        <p class="small mb-0 opacity-75">Susun rencana kegiatan sore & malammu sebelum HP dikumpulkan ke keamanan pondok, lalu salin ke buku catatanmu.</p>
    </div>

    <div class="row g-4">
        <!-- Kolom Form & Daftar Rencana -->
        <div class="col-md-6">
            <div class="card-asror p-4 mb-4">
                <h5 class="fw-bold mb-3">Tambah Rencana Setelah Sekolah</h5>
                <form method="POST" class="row g-2">
                    <div class="col-12 mb-1">
                        <label class="form-label small text-muted mb-1">Tanggal Kegiatan</label>
                        <input type="date" name="tanggal" value="<?= $hari_ini ?>" class="form-control" required>
                    </div>
                    <div class="col-4">
                        <label class="form-label small text-muted mb-1">Jam Kegiatan</label>
                        <input type="time" name="jam" class="form-control">
                    </div>
                    <div class="col-8">
                        <label class="form-label small text-muted mb-1">Aktivitas / Kegiatan</label>
                        <input type="text" name="isi" class="form-control" placeholder="Contoh: Murojaah Juz 30" required>
                    </div>

                    <!-- Informasi Jam Serah & Ambil Keamanan -->
                    <div class="col-6 mt-3">
                        <label class="form-label small fw-bold text-success">Jam Diserahkan (Keamanan)</label>
                        <input type="time" name="jam_serah" value="<?= $tampil_jam_serah ?>" class="form-control" required>
                    </div>
                    <div class="col-6 mt-3">
                        <label class="form-label small fw-bold text-primary">Jam Diambil (Keamanan)</label>
                        <input type="time" name="jam_ambil" value="<?= $tampil_jam_ambil ?>" class="form-control" required>
                    </div>

                    <div class="col-12 mt-3">
                        <button type="submit" name="tambah_rencana" class="btn btn-mint w-100">Simpan Rencana & Jadwal</button>
                    </div>
                </form>

                <hr class="my-4">
                <h6 class="fw-bold mb-3">Daftar Rencana Hari Ini</h6>
                <?php if (empty($list_rencana)): ?>
                    <p class="text-muted small italic">Belum ada rencana ditambahkan.</p>
                <?php endif; ?>
                <?php foreach ($list_rencana as $r): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <?php if ($r['jam']): ?>
                            <span class="badge bg-secondary me-2 px-2 py-1"><?= substr($r['jam'], 0, 5) ?></span>
                        <?php endif; ?>
                        <span class="text-dark fw-medium"><?= htmlspecialchars($r['isi']) ?></span>
                    </div>
                    <a href="serah_hp.php?hapus=<?= $r['id'] ?>" onclick="return confirm('Hapus rencana ini?')" class="text-danger ms-2"><i class="bi bi-x-circle"></i></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kolom Salin ke Buku -->
        <div class="col-md-6">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-2">📋 Siap Disalin ke Buku</h5>
                <p class="text-muted small">Catat jadwal penyerahan dan daftar rencana ke buku saku pondokmu:</p>
                <div class="mb-3 p-3 bg-light rounded-3 border small">
                    <div>🔒 <strong>Diserahkan ke Keamanan:</strong> Pukul <?= $tampil_jam_serah ?> WIB</div>
                    <div>🔓 <strong>Diambil dari Keamanan:</strong> Pukul <?= $tampil_jam_ambil ?> WIB</div>
                </div>
                <textarea id="teksRencana" class="form-control mb-3 font-monospace" rows="8" readonly><?= htmlspecialchars($teks_salin) ?></textarea>
                <button onclick="salinTeks()" class="btn btn-ocean w-100"><i class="bi bi-clipboard-check me-1"></i> Salin Rencana</button>
            </div>
        </div>
    </div>
</div>

<!-- POP-UP HIJAU PERINGATAN PENGUMPULAN HP (Jam 16.05 ke atas) -->
<?php if ($harus_serah): ?>
<div id="modalSerahHp" class="shalat-warning-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.75); display: flex; align-items: center; justify-content: center; z-index: 9999;">
    <div class="card p-4 text-center shadow-lg border-0 rounded-4" style="max-width: 450px; background: #198754; color: white;">
        <div class="mb-3"><i class="bi bi-phone-fill" style="font-size: 3rem;"></i></div>
        <h3 class="fw-bold mb-2">Waktu Pengumpulan HP!</h3>
        <p class="small mb-3 opacity-90">Sekarang sudah pukul 16:05 atau lebih. Waktunya menyerahkan HP ke pihak keamanan pondok. Akses halaman lain dikunci sementara.</p>
        <div class="bg-white text-dark p-3 rounded-3 text-start small mb-3">
            <div class="fw-bold mb-1">📌 Ketentuan Pengumpulan:</div>
            <div>• Serahkan HP ke Pos Keamanan pukul <strong><?= $tampil_jam_serah ?> WIB</strong>.</div>
            <div>• Ambil kembali HP besok pukul <strong><?= $tampil_jam_ambil ?> WIB</strong>.</div>
        </div>
        <button type="button" class="btn btn-light fw-bold text-success w-100 py-2" onclick="tutupModalSerah()">Saya Mengerti & Sudah Serah HP</button>
    </div>
</div>

<script>
// Fungsi untuk menutup pop-up sementara di halaman ini
function tutupModalSerah() {
    document.getElementById('modalSerahHp').style.display = 'none';
}

// Blokir navigasi ke halaman lain jika mencoba klik menu bawah (opsional pengaman tambahan)
document.addEventListener("DOMContentLoaded", function() {
    const navLinks = document.querySelectorAll('.bottom-nav a, a.nav-link');
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && !href.includes('serah_hp.php') && !href.includes('logout.php')) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                alert('Akses menu lain dikunci! Waktu pengumpulan HP telah tiba. Harap selesaikan urusan serah HP di halaman ini.');
                document.getElementById('modalSerahHp').style.display = 'flex';
            });
        }
    });
});
</script>
<?php endif; ?>

<?php renderBottomNav('serah'); ?>