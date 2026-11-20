-- Jalankan sekali di phpMyAdmin / HeidiSQL pada database db_santrify
ALTER TABLE pengguna ADD COLUMN role ENUM('admin','pengguna') NOT NULL DEFAULT 'pengguna' AFTER password_hash;

-- Semua akun lama otomatis menjadi 'pengguna'.
-- Registrasi publik juga selalu membuat role 'pengguna'.
-- Untuk menjadikan akun tertentu admin, jalankan perintah berikut setelah mengganti username:
-- UPDATE pengguna SET role = 'admin' WHERE username = 'username_admin';
