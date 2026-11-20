<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'db_santrify';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

date_default_timezone_set('Asia/Jakarta');

function wajibLogin() {
    if (!isset($_SESSION['pengguna_id'])) {
        header("Location: login.php");
        exit;
    }
}

function wajibAdmin() {
    if (!isset($_SESSION['pengguna_id'])) {
        header('Location: login.php');
        exit;
    }
    if (($_SESSION['role'] ?? 'pengguna') !== 'admin') {
        http_response_code(403);
        exit('Akses ditolak. Halaman ini khusus admin.');
    }
}

function cekJadwalWajib($pdo, $pengguna_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_harian WHERE pengguna_id = ?");
    $stmt->execute([$pengguna_id]);
    if ($stmt->fetchColumn() == 0 && basename($_SERVER['PHP_SELF']) !== 'jadwal.php') {
        header("Location: jadwal.php?setup=1");
        exit;
    }
}

function tambahPoin($pdo, $pengguna_id, $poin, $keterangan, $sumber_unik) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO riwayat_poin (pengguna_id, jumlah_poin, keterangan, sumber_transaksi) VALUES (?, ?, ?, ?)");
    $stmt->execute([$pengguna_id, $poin, $keterangan, $sumber_unik]);
}

function batalPoin($pdo, $pengguna_id, $sumber_unik) {
    $stmt = $pdo->prepare("DELETE FROM riwayat_poin WHERE pengguna_id = ? AND sumber_transaksi = ?");
    $stmt->execute([$pengguna_id, $sumber_unik]);
}

function tambahPoinSantri(PDO $pdo, int $pengguna_id, int $jumlah_poin, string $keterangan): void {
    $stmt = $pdo->prepare("INSERT INTO riwayat_poin (pengguna_id, jumlah_poin, keterangan, tanggal) VALUES (?, ?, ?, CURDATE())");
    $stmt->execute([$pengguna_id, $jumlah_poin, $keterangan]);
}

function simpanSetoranHafalan(PDO $pdo, int $pengguna_id, string $surah, string $ayat, string $catatan = ''): bool {
    $stmt = $pdo->prepare("
        INSERT INTO setoran_hafalan (pengguna_id, surah_atau_juz, ayat_atau_halaman, catatan, tanggal)
        VALUES (?, ?, ?, ?, CURDATE())
    ");
    $ok = $stmt->execute([$pengguna_id, $surah, $ayat, $catatan]);
    if ($ok) {
        tambahPoinSantri($pdo, $pengguna_id, 2, "Setoran hafalan: {$surah} ({$ayat})");
    }
    return (bool)$ok;
}

function hitungPersentaseJadwalTugas(PDO $pdo, int $pengguna_id): array {
    $hari_ini = date('Y-m-d');

    $stmtJ = $pdo->prepare("
        SELECT
            COUNT(j.id) AS total_jadwal,
            SUM(CASE WHEN COALESCE(c.sudah_selesai, 0) = 1 THEN 1 ELSE 0 END) AS selesai_jadwal
        FROM jadwal_harian j
        LEFT JOIN ceklist_kegiatan c ON j.id = c.jadwal_id AND c.tanggal = ?
        WHERE j.pengguna_id = ? AND j.status_aktif = 1
    ");
    $stmtJ->execute([$hari_ini, $pengguna_id]);
    $jadwal = $stmtJ->fetch(PDO::FETCH_ASSOC);

    $stmtT = $pdo->prepare("
        SELECT
            COUNT(*) AS total_tugas,
            SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END) AS selesai_tugas
        FROM tugas
        WHERE pengguna_id = ?
    ");
    $stmtT->execute([$pengguna_id]);
    $tugas = $stmtT->fetch(PDO::FETCH_ASSOC);

    $total_jadwal = (int)($jadwal['total_jadwal'] ?? 0);
    $selesai_jadwal = (int)($jadwal['selesai_jadwal'] ?? 0);
    $total_tugas = (int)($tugas['total_tugas'] ?? 0);
    $selesai_tugas = (int)($tugas['selesai_tugas'] ?? 0);

    $persen_jadwal = $total_jadwal > 0 ? round(($selesai_jadwal / $total_jadwal) * 100) : 0;
    $persen_tugas = $total_tugas > 0 ? round(($selesai_tugas / $total_tugas) * 100) : 0;

    $total_item = $total_jadwal + $total_tugas;
    $selesai_item = $selesai_jadwal + $selesai_tugas;
    $persen_gabungan = $total_item > 0 ? round(($selesai_item / $total_item) * 100) : 0;

    return [
        'persen_gabungan' => $persen_gabungan,
        'persen_jadwal'   => $persen_jadwal,
        'persen_tugas'    => $persen_tugas,
        'selesai_jadwal'  => $selesai_jadwal,
        'total_jadwal'    => $total_jadwal,
        'selesai_tugas'   => $selesai_tugas,
        'total_tugas'     => $total_tugas,
    ];
}

function hitungPersentasePeriodeJadwalTugas(PDO $pdo, int $pengguna_id): array {
    $hari_ini = date('Y-m-d');
    $senin_ini = date('Y-m-d', strtotime('monday this week'));
    $minggu_ini = date('Y-m-d', strtotime('sunday this week'));
    $awal_bulan = date('Y-m-01');
    $akhir_bulan = date('Y-m-t');
    $hari_berjalan_minggu = (int)date('N');
    $hari_berjalan_bulan = (int)date('j');

    $stmtJ = $pdo->prepare("SELECT COUNT(*) FROM jadwal_harian WHERE pengguna_id = ? AND status_aktif = 1");
    $stmtJ->execute([$pengguna_id]);
    $jml_jadwal = (int)$stmtJ->fetchColumn();

    $hitungRentang = function (string $tgl_awal, string $tgl_akhir, int $pengali_jadwal) use ($pdo, $pengguna_id, $hari_ini, $jml_jadwal): array {
        if ($tgl_awal === $tgl_akhir) {
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM ceklist_kegiatan WHERE pengguna_id = ? AND tanggal = ? AND sudah_selesai = 1");
            $stmtC->execute([$pengguna_id, $hari_ini]);
            $selesai_jadwal = (int)$stmtC->fetchColumn();

            $stmtT = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END), 0) AS selesai FROM tugas WHERE pengguna_id = ? AND tenggat_waktu = ?");
            $stmtT->execute([$pengguna_id, $hari_ini]);
        } else {
            $stmtC = $pdo->prepare("SELECT COUNT(*) FROM ceklist_kegiatan WHERE pengguna_id = ? AND tanggal BETWEEN ? AND ? AND sudah_selesai = 1");
            $stmtC->execute([$pengguna_id, $tgl_awal, $tgl_akhir]);
            $selesai_jadwal = (int)$stmtC->fetchColumn();

            $stmtT = $pdo->prepare("SELECT COUNT(*) AS total, COALESCE(SUM(CASE WHEN status = 'Selesai' THEN 1 ELSE 0 END), 0) AS selesai FROM tugas WHERE pengguna_id = ? AND tenggat_waktu BETWEEN ? AND ?");
            $stmtT->execute([$pengguna_id, $tgl_awal, $tgl_akhir]);
        }

        $tugas = $stmtT->fetch(PDO::FETCH_ASSOC);
        $total_jadwal = $jml_jadwal * $pengali_jadwal;
        $selesai_jadwal = min($total_jadwal, (int)$selesai_jadwal);

        $total_tugas = (int)($tugas['total'] ?? 0);
        $selesai_tugas = min($total_tugas, (int)($tugas['selesai'] ?? 0));

        $total_all = $total_jadwal + $total_tugas;
        $selesai_all = $selesai_jadwal + $selesai_tugas;
        $persen = $total_all > 0 ? min(100, (int)round(($selesai_all / $total_all) * 100)) : 0;

        return [
            'selesai' => $selesai_all,
            'total'   => $total_all,
            'persen'  => $persen,
        ];
    };

    return [
        'hari'   => $hitungRentang($hari_ini, $hari_ini, 1),
        'minggu' => $hitungRentang($senin_ini, $minggu_ini, $hari_berjalan_minggu),
        'bulan'  => $hitungRentang($awal_bulan, $akhir_bulan, $hari_berjalan_bulan),
    ];
}

function hitungProgres($pdo, $pengguna_id) {
    $hari_ini = date('Y-m-d');
    $senin_ini = date('Y-m-d', strtotime('monday this week'));
    $minggu_ini = date('Y-m-d', strtotime('sunday this week'));
    $awal_bulan = date('Y-m-01');
    $akhir_bulan = date('Y-m-t');

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jadwal_harian WHERE pengguna_id = ? AND status_aktif = 1");
    $stmt->execute([$pengguna_id]);
    $jml_jadwal_aktif = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ceklist_kegiatan WHERE pengguna_id = ? AND tanggal = ? AND sudah_selesai = 1");
    $stmt->execute([$pengguna_id, $hari_ini]);
    $jadwal_selesai_hari = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu = ?");
    $stmt->execute([$pengguna_id, $hari_ini]);
    $tugas_total_hari = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu = ? AND status = 'Selesai'");
    $stmt->execute([$pengguna_id, $hari_ini]);
    $tugas_selesai_hari = (int)$stmt->fetchColumn();

    $total_hari = $jml_jadwal_aktif + $tugas_total_hari;
    $selesai_hari = $jadwal_selesai_hari + $tugas_selesai_hari;
    $persen_hari = $total_hari > 0 ? min(100, round(($selesai_hari / $total_hari) * 100, 1)) : 0;

    $hari_berjalan_minggu = (int)date('N');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ceklist_kegiatan WHERE pengguna_id = ? AND tanggal BETWEEN ? AND ? AND sudah_selesai = 1");
    $stmt->execute([$pengguna_id, $senin_ini, $minggu_ini]);
    $jadwal_selesai_minggu = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu BETWEEN ? AND ?");
    $stmt->execute([$pengguna_id, $senin_ini, $minggu_ini]);
    $tugas_total_minggu = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu BETWEEN ? AND ? AND status = 'Selesai'");
    $stmt->execute([$pengguna_id, $senin_ini, $minggu_ini]);
    $tugas_selesai_minggu = (int)$stmt->fetchColumn();

    $total_minggu = ($jml_jadwal_aktif * $hari_berjalan_minggu) + $tugas_total_minggu;
    $selesai_minggu = $jadwal_selesai_minggu + $tugas_selesai_minggu;
    $persen_minggu = $total_minggu > 0 ? min(100, round(($selesai_minggu / $total_minggu) * 100, 1)) : 0;

    $hari_berjalan_bulan = (int)date('j');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ceklist_kegiatan WHERE pengguna_id = ? AND tanggal BETWEEN ? AND ? AND sudah_selesai = 1");
    $stmt->execute([$pengguna_id, $awal_bulan, $akhir_bulan]);
    $jadwal_selesai_bulan = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu BETWEEN ? AND ?");
    $stmt->execute([$pengguna_id, $awal_bulan, $akhir_bulan]);
    $tugas_total_bulan = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tugas WHERE pengguna_id = ? AND tenggat_waktu BETWEEN ? AND ? AND status = 'Selesai'");
    $stmt->execute([$pengguna_id, $awal_bulan, $akhir_bulan]);
    $tugas_selesai_bulan = (int)$stmt->fetchColumn();

    $total_bulan = ($jml_jadwal_aktif * $hari_berjalan_bulan) + $tugas_total_bulan;
    $selesai_bulan = $jadwal_selesai_bulan + $tugas_selesai_bulan;
    $persen_bulan = $total_bulan > 0 ? min(100, round(($selesai_bulan / $total_bulan) * 100, 1)) : 0;

    return [
        'hari'   => ['selesai' => $selesai_hari, 'total' => $total_hari, 'persen' => $persen_hari],
        'minggu' => ['selesai' => $selesai_minggu, 'total' => $total_minggu, 'persen' => $persen_minggu],
        'bulan'  => ['selesai' => $selesai_bulan, 'total' => $total_bulan, 'persen' => $persen_bulan],
    ];
}

function getPenyemangatHarian() {
    $quotes = [
        "Setiap ayat yang kau ulang hari ini adalah cahaya bagi orang tuamu di akhirat kelak.",
        "Rindu rumah adalah ujian keikhlasan; jadikan doa selepas shalat sebagai peluk paling hangat.",
        "HP boleh dikumpulkan, tapi semangat menuntut ilmu dan menjaga hafalan tak boleh padam.",
        "Sedikit demi sedikit yang istiqamah lebih dicintai Allah daripada banyak namun terputus.",
        "Lelahmu membagi waktu antara sekolah dan pondok sedang dicatat sebagai amal jariyah.",
        "Murojaah menjaga hafalan agar tidak lari lebih cepat daripada unta yang lepas ikatannya.",
        "Disiplin waktu hari ini adalah kunci kemandirianmu memimpin masa depan.",
        "Jika hafalan terasa berat, periksa kembali niat dan perbanyak istighfar.",
        "Orang tuamu di rumah mendoakan keselamatan dan keberkahan ilmumu di setiap sujudnya.",
        "Selesaikan tugas sekolah sebelum waktu serah HP agar malammu tenang untuk Al-Qur'an.",
        "Santri tangguh bukan yang tak pernah lelah, tapi yang tetap bangkit menuntaskan amanah.",
        "Waktu adalah modal utama penuntut ilmu; jangan biarkan berlalu tanpa makna.",
        "Mahkota kemuliaan menanti orang tua dari anak yang bersungguh-sungguh menjaga Al-Qur'an.",
        "Shalat tepat waktu adalah pondasi keberkahan seluruh jadwal harianmu.",
        "Catat rencanamu sebelum HP diserahkan, agar setelah pulang sekolah langkahmu terarah.",
        "Kesabaran di pondok hari ini akan berbuah manis saat kau kembali mengabdi di masyarakat.",
        "Jangan menunda tugas kecil, karena ia akan menumpuk menjadi beban besar.",
        "Al-Qur'an hanya akan menetap pada hati yang bersih dan waktu yang diprioritaskan.",
        "Evaluasi diri setiap malam menjadikan hari esokmu jauh lebih baik dari hari ini.",
        "Keterbatasan memegang HP justru melatih fokus dan ketajaman ingatanmu.",
        "Berbakti kepada orang tua dimulai dari kesungguhanmu belajar dan menjaga adab di pondok.",
        "Satu halaman murojaah yang khusyuk lebih berharga daripada tergesa-gesa tanpa tadabbur.",
        "Jadilah santri yang cerdas di sekolah dan santun serta istiqamah di pesantren.",
        "Setiap kesulitan menghafal adalah penggugur dosa dan peninggi derajat.",
        "Manfaatkan waktu senggang di antara dua kegiatan untuk mengulang hafalan.",
        "Keberkahan ilmu terletak pada adab kepada guru, ustadz, dan orang tua.",
        "Rencana yang tertulis rapi membantumu tetap produktif meski tanpa gawai.",
        "Jaga shalat berjamaah, niscaya Allah jaga seluruh urusan dunia dan akhiratmu.",
        "Konsistensi kecil setiap hari akan membawamu pada pencapaian besar.",
        "Doa orang tua menembus langit; bahagiakan mereka dengan akhlak dan prestasimu.",
        "Hari ini adalah kesempatan baru untuk memperbaiki kelalaian kemarin."
    ];
    $dayOfYear = (int)date('z');
    return $quotes[$dayOfYear % count($quotes)];
}

function getFotoProfilAktif(): string {
    $mapAvatar = [
        'santri_1' => 'img/01_Santri_Hijau.png',
        'santri_2' => 'img/02_Hafidz_Cendekia.png',
        'santri_3' => 'img/03_Pejuang_Waktu.png',
        'santri_4' => 'img/04_Bintang_Asror.png',
        'santri_5' => 'img/05_Muhafidz_Muda.png',
        'santri_6' => 'img/06_Mahkota_Quran.png',
    ];

    $uid = (string)($_SESSION['pengguna_id'] ?? '');
    $fileData = __DIR__ . '/assets/user_avatars.json';
    $aktifId = 'santri_1';
    $kandidat = [];

    if ($uid !== '' && file_exists($fileData)) {
        $json = json_decode(file_get_contents($fileData), true);
        if (is_array($json) && isset($json[$uid])) {
            $entry = $json[$uid];
            if (is_string($entry)) {
                $aktifId = $entry;
                $kandidat[] = $mapAvatar[$entry] ?? $entry;
            } elseif (is_array($entry)) {
                if (!empty($entry['aktif']) && is_string($entry['aktif'])) {
                    $aktifId = $entry['aktif'];
                    if (isset($mapAvatar[$aktifId])) {
                        $kandidat[] = $mapAvatar[$aktifId];
                    }
                }
                foreach (['foto_profil', 'foto', 'file', 'path', 'gambar'] as $key) {
                    if (!empty($entry[$key]) && is_string($entry[$key])) {
                        $kandidat[] = $entry[$key];
                    }
                }
            }
        }
    }

    if (!empty($_SESSION['foto_profil']) && is_string($_SESSION['foto_profil'])) {
        $kandidat[] = $_SESSION['foto_profil'];
    }

    if (isset($mapAvatar[$aktifId])) {
        $kandidat[] = $mapAvatar[$aktifId];
    }

    foreach ($kandidat as $raw) {
        $bersih = ltrim(explode('?', trim($raw))[0], '/\\');
        if ($bersih === '') {
            continue;
        }
        if (strpos($bersih, 'data:image/') === 0 || preg_match('#^https?://#i', $bersih)) {
            return $bersih;
        }
        $fullPath = __DIR__ . '/' . $bersih;
        if (file_exists($fullPath)) {
            $_SESSION['foto_profil'] = $bersih;
            return $bersih . '?v=' . filemtime($fullPath);
        }
    }

    return $mapAvatar[$aktifId] ?? 'img/al_asror-removebg-preview.png';
}

function renderHeader($judul = 'Santrify ASROR') {
    global $pdo;
    $nama = $_SESSION['nama_santri'] ?? 'Santri';
    $sekolah = $_SESSION['nama_sekolah'] ?? '';
    $foto_profil = getFotoProfilAktif();
    if (($_SESSION['role'] ?? 'pengguna') === 'pengguna' && !isset($GLOBALS['shalat_selesai_hari_ini'])) {
        $cekShalat = $pdo->prepare('SELECT jenis_shalat FROM catatan_shalat WHERE pengguna_id = ? AND tanggal = ?');
        $cekShalat->execute([$_SESSION['pengguna_id'], date('Y-m-d')]);
        $GLOBALS['shalat_selesai_hari_ini'] = $cekShalat->fetchAll(PDO::FETCH_COLUMN);
    }
    echo '<!DOCTYPE html>
    <html lang="id" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($judul) . ' - ASROR Al-Asror</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/style.css">
        <style>
            [data-theme="dark"] body {
                background-color: #121417 !important;
                color: #eef2f6 !important;
            }
            [data-theme="dark"] .card-asror,
            [data-theme="dark"] .card {
                background-color: #1b1f24 !important;
                color: #eef2f6 !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
                box-shadow: 0 6px 18px rgba(0, 0, 0, 0.28) !important;
            }
            [data-theme="dark"] .form-control,
            [data-theme="dark"] .form-select,
            [data-theme="dark"] textarea {
                background-color: #242a32 !important;
                color: #f3f6f9 !important;
                border-color: rgba(255, 255, 255, 0.14) !important;
            }
            [data-theme="dark"] .form-control:focus,
            [data-theme="dark"] .form-select:focus,
            [data-theme="dark"] textarea:focus {
                background-color: #28303a !important;
                color: #ffffff !important;
                border-color: #22c55e !important;
                box-shadow: 0 0 0 0.2rem rgba(34, 197, 94, 0.18) !important;
            }
            [data-theme="dark"] .form-control::placeholder,
            [data-theme="dark"] textarea::placeholder {
                color: #8f9ba8 !important;
                opacity: 1;
            }
            [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator,
            [data-theme="dark"] input[type="time"]::-webkit-calendar-picker-indicator {
                filter: invert(1);
                opacity: 0.8;
            }
            [data-theme="dark"] .bg-white,
            [data-theme="dark"] .bg-light {
                background-color: #232931 !important;
                color: #eef2f6 !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .table {
                color: #eef2f6 !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .table thead th {
                background-color: #232931 !important;
                color: #d7e0e8 !important;
                border-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .table td {
                border-color: rgba(255, 255, 255, 0.07) !important;
            }
            [data-theme="dark"] .text-dark {
                color: #eef2f6 !important;
            }
            [data-theme="dark"] .text-muted {
                color: #9aa7b5 !important;
            }
            [data-theme="dark"] .bottom-nav {
                background-color: #181c20 !important;
                border-top-color: rgba(255, 255, 255, 0.08) !important;
            }
            [data-theme="dark"] .bottom-nav a {
                color: #9aa7b5 !important;
            }
            [data-theme="dark"] .bottom-nav a.active {
                color: #4ade80 !important;
            }
            [data-theme="dark"] footer {
                color: #9aa7b5 !important;
            }
        </style>
    </head>
    <body>
    <nav class="navbar navbar-asror sticky-top">
        <div class="container d-flex justify-content-between align-items-center">
            <a class="navbar-brand brand-title d-flex align-items-center gap-2" href="index.php">
                 <img src="img/al_asror-removebg-preview.png" alt="Logo Al-Asror" style="width: 50px; height: 50px; object-fit: contain; background: white; border-radius: 50%; padding: 3px;"> Santrify | ASROR
            </a>
            <div class="d-flex align-items-center gap-2">
                <button onclick="toggleTheme()" class="btn btn-sm btn-outline-light rounded-circle" title="Mode Terang/Gelap">
                    <i class="bi bi-circle-half"></i>
                </button>
                <a href="pengaturan.php" class="btn btn-sm btn-outline-light rounded-circle" title="Pengaturan">
                    <i class="bi bi-gear-fill"></i>
                </a>
                <a href="pengaturan.php" class="d-flex align-items-center gap-2 text-white text-decoration-none me-1" title="Profil Santri">
                    <img src="' . htmlspecialchars($foto_profil) . '" alt="Foto Profil ' . htmlspecialchars($nama) . '" style="width: 36px; height: 36px; object-fit: cover; border-radius: 50%; border: 2px solid #fff; background: #fff;">
                    <div class="text-end text-white d-none d-sm-block" style="line-height:1.2;">
                        <small class="fw-bold d-block">' . htmlspecialchars($nama) . '</small>
                        <small style="font-size:0.7rem; opacity:0.85;">' . htmlspecialchars($sekolah) . '</small>
                    </div>
                </a>
                <a href="logout.php" onclick="return confirm(\'Yakin ingin keluar?\')" class="btn btn-sm btn-danger rounded-pill px-3">Keluar</a>
            </div>
        </div>
    </nav>';
    if (($_SESSION['role'] ?? 'pengguna') === 'pengguna') {
        echo '<div id="shalatWarning" class="shalat-warning-backdrop" hidden>
            <section class="shalat-warning-panel" role="alertdialog" aria-modal="true" aria-labelledby="shalatWarningTitle">
                <div class="warning-symbol"><i class="bi bi-bell-fill"></i></div>
                <div class="small fw-bold text-uppercase mb-2">Pengingat Ibadah Santrify</div>
                <h2 id="shalatWarningTitle" class="fw-bold">Waktu salat telah tiba</h2>
                <p id="shalatWarningText" class="mb-3">Segera tunaikan salatmu.</p>
                <div class="warning-advice"><strong>Nasihat dan tausiah</strong>
                    <p class="mb-0">Salat merupakan kewajiban seorang muslim. Jangan biarkan kesibukan melalaikanmu. Ingatlah bahwa setiap amal akan dimintai pertanggungjawaban di akhirat. Segera tunaikan salat dan jangan sengaja menundanya.</p>
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                    <button type="button" class="btn btn-outline-light" onclick="tutupPeringatanShalat()">Saya mengerti</button>
                </div>
                <small class="d-block mt-3 opacity-75">Pengingat akan muncul kembali selama salat belum dicentang.</small>
            </section>
        </div>';
    }
}

function renderBottomNav($aktif = 'beranda') {
    if (($_SESSION['role'] ?? 'pengguna') === 'admin') {
        $menus = [
            'admin'   => ['admin.php', 'bi-speedometer2', 'Admin'],
            'beranda' => ['index.php', 'bi-house-door-fill', 'Beranda'],
            'jadwal'  => ['jadwal.php', 'bi-calendar-check-fill', 'Jadwal'],
            'tugas'   => ['tugas.php', 'bi-list-task', 'Tugas'],
            'ibadah'  => ['hafalan.php', 'bi-book-half', 'Ibadah'],
            'serah'   => ['serah_hp.php', 'bi-phone-flip', 'Serah'],
            'fokus'   => ['fokus.php', 'bi-stopwatch-fill', 'Fokus'],
            'catatan' => ['catatan_cepat.php', 'bi-journal-text', 'Catatan'],
        ];
    } else {
        $menus = [
            'beranda' => ['index.php', 'bi-house-door-fill', 'Beranda'],
            'jadwal'  => ['jadwal.php', 'bi-calendar-check-fill', 'Jadwal'],
            'tugas'   => ['tugas.php', 'bi-list-task', 'Tugas'],
            'ibadah'  => ['hafalan.php', 'bi-book-half', 'Ibadah'],
            'serah'   => ['serah_hp.php', 'bi-phone-flip', 'Serah'],
            'fokus'   => ['fokus.php', 'bi-stopwatch-fill', 'Fokus'],
            'catatan' => ['catatan_cepat.php', 'bi-journal-text', 'Catatan'],
        ];
    }
    echo '<nav class="bottom-nav">';
    foreach ($menus as $key => $m) {
        $cls = ($aktif === $key) ? 'active' : '';
        echo '<a href="' . $m[0] . '" class="' . $cls . '">
                <i class="bi ' . $m[1] . ' fs-5"></i>
                <span>' . $m[2] . '</span>
              </a>';
    }
    echo '</nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const savedTheme = localStorage.getItem("asror_theme") || "light";
        document.documentElement.setAttribute("data-theme", savedTheme);
        function toggleTheme() {
            const current = document.documentElement.getAttribute("data-theme");
            const next = current === "light" ? "dark" : "light";
            document.documentElement.setAttribute("data-theme", next);
            localStorage.setItem("asror_theme", next);
        }

        const shalatSelesaiHariIni = ' . json_encode($GLOBALS['shalat_selesai_hari_ini'] ?? []) . ';
        const urutanShalat = [
            {nama: "Subuh", api: "Fajr"}, {nama: "Dzuhur", api: "Dhuhr"},
            {nama: "Ashar", api: "Asr"}, {nama: "Maghrib", api: "Maghrib"},
            {nama: "Isya", api: "Isha"}
        ];
        let jadwalShalat = null;
        let shalatSedangDiingatkan = null;
        function tutupPeringatanShalat() {
            const modal = document.getElementById("shalatWarning");
            if (modal) modal.hidden = true;
        }
        function cekPeringatanShalat() {
            if (!jadwalShalat) return;
            const sekarang = new Date();
            const menitIni = sekarang.getHours() * 60 + sekarang.getMinutes();
            let waktuTerakhir = null;
            for (const item of urutanShalat) {
                const jam = jadwalShalat[item.api];
                if (!jam) continue;
                const bersih = jam.split(" ")[0].split(":");
                const menit = Number(bersih[0]) * 60 + Number(bersih[1]);
                if (menit <= menitIni && !shalatSelesaiHariIni.includes(item.nama)) waktuTerakhir = item;
            }
            const modal = document.getElementById("shalatWarning");
            if (!modal) return;
            if (waktuTerakhir) {
                if (shalatSedangDiingatkan !== waktuTerakhir.nama || modal.hidden) {
                    shalatSedangDiingatkan = waktuTerakhir.nama;
                    document.getElementById("shalatWarningTitle").textContent = "Waktu " + waktuTerakhir.nama + " telah tiba!";
                    document.getElementById("shalatWarningText").textContent = "Waktu " + waktuTerakhir.nama + " sudah masuk. Segera tunaikan salat dan tandai setelah selesai.";
                    modal.hidden = false;
                }
            } else {
                shalatSedangDiingatkan = null;
                modal.hidden = true;
            }
        }
        async function ambilJadwalShalat(lat, lon) {
            try {
                const d = new Date();
                const tanggal = String(d.getDate()).padStart(2, "0") + "-" + String(d.getMonth()+1).padStart(2, "0") + "-" + d.getFullYear();
                const url = "https://api.aladhan.com/v1/timings/" + tanggal + "?latitude=" + encodeURIComponent(lat) + "&longitude=" + encodeURIComponent(lon) + "&method=20";
                const response = await fetch(url);
                if (!response.ok) throw new Error("Layanan jadwal salat tidak tersedia");
                const json = await response.json();
                jadwalShalat = json.data.timings;
                cekPeringatanShalat();
                setInterval(cekPeringatanShalat, 30000);
            } catch (error) { console.warn("Jadwal salat otomatis gagal dimuat:", error); }
        }
        if (document.getElementById("shalatWarning") && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => ambilJadwalShalat(pos.coords.latitude, pos.coords.longitude),
                err => console.warn("Izin lokasi diperlukan untuk waktu salat otomatis:", err),
                {enableHighAccuracy:false, timeout:12000, maximumAge:3600000}
            );
        }
    </script>
    <footer class="text-center py-4 my-3 text-muted small" style="margin-bottom: 90px;">
        <div class="fw-semibold" style="color: #22c55e;">Santrify | ASROR</div>
        <div>Mohammad Adnil Firdan Albanna</div>
        <div>SMKN 2 Bangkalan</div>
        <div>Pondok Pesantren Tahfidzil Qur\'an Al-Asror • Mlajah, Bangkalan, Madura, Jawa Timur</div>
    </footer>
    </body>
    </html>';
}