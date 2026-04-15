# 📡 ProTel Broadcast System

Sistem broadcast Telegram via **akun asli** (userbot) menggunakan PHP + MadelineProto.

---

## 🚀 Cara Setup

### 1. Dapatkan Telegram API Credentials
Buka **https://my.telegram.org/apps** → Login → Create App → catat **API ID** dan **API Hash**.

### 2. Isi Config
Edit file `config/app.php`:
```php
define('TG_API_ID',   '12345678');          // ganti dengan API ID kamu
define('TG_API_HASH', 'abcdef1234567890');   // ganti dengan API Hash kamu
```

### 3. Setup Database
Pastikan MySQL Laragon berjalan, lalu:
- Import `database/schema.sql` via phpMyAdmin atau:
  ```
  mysql -u root protel_broadcast < database/schema.sql
  ```
- Setelah import, **update password admin** via browser:
  ```
  http://localhost/protel/gen_password.php
  ```

### 4. Cek Setup
Buka: **http://localhost/protel/setup.php**

### 5. Login Dashboard
Buka: **http://localhost/protel/index.php**
- Username: `admin`
- Password: `admin123`

---

## 📋 Alur Login Akun Telegram

```
1. Dashboard → Tab "Akun Telegram" → Klik "+ Tambah Akun"
2. Masukkan nomor HP format internasional (+628xxx)
3. Sistem kirim OTP ke akun Telegram tersebut
4. Masukkan kode 5 digit OTP
5. (Jika ada 2FA) Masukkan password Telegram
6. Akun tersimpan dan siap digunakan untuk broadcast!
```

---

## 📢 Alur Broadcast

```
1. Tab "Daftar Kontak" → Import CSV atau tambah manual
   Format CSV: phone, nama, username (baris pertama = header)

2. Tab "Kirim Broadcast":
   - Tulis pesan (bisa markdown Telegram)
   - Pilih media (foto/video/dokumen) - opsional
   - Set delay antar pesan (min 3 detik)
   - Pilih grup kontak tujuan
   - Centang akun pengirim yang mau dipakai
   - Klik "Kirim Broadcast"

3. Tab "Riwayat" → Monitor progress real-time (auto-refresh 8 detik)
   - Bisa pause ⏸ / resume ▶ campaign yang berjalan
   - Klik 🔍 untuk lihat detail per-pesan
```

---

## 📁 Struktur File

```
protel/
├── api/
│   └── handler.php          # API endpoint (semua AJAX request)
├── assets/
│   ├── dashboard.css        # Styling dashboard
│   └── dashboard.js         # Logic frontend
├── config/
│   ├── app.php              # ⚙ KONFIGURASI UTAMA (isi API ID disini)
│   └── database.php         # Konfigurasi database
├── database/
│   └── schema.sql           # SQL schema
├── lib/
│   └── TelegramHandler.php  # Helper class MadelineProto
├── scripts/
│   ├── request_otp.php      # CLI: kirim OTP
│   ├── verify_otp.php       # CLI: verifikasi OTP
│   ├── verify_2fa.php       # CLI: verifikasi password 2FA
│   └── broadcast_worker.php # Background worker broadcast
├── sessions/                # 🔒 Session file akun (auto-created)
├── uploads/                 # Media uploads (auto-created)
├── vendor/                  # Composer dependencies
├── dashboard.php            # Halaman dashboard utama
├── gen_password.php         # Tool generate password hash
├── index.php                # Halaman login
└── setup.php                # Health check page
```

---

## ⚠️ Penting

- **Satu akun Telegram tidak boleh digunakan untuk terlalu banyak pesan** dalam waktu singkat → sistem akan terkena FLOOD_LIMIT
- Set **delay minimum 3-5 detik** antar pesan untuk mengurangi risiko ban
- Sebaiknya pakai **beberapa akun** (5-10) untuk mendistribusikan beban
- MadelineProto **berjalan lebih lambat di Windows** (peringatan normal, bukan error)
- Pastikan `sessions/` dan `uploads/` punya permission write

---

## 🛡️ Keamanan Production

1. Ganti password admin via `gen_password.php`
2. Hapus `gen_password.php` setelah digunakan
3. Set `APP_URL` ke domain production di `config/app.php`
4. Pastikan folder `sessions/` tidak dapat diakses via web (tambah `.htaccess`)
