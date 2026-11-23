SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE TABLE `avatar_milik_pengguna` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `kode_avatar` varchar(100) NOT NULL,
  `dibeli_pada` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `catatan_cepat` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `isi` text NOT NULL,
  `tipe` enum('Cepat','Pribadi') DEFAULT 'Cepat',
  `tanggal` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `catatan_cepat` (`id`, `pengguna_id`, `isi`, `tipe`, `tanggal`) VALUES
(1, 1, 'Niatkan setiap ayat hafalan untuk memakaikan mahkota cahaya kepada kedua orang tua di akhirat kelak.', 'Pribadi', '2026-11-17 01:46:06');

CREATE TABLE `catatan_hafalan` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `jenis` enum('Hafalan','Murojaah') NOT NULL,
  `tanggal` date NOT NULL,
  `juz` int NOT NULL,
  `surah` varchar(100) DEFAULT NULL,
  `ayat` varchar(50) DEFAULT NULL,
  `halaman` int DEFAULT NULL,
  `porsi` varchar(50) DEFAULT NULL,
  `selesai` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `catatan_shalat` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jenis_shalat` enum('Subuh','Dzuhur','Ashar','Maghrib','Isya') NOT NULL,
  `status_centang` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `catatan_shalat` (`id`, `pengguna_id`, `tanggal`, `jenis_shalat`, `status_centang`) VALUES
(1, 1, '2026-11-17', 'Subuh', 1),
(15, 1, '2026-11-17', 'Dzuhur', 1);

CREATE TABLE `ceklist_kegiatan` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `jadwal_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `sudah_selesai` tinyint(1) DEFAULT '0',
  `waktu_selesai` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `cek_jadwal` (
  `id` int NOT NULL,
  `jadwal_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `selesai` tinyint(1) DEFAULT '0',
  `waktu_selesai` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `daftar_pangkat` (
  `id` int NOT NULL,
  `nama_pangkat` varchar(100) NOT NULL,
  `minimal_poin` int NOT NULL,
  `urutan` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `daftar_pangkat` (`id`, `nama_pangkat`, `minimal_poin`, `urutan`) VALUES
(1, 'Santri', 0, 1),
(2, 'Santri Pro', 200, 2),
(3, 'Santri Pro Max', 500, 3),
(4, 'Santri Master', 800, 4),
(5, 'Santri Legend', 1200, 5),
(6, 'Idaman Mertua', 2000, 6);

CREATE TABLE `evaluasi_harian` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `berhasil` text NOT NULL,
  `belum` text NOT NULL,
  `besok` text NOT NULL,
  `suasana_hati` enum('Baik','Biasa','Berat') DEFAULT 'Baik'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `hadiah_impian` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `pangkat_id` int NOT NULL,
  `teks_hadiah` varchar(255) NOT NULL,
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `identitas` (
  `id` int NOT NULL,
  `nama_santri` varchar(100) NOT NULL,
  `nama_sekolah` varchar(150) NOT NULL,
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `jadwal` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `nama_kegiatan` varchar(150) NOT NULL,
  `waktu` time DEFAULT NULL,
  `aktif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `jadwal_harian` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `nama_kegiatan` varchar(150) NOT NULL,
  `kategori` enum('Sekolah','Belajar','Ibadah','Hafalan','Murojaah','Pondok','Istirahat','Pribadi') NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL,
  `poin` int DEFAULT '10',
  `status_aktif` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `jadwal_harian` (`id`, `pengguna_id`, `nama_kegiatan`, `kategori`, `jam_mulai`, `jam_selesai`, `poin`, `status_aktif`) VALUES
(3, 1, 'solat tahajjud', 'Pondok', '03:15:00', '04:30:00', 2, 1);

CREATE TABLE `nama_pengguna` (
  `id` int NOT NULL,
  `nama` varchar(100) DEFAULT NULL,
  `nama_sekolah` varchar(150) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pangkat_pengguna` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `pangkat_id` int NOT NULL,
  `tanggal_diperoleh` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pengaturan` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tema` varchar(20) DEFAULT 'terang',
  `notifikasi_aktif` tinyint(1) DEFAULT '1',
  `waktu_pengingat` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pengaturan` (`id`, `pengguna_id`, `tema`, `notifikasi_aktif`, `waktu_pengingat`) VALUES
(1, 1, 'gelap', 1, '16:00:00');

CREATE TABLE `pengguna` (
  `id` int NOT NULL,
  `nama_santri` varchar(100) NOT NULL,
  `nama_sekolah` varchar(150) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','pengguna') NOT NULL DEFAULT 'pengguna',
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `avatar_aktif` varchar(100) DEFAULT 'pangkat_default'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `pengguna` (`id`, `nama_santri`, `nama_sekolah`, `username`, `password_hash`, `role`, `tanggal_dibuat`, `avatar_aktif`) VALUES
(1, 'Firdan', 'SMKN 2 Bangkalan', 'Firdan', '$2y$10$sqGkfux4go2YZ6duIsktV.zb.SlM7G.QwwZqx27cZH/yFlq0x9ohu', 'admin', '2026-11-17 01:46:06', 'pangkat_default');

CREATE TABLE `poin` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `jumlah_poin` int NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `sumber_tipe` varchar(50) DEFAULT NULL,
  `sumber_id` int DEFAULT NULL,
  `tanggal` date DEFAULT NULL,
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `progres_harian` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `total_kegiatan` int DEFAULT '0',
  `kegiatan_selesai` int DEFAULT '0',
  `persentase` decimal(5,2) DEFAULT '0.00',
  `total_poin` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rencana_serah` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time DEFAULT NULL,
  `isi` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rencana_serah_hp` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `jam` time DEFAULT NULL,
  `isi_kegiatan` varchar(255) NOT NULL,
  `urutan` int DEFAULT '1',
  `selesai` tinyint(1) DEFAULT '0',
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `riwayat_poin` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `jumlah_poin` int NOT NULL,
  `keterangan` varchar(255) NOT NULL,
  `sumber_transaksi` varchar(100) DEFAULT NULL,
  `tanggal_dibuat` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `sesi_fokus` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `topik` varchar(150) NOT NULL,
  `durasi` int NOT NULL,
  `tanggal` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `setoran_hafalan` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `surah_atau_juz` varchar(150) NOT NULL,
  `ayat_atau_halaman` varchar(100) NOT NULL,
  `catatan` text,
  `tanggal` date NOT NULL,
  `dibuat_pada` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tugas` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `judul_tugas` varchar(200) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `tenggat_waktu` date DEFAULT NULL,
  `prioritas` enum('Rendah','Sedang','Tinggi') DEFAULT 'Sedang',
  `status` enum('Belum','Proses','Selesai') DEFAULT 'Belum',
  `poin` int DEFAULT '15'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `waktu_shalat` (
  `id` int NOT NULL,
  `pengguna_id` int NOT NULL,
  `nama_shalat` varchar(20) NOT NULL,
  `jam_mulai` time NOT NULL,
  `batas_akhir` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `avatar_milik_pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_milik` (`pengguna_id`,`kode_avatar`);

ALTER TABLE `catatan_cepat`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `catatan_hafalan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `catatan_shalat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_shalat_tanggal` (`pengguna_id`,`tanggal`,`jenis_shalat`);

ALTER TABLE `ceklist_kegiatan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_jadwal_tanggal` (`jadwal_id`,`tanggal`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `cek_jadwal`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jadwal_id` (`jadwal_id`,`tanggal`);

ALTER TABLE `daftar_pangkat`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `evaluasi_harian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unik_eval_tanggal` (`pengguna_id`,`tanggal`);

ALTER TABLE `hadiah_impian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_hadiah_pangkat` (`pengguna_id`,`pangkat_id`),
  ADD KEY `fk_hadiah_pangkat` (`pangkat_id`);

ALTER TABLE `identitas`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `jadwal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `jadwal_harian`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `nama_pengguna`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `pangkat_pengguna`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`),
  ADD KEY `pangkat_id` (`pangkat_id`);

ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `poin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `progres_harian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `pengguna_id` (`pengguna_id`,`tanggal`);

ALTER TABLE `rencana_serah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `rencana_serah_hp`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_serah_hp_pengguna` (`pengguna_id`,`tanggal`);

ALTER TABLE `riwayat_poin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sumber_transaksi` (`sumber_transaksi`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `sesi_fokus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `setoran_hafalan`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `pengguna_id` (`pengguna_id`);

ALTER TABLE `waktu_shalat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_pengguna_shalat` (`pengguna_id`,`nama_shalat`);

ALTER TABLE `avatar_milik_pengguna`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `catatan_cepat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `catatan_hafalan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `catatan_shalat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

ALTER TABLE `ceklist_kegiatan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `cek_jadwal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `daftar_pangkat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

ALTER TABLE `evaluasi_harian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

ALTER TABLE `hadiah_impian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `identitas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `jadwal`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `jadwal_harian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `nama_pengguna`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `pangkat_pengguna`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `pengaturan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

ALTER TABLE `pengguna`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `poin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `progres_harian`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `rencana_serah`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

ALTER TABLE `rencana_serah_hp`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `riwayat_poin`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

ALTER TABLE `sesi_fokus`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `setoran_hafalan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `tugas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

ALTER TABLE `waktu_shalat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `catatan_cepat`
  ADD CONSTRAINT `catatan_cepat_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `catatan_hafalan`
  ADD CONSTRAINT `catatan_hafalan_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `catatan_shalat`
  ADD CONSTRAINT `catatan_shalat_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `ceklist_kegiatan`
  ADD CONSTRAINT `ceklist_kegiatan_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ceklist_kegiatan_ibfk_2` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal_harian` (`id`) ON DELETE CASCADE;

ALTER TABLE `cek_jadwal`
  ADD CONSTRAINT `cek_jadwal_ibfk_1` FOREIGN KEY (`jadwal_id`) REFERENCES `jadwal` (`id`) ON DELETE CASCADE;

ALTER TABLE `evaluasi_harian`
  ADD CONSTRAINT `evaluasi_harian_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `hadiah_impian`
  ADD CONSTRAINT `fk_hadiah_pangkat` FOREIGN KEY (`pangkat_id`) REFERENCES `daftar_pangkat` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_hadiah_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `jadwal`
  ADD CONSTRAINT `jadwal_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `jadwal_harian`
  ADD CONSTRAINT `jadwal_harian_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `pangkat_pengguna`
  ADD CONSTRAINT `pangkat_pengguna_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `pangkat_pengguna_ibfk_2` FOREIGN KEY (`pangkat_id`) REFERENCES `daftar_pangkat` (`id`);

ALTER TABLE `pengaturan`
  ADD CONSTRAINT `pengaturan_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`);

ALTER TABLE `poin`
  ADD CONSTRAINT `poin_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`);

ALTER TABLE `progres_harian`
  ADD CONSTRAINT `progres_harian_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`);

ALTER TABLE `rencana_serah`
  ADD CONSTRAINT `rencana_serah_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `rencana_serah_hp`
  ADD CONSTRAINT `fk_serah_hp_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `riwayat_poin`
  ADD CONSTRAINT `riwayat_poin_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `sesi_fokus`
  ADD CONSTRAINT `sesi_fokus_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `tugas`
  ADD CONSTRAINT `tugas_ibfk_1` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;

ALTER TABLE `waktu_shalat`
  ADD CONSTRAINT `fk_waktu_shalat_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;