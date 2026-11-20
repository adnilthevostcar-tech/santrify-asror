<?php
require_once 'includes/fungsi.php';
wajibLogin();
$uid = (int)$_SESSION['pengguna_id'];
$hari_ini = date('Y-m-d');

$file_eval_json = __DIR__ . '/assets/evaluasi_harian.json';
if (!is_dir(__DIR__ . '/assets')) {
    @mkdir(__DIR__ . '/assets', 0777, true);
}

$formatTanggalIndo = function (?string $tgl): string {
    if (!$tgl) {
        return '-';
    }
    $ts = strtotime($tgl);
    if ($ts === false) {
        return htmlspecialchars($tgl);
    }
    $hari = [
        'Sunday'    => 'Minggu',
        'Monday'    => 'Senin',
        'Tuesday'   => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday'  => 'Kamis',
        'Friday'    => 'Jumat',
        'Saturday'  => 'Sabtu',
    ];
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $namaHari = $hari[date('l', $ts)] ?? date('l', $ts);
    $namaBulan = $bulan[(int)date('n', $ts)] ?? date('M', $ts);
    return $namaHari . ', ' . date('j', $ts) . ' ' . $namaBulan . ' ' . date('Y', $ts);
};

$bacaEvalJson = function () use ($file_eval_json, $uid, $hari_ini): ?array {
    if (!file_exists($file_eval_json)) {
        return null;
    }
    $all = json_decode(file_get_contents($file_eval_json), true);
    if (is_array($all) && isset($all[(string)$uid][$hari_ini]) && is_array($all[(string)$uid][$hari_ini])) {
        return $all[(string)$uid][$hari_ini];
    }
    return null;
};

$simpanEvalJson = function (array $payload) use ($file_eval_json, $uid, $hari_ini): void {
    $all = [];
    if (file_exists($file_eval_json)) {
        $decoded = json_decode(file_get_contents($file_eval_json), true);
        if (is_array($decoded)) {
            $all = $decoded;
        }
    }
    if (!isset($all[(string)$uid]) || !is_array($all[(string)$uid])) {
        $all[(string)$uid] = [];
    }
    $all[(string)$uid][$hari_ini] = $payload;
    @file_put_contents($file_eval_json, json_encode($all, JSON_PRETTY_PRINT));
};

$hapusEvalJson = function (string $tglTarget) use ($file_eval_json, $uid): void {
    if (!file_exists($file_eval_json)) {
        return;
    }
    $all = json_decode(file_get_contents($file_eval_json), true);
    if (is_array($all) && isset($all[(string)$uid][$tglTarget])) {
        unset($all[(string)$uid][$tglTarget]);
        if (empty($all[(string)$uid])) {
            unset($all[(string)$uid]);
        }
        @file_put_contents($file_eval_json, json_encode($all, JSON_PRETTY_PRINT));
    }
};

$pesan_notif = '';
$tipe_notif = 'success';

if (isset($_SESSION['flash_fokus'])) {
    $pesan_notif = $_SESSION['flash_fokus']['pesan'] ?? '';
    $tipe_notif = $_SESSION['flash_fokus']['tipe'] ?? 'success';
    unset($_SESSION['flash_fokus']);
}

// Simpan Sesi Fokus Selesai
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_fokus'])) {
    $topik  = trim($_POST['topik'] ?? '') ?: 'Belajar';
    $durasi = max(1, (int)($_POST['durasi'] ?? 25));
    $pdo->prepare("INSERT INTO sesi_fokus (pengguna_id, topik, durasi, tanggal) VALUES (?, ?, ?, ?)")->execute([$uid, $topik, $durasi, $hari_ini]);
    tambahPoin($pdo, $uid, 10, "Sesi Fokus ($durasi menit): $topik", "fokus_" . time());
    $_SESSION['flash_fokus'] = ['tipe' => 'success', 'pesan' => "Sesi fokus ($durasi menit) berhasil disimpan!"];
    header("Location: fokus.php");
    exit;
}

// Hapus Evaluasi Harian berdasarkan tanggal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_evaluasi'])) {
    $tgl_hapus = trim($_POST['tanggal_evaluasi'] ?? '');
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl_hapus)) {
        try {
            $del = $pdo->prepare("DELETE FROM evaluasi_harian WHERE pengguna_id = ? AND tanggal = ?");
            $del->execute([$uid, $tgl_hapus]);
        } catch (PDOException $e) {
            // Tetap hapus dari cadangan JSON jika ada
        }
        $hapusEvalJson($tgl_hapus);

        $_SESSION['flash_fokus'] = [
            'tipe'  => 'success',
            'pesan' => 'Evaluasi tanggal ' . $tgl_hapus . ' berhasil dihapus.'
        ];
    }
    header("Location: fokus.php#evaluasi");
    exit;
}

// Simpan Evaluasi Harian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_evaluasi'])) {
    $berhasil = trim($_POST['berhasil'] ?? '');
    $belum    = trim($_POST['belum'] ?? '');
    $besok    = trim($_POST['besok'] ?? '');
    $mood     = trim($_POST['suasana_hati'] ?? 'Baik');

    $payload_eval = [
        'pengguna_id'  => $uid,
        'tanggal'      => $hari_ini,
        'berhasil'     => $berhasil,
        'belum'        => $belum,
        'besok'        => $besok,
        'suasana_hati' => $mood,
        'diperbarui'   => date('Y-m-d H:i:s'),
    ];

    $simpanEvalJson($payload_eval);

    $tersimpan_db = false;
    try {
        $cek = $pdo->prepare("SELECT id FROM evaluasi_harian WHERE pengguna_id = ? AND tanggal = ? LIMIT 1");
        $cek->execute([$uid, $hari_ini]);
        $ada = $cek->fetch(PDO::FETCH_ASSOC);

        if ($ada) {
            $upd = $pdo->prepare("
                UPDATE evaluasi_harian
                SET berhasil = ?, belum = ?, besok = ?, suasana_hati = ?
                WHERE id = ?
            ");
            $upd->execute([$berhasil, $belum, $besok, $mood, $ada['id']]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO evaluasi_harian (pengguna_id, tanggal, berhasil, belum, besok, suasana_hati)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $ins->execute([$uid, $hari_ini, $berhasil, $belum, $besok, $mood]);
        }
        $tersimpan_db = true;
    } catch (PDOException $e) {
        $tersimpan_db = false;
    }

    tambahPoin($pdo, $uid, 10, "Mengisi Evaluasi Harian ($hari_ini)", "eval_{$uid}_{$hari_ini}");

    $_SESSION['flash_fokus'] = [
        'tipe'  => 'success',
        'pesan' => $tersimpan_db
            ? 'Evaluasi harian berhasil disimpan ke database!'
            : 'Evaluasi harian berhasil disimpan ke penyimpanan lokal/cadangan!'
    ];
    header("Location: fokus.php#evaluasi");
    exit;
}

$data_eval = null;
try {
    $eval = $pdo->prepare("SELECT * FROM evaluasi_harian WHERE pengguna_id = ? AND tanggal = ? LIMIT 1");
    $eval->execute([$uid, $hari_ini]);
    $row_db = $eval->fetch(PDO::FETCH_ASSOC);
    if ($row_db) {
        $data_eval = $row_db;
    }
} catch (PDOException $e) {
    $data_eval = null;
}

$eval_json = $bacaEvalJson();
if ($eval_json) {
    if (!$data_eval) {
        $data_eval = $eval_json;
    } else {
        $data_eval['berhasil'] = $data_eval['berhasil'] ?? ($eval_json['berhasil'] ?? '');
        $data_eval['belum'] = $data_eval['belum'] ?? ($eval_json['belum'] ?? '');
        $data_eval['besok'] = $data_eval['besok'] ?? ($eval_json['besok'] ?? '');
        $data_eval['suasana_hati'] = $data_eval['suasana_hati'] ?? ($eval_json['suasana_hati'] ?? 'Baik');
    }
}

$riwayat_fokus = $pdo->prepare("SELECT * FROM sesi_fokus WHERE pengguna_id = ? ORDER BY id DESC LIMIT 5");
$riwayat_fokus->execute([$uid]);
$list_fokus = $riwayat_fokus->fetchAll();

$riwayat_eval = [];
if (file_exists($file_eval_json)) {
    $all_json = json_decode(file_get_contents($file_eval_json), true);
    if (is_array($all_json) && isset($all_json[(string)$uid]) && is_array($all_json[(string)$uid])) {
        foreach ($all_json[(string)$uid] as $tgl => $item) {
            if (is_array($item)) {
                $item['tanggal'] = $item['tanggal'] ?? $tgl;
                $riwayat_eval[$item['tanggal']] = $item;
            }
        }
    }
}
try {
    $stmtEvalList = $pdo->prepare("SELECT * FROM evaluasi_harian WHERE pengguna_id = ? ORDER BY tanggal DESC LIMIT 7");
    $stmtEvalList->execute([$uid]);
    foreach ($stmtEvalList->fetchAll(PDO::FETCH_ASSOC) as $rowItem) {
        $tgl = $rowItem['tanggal'] ?? '';
        if ($tgl !== '') {
            $riwayat_eval[$tgl] = $rowItem;
        }
    }
} catch (PDOException $e) {
    // Tetap gunakan data yang tersedia
}
krsort($riwayat_eval);
$riwayat_eval = array_slice($riwayat_eval, 0, 7, true);

renderHeader('Fokus & Evaluasi');
?>

<style>
    .btn-simpan-evaluasi {
        background-color: #095f1f;
        color: #ffffff;
        border: none;
        font-weight: 600;
        padding: 10px 18px;
        border-radius: 12px;
    }
    .btn-simpan-evaluasi:hover {
        background-color: #064416;
        color: #ffffff;
    }
</style>

<div class="container my-4">
    <?php if ($pesan_notif !== ''): ?>
        <div class="alert alert-<?= htmlspecialchars($tipe_notif) ?> rounded-4 shadow-sm mb-4">
            <?= htmlspecialchars($pesan_notif) ?>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Timer Fokus -->
        <div class="col-md-5">
            <div class="card-asror p-4 text-center">
                <h5 class="fw-bold mb-3">⏱️ Timer Belajar Fokus</h5>
                <input type="text" id="topikFokus" class="form-control mb-3 text-center" placeholder="Topik fokus (bawaan: Belajar)">
                <div class="btn-group mb-3">
                    <button type="button" onclick="setMenit(15)" class="btn btn-outline-primary btn-sm">15 Menit</button>
                    <button type="button" onclick="setMenit(25)" class="btn btn-outline-primary btn-sm active">25 Menit</button>
                    <button type="button" onclick="setMenit(45)" class="btn btn-outline-primary btn-sm">45 Menit</button>
                </div>
                <div id="tampilanTimer" class="display-3 fw-bold my-3" style="color:var(--primary-blue);">25:00</div>
                <div class="d-flex justify-content-center gap-2">
                    <button onclick="mulaiTimer()" class="btn btn-mint px-4">Mulai</button>
                    <button onclick="jedaTimer()" class="btn btn-warning px-3">Jeda</button>
                    <button onclick="resetTimer()" class="btn btn-outline-secondary px-3">Atur Ulang</button>
                </div>
                <form id="formSelesaiFokus" method="POST" class="d-none">
                    <input type="hidden" name="topik" id="inputTopik">
                    <input type="hidden" name="durasi" id="inputDurasi" value="25">
                    <input type="hidden" name="simpan_fokus" value="1">
                </form>

                <hr>
                <h6 class="fw-bold text-start small text-muted">5 Sesi Fokus Terakhir</h6>
                <?php foreach ($list_fokus as $f): ?>
                    <div class="d-flex justify-content-between small border-bottom py-1">
                        <span><?= htmlspecialchars($f['topik']) ?></span>
                        <strong><?= $f['durasi'] ?> mnt</strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Evaluasi Harian -->
        <div class="col-md-7" id="evaluasi">
            <div class="card-asror p-4">
                <h5 class="fw-bold mb-3">📝 Evaluasi Harian (<?= date('d M Y') ?>)</h5>
                <form method="POST">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">1. Apa yang berhasil diselesaikan hari ini?</label>
                        <textarea name="berhasil" class="form-control" rows="2" required><?= htmlspecialchars($data_eval['berhasil'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">2. Apa yang belum selesai?</label>
                        <textarea name="belum" class="form-control" rows="2" required><?= htmlspecialchars($data_eval['belum'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-semibold">3. Apa yang perlu diperbaiki besok?</label>
                        <textarea name="besok" class="form-control" rows="2" required><?= htmlspecialchars($data_eval['besok'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Suasana Hati Hari Ini</label>
                        <select name="suasana_hati" class="form-select">
                            <option value="Baik" <?= ($data_eval['suasana_hati']??'')=='Baik'?'selected':'' ?>>😊 Baik</option>
                            <option value="Biasa" <?= ($data_eval['suasana_hati']??'')=='Biasa'?'selected':'' ?>>😐 Biasa</option>
                            <option value="Berat" <?= ($data_eval['suasana_hati']??'')=='Berat'?'selected':'' ?>>😔 Berat</option>
                        </select>
                    </div>
                    <button type="submit" name="simpan_evaluasi" class="btn btn-simpan-evaluasi w-100">Simpan Evaluasi Harian</button>
                </form>

                <?php if (!empty($riwayat_eval)): ?>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-3" style="color: #095f1f;">
                        <i class="bi bi-calendar-event me-1"></i> Riwayat Evaluasi Harian
                    </h6>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($riwayat_eval as $tglKey => $item):
                            $tglRaw = (string)($item['tanggal'] ?? $tglKey);
                            $isHariIni = ($tglRaw === $hari_ini);
                        ?>
                            <div class="p-3 rounded-3 border" style="background: rgba(9, 95, 31, 0.03); border-color: rgba(9, 95, 31, 0.15) !important;">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <div>
                                        <span class="fw-bold small" style="color: #095f1f;">
                                            <i class="bi bi-calendar3 me-1"></i><?= htmlspecialchars($formatTanggalIndo($tglRaw)) ?>
                                        </span>
                                        <span class="text-muted small ms-1">(<?= htmlspecialchars($tglRaw) ?>)</span>
                                        <?php if ($isHariIni): ?>
                                            <span class="badge bg-warning text-dark rounded-pill ms-1">Hari Ini</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-success rounded-pill">
                                            Mood: <?= htmlspecialchars($item['suasana_hati'] ?? 'Baik') ?>
                                        </span>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus evaluasi tanggal <?= htmlspecialchars($tglRaw, ENT_QUOTES) ?>?');">
                                            <input type="hidden" name="tanggal_evaluasi" value="<?= htmlspecialchars($tglRaw) ?>">
                                            <button type="submit" name="hapus_evaluasi" class="btn btn-sm btn-outline-danger rounded-pill py-0 px-2" title="Hapus evaluasi">
                                                <i class="bi bi-trash3"></i> Hapus
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <div class="small mb-1"><strong>Berhasil:</strong> <?= nl2br(htmlspecialchars($item['berhasil'] ?? '-')) ?></div>
                                <div class="small mb-1"><strong>Belum selesai:</strong> <?= nl2br(htmlspecialchars($item['belum'] ?? '-')) ?></div>
                                <div class="small mb-0"><strong>Perbaikan besok:</strong> <?= nl2br(htmlspecialchars($item['besok'] ?? '-')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let menitAwal = 25;
let sisaDetik = 25 * 60;
let interval = null;

function updateDisplay() {
    let m = Math.floor(sisaDetik / 60);
    let s = sisaDetik % 60;
    document.getElementById('tampilanTimer').innerText = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
}
function setMenit(m) {
    jedaTimer();
    menitAwal = m;
    sisaDetik = m * 60;
    document.getElementById('inputDurasi').value = m;
    updateDisplay();
}
function mulaiTimer() {
    if (interval) return;
    interval = setInterval(() => {
        if (sisaDetik > 0) {
            sisaDetik--;
            updateDisplay();
        } else {
            jedaTimer();
            if (navigator.vibrate) navigator.vibrate([300, 100, 300]);
            document.getElementById('inputTopik').value = document.getElementById('topikFokus').value;
            alert("Alhamdulillah sesi fokus selesai! Waktunya istirahat 5 menit.");
            document.getElementById('formSelesaiFokus').submit();
        }
    }, 1000);
}
function jedaTimer() { clearInterval(interval); interval = null; }
function resetTimer() { jedaTimer(); sisaDetik = menitAwal * 60; updateDisplay(); }
</script>

<?php renderBottomNav('fokus'); ?>