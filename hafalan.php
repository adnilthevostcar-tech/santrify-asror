<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = $_SESSION['pengguna_id'];
$hari_ini = date('Y-m-d');

// Toggle Shalat
if (isset($_GET['shalat'])) {
    $sh = $_GET['shalat'];
    if (in_array($sh, ['Subuh', 'Dzuhur', 'Ashar', 'Maghrib', 'Isya'])) {
        $cek = $pdo->prepare("SELECT id FROM catatan_shalat WHERE pengguna_id = ? AND tanggal = ? AND jenis_shalat = ?");
        $cek->execute([$uid, $hari_ini, $sh]);
        if ($cek->fetch()) {
            $pdo->prepare("DELETE FROM catatan_shalat WHERE pengguna_id = ? AND tanggal = ? AND jenis_shalat = ?")->execute([$uid, $hari_ini, $sh]);
        } else {
            $pdo->prepare("INSERT INTO catatan_shalat (pengguna_id, tanggal, jenis_shalat) VALUES (?, ?, ?)")->execute([$uid, $hari_ini, $sh]);
        }
    }
    header("Location: hafalan.php");
    exit;
}

// Tambah Setoran Hafalan / Murojaah (+2 Poin saat membuat setoran)
if (isset($_POST['tambah_setoran'])) {
    $jenis   = $_POST['jenis'];
    $tanggal = $_POST['tanggal'] ?: $hari_ini;
    $juz     = (int)$_POST['juz'];
    $surah   = ($jenis === 'Hafalan') ? trim($_POST['surah']) : null;
    $ayat    = ($jenis === 'Hafalan') ? trim($_POST['ayat']) : null;
    $porsi   = ($jenis === 'Murojaah') ? trim($_POST['porsi']) : null;
    $halaman = !empty($_POST['halaman']) ? (int)$_POST['halaman'] : null;

    $stmtIns = $pdo->prepare("INSERT INTO catatan_hafalan (pengguna_id, jenis, tanggal, juz, surah, ayat, halaman, porsi) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmtIns->execute([$uid, $jenis, $tanggal, $juz, $surah, $ayat, $halaman, $porsi])) {
        $id_baru = (int)$pdo->lastInsertId();
        $sumber_buat = "buat_setoran_{$id_baru}";
        tambahPoin($pdo, $uid, 2, "Buat setoran {$jenis} Juz {$juz}", $sumber_buat);
    }
    header("Location: hafalan.php?setoran=sukses");
    exit;
}

// Centang Setoran Selesai (+2 Poin)
if (isset($_GET['selesai_setoran'])) {
    $sid = (int)$_GET['selesai_setoran'];
    $stmt = $pdo->prepare("SELECT * FROM catatan_hafalan WHERE id = ? AND pengguna_id = ?");
    $stmt->execute([$sid, $uid]);
    $set = $stmt->fetch();
    if ($set) {
        $baru = $set['selesai'] ? 0 : 1;
        $pdo->prepare("UPDATE catatan_hafalan SET selesai = ? WHERE id = ?")->execute([$baru, $sid]);
        $sumber = "setoran_{$sid}";
        if ($baru == 1) {
            tambahPoin($pdo, $uid, 2, "Setoran {$set['jenis']} Juz {$set['juz']}", $sumber);
        } else {
            batalPoin($pdo, $uid, $sumber);
        }
    }
    header("Location: hafalan.php");
    exit;
}

// Ambil data shalat hari ini
$stmt = $pdo->prepare("SELECT jenis_shalat FROM catatan_shalat WHERE pengguna_id = ? AND tanggal = ?");
$stmt->execute([$uid, $hari_ini]);
$shalat_done = $stmt->fetchAll(PDO::FETCH_COLUMN);
$GLOBALS['shalat_selesai_hari_ini'] = $shalat_done;

// Ambil setoran terbaru
$hafalan_list = $pdo->prepare("SELECT * FROM catatan_hafalan WHERE pengguna_id = ? AND jenis = 'Hafalan' ORDER BY tanggal DESC, id DESC LIMIT 5");
$hafalan_list->execute([$uid]);
$hafalan = $hafalan_list->fetchAll();

$murojaah_list = $pdo->prepare("SELECT * FROM catatan_hafalan WHERE pengguna_id = ? AND jenis = 'Murojaah' ORDER BY tanggal DESC, id DESC LIMIT 5");
$murojaah_list->execute([$uid]);
$murojaah = $murojaah_list->fetchAll();

renderHeader('Ibadah & Hafalan');
?>

<div class="container my-4">
    <?php if (isset($_GET['setoran']) && $_GET['setoran'] === 'sukses'): ?>
        <div class="alert alert-success rounded-4 shadow-sm">
            Setoran berhasil dicatat dan kamu mendapat <strong>+2 poin</strong>!
        </div>
    <?php endif; ?>

    <!-- Checklist Shalat 5 Waktu -->
    <div id="checklist-shalat" class="card-asror p-4 mb-4">
        <h5 class="fw-bold mb-1">🕌 Checklist Shalat Wajib (<?= date('d M Y') ?>)</h5>
        <p class="text-muted small mb-3">Klik untuk menandai shalat yang sudah ditunaikan.</p>
        <div class="row g-2 text-center">
            <?php 
            $waktu = ['Subuh'=>'Waktu Subuh', 'Dzuhur'=>'Waktu Dzuhur', 'Ashar'=>'Waktu Ashar', 'Maghrib'=>'Waktu Maghrib', 'Isya'=>'Waktu Isya'];
            foreach ($waktu as $nama_sh => $jam_sh): 
                $done = in_array($nama_sh, $shalat_done);
            ?>
            <div class="col">
                <a href="hafalan.php?shalat=<?= $nama_sh ?>" class="btn w-100 py-3 rounded-4 <?= $done ? 'btn-success' : 'btn-outline-secondary' ?>">
                    <div class="fw-bold"><?= $nama_sh ?></div>
                    <small style="font-size:0.7rem;"><?= $done ? '✓ Selesai' : $jam_sh ?></small>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Form & Daftar Setoran Hafalan -->
        <div class="col-md-6">
            <div class="card-asror p-4 h-100">
                <h5 class="fw-bold mb-3">📖 Setoran Hafalan Baru (+2 Poin)</h5>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="jenis" value="Hafalan">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="date" name="tanggal" value="<?= $hari_ini ?>" class="form-control" required></div>
                        <div class="col-6"><input type="number" name="juz" class="form-control" placeholder="Juz (1-30)" min="1" max="30" required></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="text" name="surah" class="form-control" placeholder="Nama Surat (Wajib)" required></div>
                        <div class="col-5"><input type="text" name="ayat" class="form-control" placeholder="Ayat (mis: 1-10)"></div>
                    </div>
                    <div class="mb-2"><input type="number" name="halaman" class="form-control" placeholder="Halaman (Opsional)"></div>
                    <button type="submit" name="tambah_setoran" class="btn btn-mint w-100">Catat Setoran Hafalan (+2 Poin)</button>
                </form>

                <h6 class="fw-bold text-muted">5 Setoran Hafalan Terbaru</h6>
                <?php foreach ($hafalan as $h): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong class="<?= $h['selesai']?'text-success':'' ?>">Juz <?= $h['juz'] ?> • QS. <?= htmlspecialchars($h['surah']) ?> (<?= htmlspecialchars($h['ayat']) ?>)</strong>
                        <small class="d-block text-muted"><?= $h['tanggal'] ?> <?= $h['halaman'] ? "• Hal. {$h['halaman']}" : '' ?></small>
                    </div>
                    <a href="hafalan.php?selesai_setoran=<?= $h['id'] ?>" class="btn btn-sm <?= $h['selesai']?'btn-success':'btn-outline-secondary' ?>">
                        <?= $h['selesai'] ? 'Tuntas ✓' : 'Tandai Selesai' ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Form & Daftar Setoran Murojaah -->
        <div class="col-md-6">
            <div class="card-asror p-4 h-100">
                <h5 class="fw-bold mb-3">🔁 Setoran Murojaah (+2 Poin)</h5>
                <form method="POST" class="mb-4">
                    <input type="hidden" name="jenis" value="Murojaah">
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="date" name="tanggal" value="<?= $hari_ini ?>" class="form-control" required></div>
                        <div class="col-6"><input type="number" name="juz" class="form-control" placeholder="Juz (1-30)" min="1" max="30" required></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7">
                            <select name="porsi" class="form-select">
                                <option>Seperempat Juz</option>
                                <option>Setengah Juz</option>
                                <option>Satu Juz</option>
                                <option>Lainnya</option>
                            </select>
                        </div>
                        <div class="col-5"><input type="number" name="halaman" class="form-control" placeholder="Jml Halaman"></div>
                    </div>
                    <button type="submit" name="tambah_setoran" class="btn btn-ocean w-100 mt-1">Catat Murojaah (+2 Poin)</button>
                </form>

                <h6 class="fw-bold text-muted">5 Setoran Murojaah Terbaru</h6>
                <?php foreach ($murojaah as $m): ?>
                <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                    <div>
                        <strong class="<?= $m['selesai']?'text-success':'' ?>">Juz <?= $m['juz'] ?> • <?= htmlspecialchars($m['porsi']) ?></strong>
                        <small class="d-block text-muted"><?= $m['tanggal'] ?> <?= $m['halaman'] ? "• {$m['halaman']} Hal." : '' ?></small>
                    </div>
                    <a href="hafalan.php?selesai_setoran=<?= $m['id'] ?>" class="btn btn-sm <?= $m['selesai']?'btn-success':'btn-outline-secondary' ?>">
                        <?= $m['selesai'] ? 'Tuntas ✓' : 'Tandai Selesai' ?>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<?php renderBottomNav('ibadah'); ?>