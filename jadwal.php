<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];
$tanggal = $_GET['tanggal'] ?? date('Y-m-d');

// Isi template jadwal bawaan (PRD v1.1 Bagian 8.2)
if (isset($_POST['isi_template'])) {
    $templates = [
        ['Sekolah Formal', 'Sekolah', '07:00', '14:00', 20],
        ['Murojaah Sore', 'Murojaah', '16:00', '16:30', 15],
        ['Ngaji Kitab Malam', 'Pondok', '20:00', '21:00', 15],
    ];
    $ins = $pdo->prepare("INSERT INTO jadwal_harian (pengguna_id, nama_kegiatan, kategori, jam_mulai, jam_selesai, poin) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($templates as $tpl) {
        $ins->execute([$uid, $tpl[0], $tpl[1], $tpl[2], $tpl[3], $tpl[4]]);
    }
    header("Location: jadwal.php");
    exit;
}

// Tambah Jadwal Baru
if (isset($_POST['tambah_jadwal'])) {
    $nama = trim($_POST['nama_kegiatan']);
    $kat  = $_POST['kategori'];
    $mulai = $_POST['jam_mulai'];
    $selesai = $_POST['jam_selesai'];
    $poin = (int)$_POST['poin'];

    if ($nama && $mulai && $selesai) {
        $pdo->prepare("INSERT INTO jadwal_harian (pengguna_id, nama_kegiatan, kategori, jam_mulai, jam_selesai, poin) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$uid, $nama, $kat, $mulai, $selesai, $poin]);
    }
    header("Location: jadwal.php?tanggal=" . $tanggal);
    exit;
}

// Toggle Checklist Per Tanggal
if (isset($_GET['toggle'])) {
    $jid = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT * FROM jadwal_harian WHERE id = ? AND pengguna_id = ?");
    $stmt->execute([$jid, $uid]);
    $jadwal = $stmt->fetch();

    if ($jadwal) {
        $cek = $pdo->prepare("SELECT * FROM ceklist_kegiatan WHERE jadwal_id = ? AND tanggal = ?");
        $cek->execute([$jid, $tanggal]);
        $row = $cek->fetch();
        $sumber = "jadwal_{$jid}_{$tanggal}";

        if ($row && $row['sudah_selesai'] == 1) {
            $pdo->prepare("UPDATE ceklist_kegiatan SET sudah_selesai = 0, waktu_selesai = NULL WHERE id = ?")->execute([$row['id']]);
            batalPoin($pdo, $uid, $sumber);
        } else {
            if ($row) {
                $pdo->prepare("UPDATE ceklist_kegiatan SET sudah_selesai = 1, waktu_selesai = NOW() WHERE id = ?")->execute([$row['id']]);
            } else {
                $pdo->prepare("INSERT INTO ceklist_kegiatan (pengguna_id, jadwal_id, tanggal, sudah_selesai, waktu_selesai) VALUES (?, ?, ?, 1, NOW())")
                    ->execute([$uid, $jid, $tanggal]);
            }
            tambahPoin($pdo, $uid, $jadwal['poin'], "Jadwal selesai: " . $jadwal['nama_kegiatan'] . " ($tanggal)", $sumber);
        }
    }
    header("Location: jadwal.php?tanggal=" . $tanggal);
    exit;
}

// Hapus Jadwal
if (isset($_GET['hapus'])) {
    $jid = (int)$_GET['hapus'];
    $pdo->prepare("DELETE FROM jadwal_harian WHERE id = ? AND pengguna_id = ?")->execute([$jid, $uid]);
    header("Location: jadwal.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT j.*, COALESCE(c.sudah_selesai, 0) as selesai
    FROM jadwal_harian j
    LEFT JOIN ceklist_kegiatan c ON j.id = c.jadwal_id AND c.tanggal = ?
    WHERE j.pengguna_id = ? AND j.status_aktif = 1
    ORDER BY j.jam_mulai ASC
");
$stmt->execute([$tanggal, $uid]);
$daftar_jadwal = $stmt->fetchAll();

renderHeader('Jadwal Wajib');
?>

<div class="container my-4">
    <?php if (isset($_GET['setup']) || count($daftar_jadwal) == 0): ?>
    <div class="alert alert-primary rounded-4 p-4 mb-4">
        <h5 class="fw-bold">👋 Selamat Datang! Atur Jadwal Wajib Harianmu</h5>
        <p class="small mb-3">Sesuai alur ASROR, silakan tambahkan jadwal wajib harianmu terlebih dahulu (atau gunakan contoh bawaan di bawah ini) sebelum masuk ke beranda.</p>
        <form method="POST">
            <button type="submit" name="isi_template" class="btn btn-ocean btn-sm">Gunakan Jadwal Contoh (Sekolah, Murojaah, Ngaji)</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Kolom Kiri: Form Tambah Jadwal -->
        <div class="col-md-4">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-3">Tambah Jadwal Wajib</h5>
                <form method="POST">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Nama Kegiatan</label>
                        <input type="text" name="nama_kegiatan" class="form-control" placeholder="Contoh: Setoran Subuh" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">Kategori</label>
                        <select name="kategori" class="form-select">
                            <option value="Sekolah">Sekolah</option>
                            <option value="Belajar">Belajar</option>
                            <option value="Ibadah">Ibadah</option>
                            <option value="Hafalan">Hafalan</option>
                            <option value="Murojaah">Murojaah</option>
                            <option value="Pondok">Pondok</option>
                            <option value="Istirahat">Istirahat</option>
                            <option value="Pribadi">Pribadi</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jam Mulai</label>
                            <input type="time" name="jam_mulai" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold">Jam Selesai</label>
                            <input type="time" name="jam_selesai" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Poin Penyelesaian</label>
                        <input type="hidden" name="poin" value="2">
                        <input type="text" class="form-control bg-light" value="2 Poin" readonly>
                    </div>
                    <button type="submit" name="tambah_jadwal" class="btn btn-mint w-100">Simpan Jadwal</button>
                </form>
            </div>
        </div>

        <!-- Kolom Kanan: Checklist Jadwal Harian -->
        <div class="col-md-8">
            <div class="card-asror p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
                    <h5 class="fw-bold mb-0">Checklist Jadwal Harian</h5>
                    <form method="GET" class="d-flex gap-2">
                        <input type="date" name="tanggal" value="<?= $tanggal ?>" class="form-control form-control-sm" onchange="this.form.submit()">
                    </form>
                </div>

                <?php if ($daftar_jadwal): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($daftar_jadwal as $j): ?>
                        <div class="list-group-item bg-transparent px-0 py-3 d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <a href="jadwal.php?tanggal=<?= $tanggal ?>&toggle=<?= $j['id'] ?>" 
                                   class="btn btn-sm <?= $j['selesai'] ? 'btn-success' : 'btn-outline-secondary' ?> rounded-circle" style="width:34px;height:34px;">
                                    <i class="bi <?= $j['selesai'] ? 'bi-check-lg' : 'bi-circle' ?>"></i>
                                </a>
                                <div>
                                    <div class="fw-bold <?= $j['selesai'] ? 'text-decoration-line-through text-muted' : '' ?>">
                                        <?= htmlspecialchars($j['nama_kegiatan']) ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= substr($j['jam_mulai'],0,5) ?> - <?= substr($j['jam_selesai'],0,5) ?> • 
                                        <span class="badge bg-info text-dark"><?= $j['kategori'] ?></span>
                                        <span class="text-success fw-bold">+<?= $j['poin'] ?> Poin</span>
                                    </small>
                                </div>
                            </div>
                            <a href="jadwal.php?hapus=<?= $j['id'] ?>" onclick="return confirm('Hapus jadwal ini?')" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4">Belum ada jadwal wajib yang ditambahkan.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php renderBottomNav('jadwal'); ?>